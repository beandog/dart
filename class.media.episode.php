<?php

	class MediaEpisode extends MediaFile {

		public $collection_title;
		public $series_title;
		public $episode_title;
		public $export_dir;
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

		public function __construct($collection_title, $series_title, $episode_title, $export_dir) {

			$this->collection_title = $collection_title;
			$this->series_title = $series_title;
			$this->episode_title = $episode_title;
			$this->export_dir = $export_dir;

		}

		public function get_queue_dir() {

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->title)."/";

			return $dir;

		}

		public function create_queue_dir() {

			$dir = $this->get_queue_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

		public function get_episodes_dir() {

			$dir = $this->export_dir;
			$dir .= "episodes/";
			$dir .= $this->safe_filename_title($this->title)."/";

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
			$dir .= $this->safe_filename_title($this->title)."/";

			return $dir;

		}

		public function create_isos_dir() {

			$dir = $this->get_isos_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

	}
