<?php

	class MediaEpisode {

		public $model;
		public $id;
		public $title;
		public $track_id;
		public $ix;
		public $part;
		public $starting_chapter;
		public $ending_chapter;
		public $season;
		public $volume;
		public $display_name;
		public $number;

		public function __construct($episode_id) {

			$this->id = $episode_id;
			$this->model = new Episodes_Model($episode_id);

			$this->title = $this->model->title;
			$this->track_id = $this->model->track_id;
			$this->ix = $this->model->ix;
			$this->part = $this->model->part;
			$this->starting_chapter = $this->model->starting_chapter;
			$this->ending_chapter = $this->model->ending_chapter;
			$this->season = $this->model->get_season();
			$this->volume = $this->model->get_volume();
			$this->display_name = $this->model->get_display_name();
			$this->number = $this->model->get_number();
			$this->iso = $this->model->get_iso();
			$this->series_id = $this->model->get_series_id();


		}

	}
