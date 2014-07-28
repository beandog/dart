<?php

	class MediaSeries {

		public $model;
		public $id;
		public $title;
		public $collection_id;
		public $production_year;
		public $production_studio;
		public $indexed;
		public $animation;
		public $grayscale;
		public $collection_title;

		public $export_dir;
		public $queue_dir;
		public $episodes_dir;
		public $isos_dir;

		public function __construct($series_id, $export_dir) {

			$this->id = $series_id;
			$this->export_dir = $export_dir;
			$this->model = new Series_Model($series_id);

			$this->title = $this->model->title;
			$this->collection_id = $this->model->collection_id;
			$this->production_year = $this->model->production_year;
			$this->production_studio = $this->model->production_studio;
			$this->indexed = $this->model->indexed;
			$this->animation = $this->model->animation;
			$this->grayscale = $this->model->grayscale;
			$this->collection_title = $this->model->get_collection_title();

			$this->queue_dir = $this->get_queue_dir();
			$this->episodes_dir = $this->get_episodes_dir();
			$this->isos_dir = $this->get_isos_dir();

			unset($this->model);

		}

		public function filename_title($str = 'Title', $underlines = true) {

			$str = preg_replace("/[^A-Za-z0-9 \-,.?':!_]/", '', $str);
			$underlines && $str = str_replace(' ', '_', $str);
			return $str;

		}

		public function safe_filename_title($str = 'Title', $underlines = true) {

			$str = preg_replace("/[^A-Za-z0-9 _]/", '', $str);
			$underlines && $str = str_replace(' ', '_', $str);
			return $str;

		}

		public function get_queue_dir() {

			$dir = $this->export_dir;
			$dir .= "queue/";
			$dir .= $this->safe_filename_title($this->title)."/";

			return $dir;

		}

		public function get_episodes_dir() {

			$dir = $this->export_dir;
			$dir .= "episodes/";
			$dir .= $this->safe_filename_title($this->title)."/";

			return $dir;

		}

		public function get_isos_dir() {

			$dir = $this->export_dir;
			$dir .= "isos/";
			$dir .= $this->safe_filename_title($this->collection_title)."/";
			$dir .= $this->safe_filename_title($this->title)."/";

			return $dir;

		}

	}
