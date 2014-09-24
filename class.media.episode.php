<?php

	require_once 'class.media.file.php';

	class MediaEpisode extends MediaFile {

		public $debug = false;
		public $export_dir;
		public $dvd_iso;
		public $episode_id;
		public $isos_dir;
		public $queue_dir;
		public $episodes_dir;
		public $episode_title_filename;
		public $queue_model;
		public $queue_iso_symlink;
		public $queue_handbrake_script;
		public $queue_handbrake_output;
		public $queue_handbrake_x264;
		public $queue_mkvmerge_script;
		public $queue_mkvmerge_output;
		public $queue_matroska_xml;
		public $queue_matroska_mkv;
		public $episode_mkv;
		public $metadata;
		public $arr_queue_status;
		public $chapter_lengths = array();

		public $encoder_version;
		public $encode_stage_command;
		public $encode_stage_output;
		public $encode_stage_exit_code = 0;

		public $matroska_xml = '';

		public $remux_stage_command;
		public $remux_stage_output;
		public $remux_stage_exit_code = 0;

		public function __construct($episode_id, $export_dir = null) {

			clearstatcache();

			$this->episode_id = $episode_id;

			if(is_null($export_dir))
				$export_dir = realpath(getenv('HOME').'/dvds/');
			$this->export_dir = $export_dir;

			$episodes_model = new Episodes_Model($episode_id);
			$this->metadata = $episodes_model->get_metadata();
			$tracks_model = new Tracks_Model($this->metadata['track_id']);
			$series_model = new Series_Model($this->metadata['series_id']);
			$this->queue_model = new Queue_Model();
			$this->encodes_model = new Encodes_Model();

			// The view uses the full title name, including parts, which is what is
			// used in the final filename for the title.  Reset it in the array
			// so that we have the original source.
			$this->metadata['episode_title'] = $episodes_model->title;

			// Clarify the season -- the model uses this one from the view
			unset($this->metadata['series_dvds_season']);

			// The episodes model handles the heavy lifting perfectly :)
			$this->metadata['episode_number'] = $episodes_model->get_number();

			// Use the view to find the ISO filename
			$this->dvd_iso = basename($episodes_model->get_iso());

			// Query the series model to get the collection title, since it's not
			// a part of the view (FIXME)
			$series_model = new Series_Model($this->metadata['series_id']);
			$this->metadata['collection_title'] = $series_model->get_collection_title();

			// Handbrake needs a fixed ending chapter given, otherwise it will
			// only encode the first chapter passed to it.  Update it in the
			// metadata here to match the first chapter if an ending one is null.
			if(!is_null($this->metadata['episode_starting_chapter']) && is_null($this->metadata['episode_ending_chapter'])) {
				$this->metadata['episode_ending_chapter'] = $tracks_model->get_num_chapters();
			}

			// Add metadata not in the view
			$this->metadata['production_year'] = $series_model->production_year;
			$this->metadata['episode_number'] = $episodes_model->get_number();

			// Calculate year for air date
			if($this->metadata['season'] && $this->metadata['production_year'])
				$this->metadata['episode_year'] = $this->metadata['season'] + $this->metadata['production_year'];
			else
				$this->metadata['episode_year'] = $this->metadata['production_year'];

			// Get all the filenames
			$this->episode_title_filename = $this->get_episode_title_filename();
			$this->queue_dir = $this->get_queue_dir();
			$this->isos_dir = $this->get_isos_dir();
			$this->episodes_dir = $this->get_episodes_dir();
			$this->queue_iso_symlink = $this->get_queue_iso_symlink();
			$this->queue_handbrake_script = $this->get_queue_handbrake_script();
			$this->queue_handbrake_output = $this->get_queue_handbrake_output();
			$this->queue_handbrake_x264 = $this->get_queue_handbrake_x264();
			$this->queue_mkvmerge_script = $this->get_queue_mkvmerge_script();
			$this->queue_mkvmerge_output = $this->get_queue_mkvmerge_output();
			$this->queue_matroska_xml = $this->get_queue_matroska_xml();
			$this->queue_matroska_mkv = $this->get_queue_matroska_mkv();
			$this->episode_mkv = $this->get_episode_mkv();

		}

		public function get_episode_title_filename() {

			$episodes_model = new Episodes_Model($this->episode_id);
			$display_episode_number = str_pad($this->metadata['episode_number'], 2, 0, STR_PAD_LEFT);
			$series_model = new Series_Model($this->metadata['series_id']);

			$episode_prefix = '';
			$episode_suffix = '';

			// FIXME Take into account 10+seasons
			if($series_model->indexed == 't') {
				if(!$this->metadata['episode_season'])
					$display_season = 1;
				else
					$display_season = $this->metadata['episode_season'];

				$episode_prefix = "${display_season}.${display_episode_number}. ";
			}

			if($this->metadata['episode_part'])
				$episode_suffix = ", Part ".$this->metadata['episode_part'];

			/** Filenames **/
			$filename = $episode_prefix.$this->metadata['episode_title'].$episode_suffix;

			return $filename;

		}

		public function get_queue_dir() {

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->metadata['series_title'])."/";
			$dir .= $this->safe_filename_title($this->episode_title_filename)."/";

			return $dir;

		}

		public function create_queue_dir() {

			clearstatcache();

			$dir = $this->get_queue_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

		public function get_queue_iso_symlink() {

			$filename = $this->export_dir;
			$filename .= "queue/";
			$filename .= $this->safe_filename_title($this->metadata['series_title'])."/";
			$filename .= basename($this->dvd_iso);

			return $filename;

		}

		public function create_queue_iso_symlink($source = '') {

			clearstatcache();

			if(!strlen($source)) {
				$dir = $this->get_isos_dir();
				$source = $dir.$this->dvd_iso;
			}

			assert(file_exists($source));

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->metadata['series_title'])."/";

			if(!is_dir($dir))
				mkdir($dir, 0755, true);

			// If a symlink exists, but the source file does not, start over
			if(is_link($this->queue_iso_symlink) && !file_exists($this->queue_iso_symlink))
				unlink($this->queue_iso_symlink);

			if(!file_exists($this->queue_iso_symlink))
				symlink($source, $this->queue_iso_symlink);

		}

		public function remove_queue_iso_symlink() {

			clearstatcache();

			if(file_exists($this->queue_iso_symlink) && is_symlink($this->queue_iso_symlink))
				unlink($this->queue_iso_symlink);

		}

		public function get_episodes_dir() {

			$dir = $this->export_dir;
			$dir .= "episodes/";
			$dir .= $this->filename_title($this->metadata['series_title'])."/";

			return $dir;

		}

		public function create_episodes_dir() {

			clearstatcache();

			$dir = $this->get_episodes_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

		public function get_isos_dir() {

			$dir = $this->export_dir;
			$dir .= "isos/";
			$dir .= $this->safe_filename_title($this->metadata['collection_title'])."/";
			$dir .= $this->safe_filename_title($this->metadata['series_title'])."/";

			return $dir;

		}

		public function get_queue_handbrake_script() {

			$filename = $this->get_queue_dir();
			$filename .= "handbrake.sh";

			return $filename;

		}

		public function get_queue_handbrake_output() {

			$filename = $this->get_queue_dir();
			$filename .= "encode.out";

			return $filename;

		}

		public function get_queue_handbrake_x264() {

			$filename = $this->get_queue_dir();
			$filename .= "x264.mkv";

			return $filename;

		}

		public function get_queue_mkvmerge_script() {

			$filename = $this->get_queue_dir();
			$filename .= "mkvmerge.sh";

			return $filename;

		}

		public function get_queue_mkvmerge_output() {

			$filename = $this->get_queue_dir();
			$filename .= "mkvmerge.out";

			return $filename;

		}

		public function get_queue_matroska_xml() {

			$filename = $this->get_queue_dir();
			$filename .= "matroska.xml";

			return $filename;

		}

		public function get_queue_matroska_mkv() {

			$filename = $this->get_queue_dir();
			$filename .= "queue_matroska.mkv";

			return $filename;

		}

		public function get_episode_mkv() {

			$filename = $this->get_episodes_dir();
			$filename .= $this->episode_title_filename.".mkv";

			return $filename;

		}

		public function remove_queue_dir() {

			clearstatcache();

			if(file_exists($this->queue_iso_symlink))
				unlink($this->queue_iso_symlink);
			if(file_exists($this->queue_handbrake_script))
				unlink($this->queue_handbrake_script);
			if(file_exists($this->queue_handbrake_output))
				unlink($this->queue_handbrake_output);
			if(file_exists($this->queue_handbrake_x264))
				unlink($this->queue_handbrake_x264);
			if(file_exists($this->queue_mkvmerge_script))
				unlink($this->queue_mkvmerge_script);
			if(file_exists($this->queue_mkvmerge_output))
				unlink($this->queue_mkvmerge_output);
			if(file_exists($this->queue_matroska_xml))
				unlink($this->queue_matroska_xml);
			if(file_exists($this->queue_matroska_mkv))
				unlink($this->queue_matroska_mkv);
			if(is_dir($this->queue_dir)) {
				// Only remove directory if it's empty
				if(count(scandir($this->queue_dir)) == 2)
					rmdir($this->queue_dir);
			}

		}

		/**
		 * Check if an episode file has been encoded *and* the file exists
		 *
		 * Queue status codes: 1 for encoding, 2 for encoding failed
		 */
		public function encoded() {

			clearstatcache();

			$status = $this->get_queue_status();

			if(($status > 2 || is_null($status)) && file_exists($this->episode_mkv))
				return true;
			else
				return false;

		}

		/**
		 * Get encoding status
		 *
		 * If value is NULL, then it is either not in the queue or has been encoded
		 */
		public function get_queue_status() {

			$queue_model = new Queue_Model;

			$arr = $queue_model->get_episode_status($this->episode_id);

			// Stupid model function sets values to strings, fix it here
			foreach($arr as $key => $value)
				$arr[$key] = intval($value);

			return $arr;

		}

		/** Encoding **/

		public function create_pre_encode_stage_files() {

			$this->create_queue_dir();
			$this->create_queue_iso_symlink();

			file_put_contents($this->queue_handbrake_script, $this->encode_stage_command." $*\n");
			chmod($this->queue_handbrake_script, 0755);

			$this->encodes_model->encode_cmd = $this->encode_stage_command;

		}

		// Track encoding session in the database
		// A little bit about the encodes table ... it is designed to keep track of
		// *attempts* to encode an episode, and is not meant to be a tracker for a
		// unique episode.  The valuable part is the uuid that will be stored in the
		// container metadata when everything is finished -- it will point to the
		// database entry where the encoding settings, commands, reuslts, etc. are
		// stored.
		// The database table is intended to be abused, so creating an entry as soon
		// as possible falls within that goal, but only occurs when a dry run is not
		// enabled.
		public function create_encodes_entry() {

			$this->encodes_model->create_new();
			$this->encodes_model->episode_id = $this->episode_id;
			$this->encodes_model->encoder_version = strval($this->encoder_version);
			$this->uuid = $this->encodes_model->uniq_id;
			$this->encode_begin_time = time();
			$this->encodes_model->encode_begin = date('%r');

		}

		public function encode_stage_output() {

			$encode_stage_output = file_get_contents($this->queue_handbrake_output);
			$encode_stage_output = mb_convert_encoding($encode_stage_output, 'UTF-8');
			$this->encodes_model->encode_output = $encode_stage_output;

			return $encode_stage_output;

		}

		/**
		 * Starts the encode stage for DVD to HandBrake MKV file
		 *
		 * Checks if file exists, etc.  Optional to force the encode.
		 *
		 * @param force encode stage
		 */
		public function encode_stage($force = false) {

			$this->create_pre_encode_stage_files();

			clearstatcache();

			$exit_code = null;

			$this->queue_model->set_episode_status($this->episode_id, 'x264', 1);

			if(file_exists($this->queue_handbrake_x264) && !$force) {

				if(file_exists($this->queue_handbrake_output) && !$this->encodes_model->encode_output) {
					$this->encode_stage_output = $this->encode_stage_output();
				}

				$this->queue_model->set_episode_status($this->episode_id, 'x264', 2);
				return true;

			}

			if(!file_exists($this->queue_handbrake_x264) || $force) {

				$exit_code = $this->encode_video();

				$this->encode_stage_output = $this->encode_stage_output();

				$this->encodes_model->encoder_exit_code = $exit_code;
				$this->encode_stage_exit_code = $exit_code;

			}

			if($exit_code === 0) {

				$this->queue_model->set_episode_status($this->episode_id, 'x264', 2);
				return true;

			} else {

				$this->queue_model->set_episode_status($this->episode_id, 'x264', 3);
				return false;

			}

		}

		/**
		 * Encodes the video directly, with no arguments
		 */
		public function encode_video() {

			$arg_queue_handbrake_output = escapeshellarg($this->queue_handbrake_output);
			if($this->debug)
				$passthru_command = $this->encode_stage_command ." 2>&1 | tee $arg_queue_handbrake_output";
			else
				$passthru_command = $this->encode_stage_command ." 2> $arg_queue_handbrake_output";

			passthru($passthru_command, $exit_code);

			return $exit_code;

		}

		/** Metadata Stage **/

		public function create_pre_metadata_stage_files() {

			$this->create_queue_dir();
			$this->matroska_xml = mb_convert_encoding($this->matroska_xml, 'UTF-8');
			$ret = file_put_contents($this->queue_matroska_xml, $this->matroska_xml);

			$this->encodes_model->remux_metadata = $this->matroska_xml;

			if($ret === false)
				return false;
			else
				return true;

		}

		public function metadata_stage($force = false) {

			$this->create_pre_metadata_stage_files();

			clearstatcache();

			$this->queue_model->set_episode_status($this->episode_id, 'xml', 1);

			if(file_exists($this->queue_matroska_xml) && !$force) {
				$this->queue_model->set_episode_status($this->episode_id, 'xml', 3);
				return true;
			}

			if(!strlen($this->matroska_xml)) {
				$this->queue_model->set_episode_status($this->episode_id, 'xml', 2);
				return false;
			}

			$this->encodes_model->remux_metadata = $this->matroska_xml;

			$bool = $this->create_matroska_xml_file();

			if(!$bool) {
				$this->queue_model->set_episode_status($this->episode_id, 'xml', 2);
				return false;
			}

			$this->queue_model->set_episode_status($this->episode_id, 'xml', 3);

			return true;

		}

		/** Remux stage **/

		// Create the files on the filesystem, and update the encodes table
		public function create_pre_remux_stage_files() {

			$this->create_queue_dir();
			$contents = $this->remux_stage_command." $*\n";
			file_put_contents($this->queue_mkvmerge_script, $contents);
			chmod($this->queue_mkvmerge_script, 0755);
			$this->encodes_model->remux_command = $this->remux_stage_command;

		}

		public function create_post_remux_stage_files() {

			file_put_contents($this->queue_mkvmerge_output, $this->remux_stage_output);

		}

		public function remux_video() {

			$command = $this->remux_stage_command." 2>&1";
			exec($command, $arr, $exit_code);
			$remux_stage_output = implode("\n", $arr)."\n";
			$this->remux_stage_output = $remux_stage_output;

			return $exit_code;

		}

		public function remux_set_filesize() {

			clearstatcache();

			if(!file_exists($this->queue_matroska_mkv))
				return false;

			$filesize = filesize($this->queue_matroska_mkv);

			if($filesize === false)
				return false;

			$this->encodes_model->filesize = $filesize;

			return true;

		}

		public function remux_stage($force = false) {

			$this->create_pre_remux_stage_files();

			clearstatcache();

			if(file_exists($this->queue_matroska_mkv) && !$force) {
				$this->queue_model->set_episode_status($this->episode_id, 'mkv', 3);
				$this->remux_set_filesize();
				return true;
			}

			$this->queue_model->set_episode_status($this->episode_id, 'mkv', 1);
			$exit_code = $this->remux_video();
			$this->remux_set_filesize();
			$this->encodes_model->remux_output = $this->remux_stage_output;
			$this->encodes_model->remux_exit_code = $exit_code;
			$this->remux_stage_exit_code = $exit_code;
			$this->create_post_remux_stage_files();

			if(!($exit_code === 0 || $exit_code === 1)) {
				$this->queue_model->set_episode_status($this->episode_id, 'mkv', 2);
				return false;
			}

			$this->queue_model->set_episode_status($this->episode_id, 'mkv', 3);
			return true;

		}

		public function final_stage($force = false) {

			$this->encode_finish_time = time();
			$this->encodes_model->encode_finish = date('%r');

			clearstatcache();

			if(file_exists($this->episode_mkv) && $force == false) {

				if(!$this->debug) {
					$this->queue_model->remove_episode($this->episode_id);
					$this->remove_queue_dir();
				}
				return true;

			}

			if($this->debug) {

				$bool = copy($this->queue_matroska_mkv, $this->episode_mkv);
				return $bool;

			} else {

				$bool = rename($this->queue_matroska_mkv, $this->episode_mkv);
				if($bool) {
					$this->queue_model->remove_episode($this->episode_id);
					$this->remove_queue_dir();
				}
				return $bool;

			}


		}

		// In the queue, at any stage
		public function in_queue() {

			$arr = $this->get_queue_status();

			if(count($arr))
				return true;
			else
				return false;

		}

		public function x264_ready() {

			$arr = $this->get_queue_status();

			if($arr['x264'] === 0)
				return true;
			else
				return false;

		}

		public function x264_running() {

			$arr = $this->get_queue_status();

			if($arr['x264'] === 1)
				return true;
			else
				return false;

		}

		public function x264_passed() {

			$arr = $this->get_queue_status();

			if($arr['x264'] === 2)
				return true;
			else
				return false;

		}

		public function x264_failed() {

			$arr = $this->get_queue_status();

			if($arr['x264'] === 3)
				return true;
			else
				return false;

		}

		public function xml_ready() {

			$arr = $this->get_queue_status();

			if($arr['xml'] === 0)
				return true;
			else
				return false;

		}

		public function xml_running() {

			$arr = $this->get_queue_status();

			if($arr['xml'] === 1)
				return true;
			else
				return false;

		}

		public function xml_passed() {

			$arr = $this->get_queue_status();

			if($arr['xml'] === 2)
				return true;
			else
				return false;

		}

		public function xml_failed() {

			$arr = $this->get_queue_status();

			if($arr['xml'] === 3)
				return true;
			else
				return false;

		}

		public function mkv_ready() {

			$arr = $this->get_queue_status();

			if($arr['mkv'] === 0)
				return true;
			else
				return false;

		}

		public function mkv_running() {

			$arr = $this->get_queue_status();

			if($arr['mkv'] === 1)
				return true;
			else
				return false;

		}

		public function mkv_passed() {

			$arr = $this->get_queue_status();

			if($arr['mkv'] === 2)
				return true;
			else
				return false;

		}

		public function mkv_failed() {

			$arr = $this->get_queue_status();

			if($arr['mkv'] === 3)
				return true;
			else
				return false;

		}

	}
