<?php

	require_once 'class.media.file.php';

	class MediaEpisode extends MediaFile {

		public $export_dir;
		public $dvd_iso;
		public $episode_id;
		public $isos_dir;
		public $queue_dir;
		public $episodes_dir;
		public $episode_title_filename;
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

		public function __construct($episode_id, $export_dir) {

			clearstatcache();

			$this->episode_id = $episode_id;
			$this->export_dir = $export_dir;
			$episodes_model = new Episodes_Model($episode_id);
			$this->metadata = $episodes_model->get_metadata();
			$tracks_model = new Tracks_Model($this->metadata['track_id']);
			$series_model = new Series_Model($this->metadata['series_id']);

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
			$this->metadata['production_studio'] = $series_model->production_studio;
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
