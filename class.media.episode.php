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

			$this->id = $episode_id;

		}

	}
