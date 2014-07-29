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
		public $queue_handbrake_script;
		public $queue_handbrake_output;
		public $queue_mkmverge_script;
		public $queue_mkmerge_output;
		public $queue_xml;
		public $queue_x264;
		public $queue_mkv;
		public $episosde_mkv;

		public function __construct($export_dir, $dvd_iso, $collection_title, $series_title, $episode_title, $episode_id) {

			$this->export_dir = realpath($export_dir)."/";
			$this->dvd_iso = $dvd_iso;
			$this->collection_title = $collection_title;
			$this->series_title = $series_title;
			$this->episode_title = $episode_title;
			$this->episode_id = $episode_id;

			$this->episode_title_filename = $this->get_episode_title_filename();



			$this->queue_dir = $this->get_queue_dir();
			$this->isos_dir = $this->get_isos_dir();
			$this->episodes_dir = $this->get_episodes_dir();
			$this->queue_iso_symlink = $this->get_queue_dir().basename($this->dvd_iso);

			$this->queue_handbrake_script_filename = $this->get_queue_handbrake_script_filename();
			$this->queue_handbrake_output_filename = $this->get_queue_handbrake_output_filename();

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

		public function create_queue_iso_symlink() {

			if(!file_exists($this->queue_iso_symlink))
				symlink($this->dvd_iso, $this->queue_iso_symlink);

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

		public function get_queue_handbrake_script_filename() {

			$filename = $this->get_queue_dir();
			$filename .= "handbrake.sh";

			return $filename;

		}

		public function get_queue_handbrake_output_filename() {

			$filename = $this->get_queue_dir();
			$filename .= "encode.out";

			return $filename;

		}

	}
