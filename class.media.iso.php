<?php

	require_once 'class.media.file.php';

	/**
	 * Class to track the filesystem location of the original ISO
	 */
	class MediaISO extends MediaFile {

		public $export_dir;
		public $dvd_iso;
		public $collection_title;
		public $series_title;
		public $isos_dir;

		public function __construct($export_dir, $dvd_iso, $collection_title = '', $series_title = '') {

			$this->export_dir = realpath($export_dir)."/";
			$this->dvd_iso = basename($dvd_iso);
			$this->collection_title = $collection_title;
			$this->series_title = $series_title;
			$this->isos_dir = $this->get_isos_dir();

		}

		public function get_isos_dir() {

			$dir = $this->export_dir;
			$dir .= "isos/";
			if($this->collection_title)
				$dir .= $this->safe_filename_title($this->collection_title)."/";
			if($this->series_title)
				$dir .= $this->safe_filename_title($this->series_title)."/";

			return $dir;

		}

		public function create_isos_dir() {

			clearstatcache();

			$dir = $this->get_isos_dir();

			if(!is_dir($dir))
				return mkdir($dir, 0755, true);
			else
				return true;

		}

	}
