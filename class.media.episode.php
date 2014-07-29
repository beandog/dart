<?php

	require_once 'class.media.file.php';

	class MediaEpisode extends MediaFile {

		public $export_dir;
		public $dvd_iso;
		public $collection_title;
		public $series_title;
		public $episode_title;
		public $episode_id;
		public $isos_dir;
		public $queue_dir;
		public $episodes_dir;
		public $queue_iso_symlink;
		public $queue_handbrake_script;
		public $queue_handbrake_output;
		public $queue_handbrake_x264;
		public $queue_mkvmerge_script;
		public $queue_mkvmerge_output;
		public $queue_matroska_xml;
		public $queue_matroska_mkv;
		public $episode_mkv;

		public function __construct($export_dir, $dvd_iso, $collection_title, $series_title, $episode_title, $episode_id) {

			$this->export_dir = realpath($export_dir)."/";
			$this->dvd_iso = basename($dvd_iso);
			$this->collection_title = $collection_title;
			$this->series_title = $series_title;
			$this->episode_title = $episode_title;
			$this->episode_id = $episode_id;
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
			$track_id = $episodes_model->track_id;
			$episode_number = $episodes_model->get_number();
			$display_episode_number = str_pad($episode_number, 2, 0, STR_PAD_LEFT);
			$episode_part = $episodes_model->part;
			$episode_season = $episodes_model->get_season();
			$series_model = new Series_Model($episodes_model->get_series_id());

			$episode_prefix = '';
			$episode_suffix = '';

			// FIXME Take into account 10+seasons
			if($series_model->indexed == 't') {
				if(!$episode_season)
					$display_season = 1;
				else
					$display_season = $episode_season;

				$episode_prefix = "${display_season}.${display_episode_number}. ";
			}

			if($episode_part)
				$episode_suffix = ", Part $episode_part";

			/** Filenames **/
			$filename = $episode_prefix.$this->episode_title.$episode_suffix;

			return $filename;

		}

		public function get_queue_dir() {

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->series_title)."/";
			$dir .= $this->safe_filename_title($this->episode_title_filename)."/";

			return $dir;

		}

		public function create_queue_dir() {

			$dir = $this->get_queue_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

		public function get_queue_iso_symlink() {

			$filename = $this->export_dir;
			$filename .= "queue/";
			$filename .= $this->safe_filename_title($this->series_title)."/";
			$filename .= basename($this->dvd_iso);

			return $filename;

		}

		public function create_queue_iso_symlink($source = '') {


			if(!strlen($source))
				$source = $this->isos_dir.$this->dvd_iso;

			assert(file_exists($source));

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->series_title)."/";

			if(!is_dir($dir))
				mkdir($dir, 0755, true);

			if(!file_exists($this->queue_iso_symlink))
				symlink($source, $this->queue_iso_symlink);

		}

		public function remove_queue_iso_symlink() {

			if(file_exists($this->queue_iso_symlink) && is_symlink($this->queue_iso_symlink))
				unlink($this->queue_iso_symlink);

		}

		public function get_episodes_dir() {

			$dir = $this->export_dir;
			$dir .= "episodes/";
			$dir .= $this->safe_filename_title($this->series_title)."/";

			return $dir;

		}

		public function create_episodes_dir() {

			$dir = $this->get_episodes_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

		public function get_isos_dir() {

			$dir = $this->export_dir;
			$dir .= "isos/";
			$dir .= $this->safe_filename_title($this->collection_title)."/";
			$dir .= $this->safe_filename_title($this->series_title)."/";

			return $dir;

		}

		public function create_isos_dir() {

			$dir = $this->get_isos_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

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
			if(is_dir($this->queue_dir))
				rmdir($this->queue_dir);

		}

	}
