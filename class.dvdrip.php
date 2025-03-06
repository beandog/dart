<?php

	class DVDRip {

		public $debug = false;
		public $verbose = false;

		// dvd_rip
		public $dvd_rip = 'dvd_rip';
		public $duration = 0;

		// DVD source
		public $input_filename = '';
		public $output_filename = 'dvd_rip.mkv';

		// Video
		public $vcodec = '';
		public $crf = '';

		// Audio
		public $acodec = '';
		public $audio_lang = '';

		// Subtitles
		public $subtitle_lang = '';

		// Chapters
		public $chapters = '';

		public function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		public function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filename = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		/*
		public function input_opts($str) {
			$this->input_opts = $str;
		}
		*/

		/** DVD **/

		public function input_track($str) {
			$this->track = abs(intval($str));
		}

		public function set_chapters($start, $stop) {
			$this->chapters = "$start-$stop";
		}

		public function set_duration($str) {
			$this->duration = abs(intval($str));
		}

		public function set_vcodec($str) {
			$this->vcodec = $str;
		}

		public function set_video_quality($str) {
			$this->crf = abs(intval($str));
		}

		/*
		public function set_vcodec_opts($str) {
			$this->vcodec_opts = $str;
		}
		*/

		/*
		public function add_video_filter($str) {
			$this->video_filters[] = $str;
		}
		*/

		public function set_acodec($str) {
			$this->acodec = $str;
		}

		/*
		public function set_acodec_opts($str) {
			$this->acodec_opts = $str;
		}
		*/

		public function set_audio_lang($str = 'en') {
			$this->audio_lang = $str;
		}

		public function set_subtitle_lang($str = 'en') {
			$this->subtitle_lang = $str;
		}

		/*
		public function add_opts($str) {
			$this->dvd_rip_opts = $str;
		}
		*/

		public function get_dvd_rip_arguments() {

			$args = array();

			if($this->track)
				$args['track'] = $this->track;

			if($this->vcodec)
				$args['vcodec'] = $this->vcodec;

			if($this->acodec)
				$args['acodec'] = $this->acodec;

			if($this->chapters)
				$args['chapter'] = $this->chapters;

			if($this->crf)
				$args['crf'] = $this->crf;

			$args['alang'] = 'en';
			$args['slang'] = 'en';

			if($this->duration)
				$args['stop'] = $this->duration;

			return $args;

		}

		public function get_executable_string() {

			$cmd[] = "dvd_rip";

			if($this->verbose && !$this->debug)
				$cmd[] = "--verbose";
			if($this->debug)
				$cmd[] = "--debug";

			/*
			if($this->input_opts)
				$cmd[] = $this->input_opts;
			*/
			$arg_input = escapeshellarg($this->input_filename);
			$cmd[] = "$arg_input";

			$args = $this->get_dvd_rip_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "--$key $arg_value";
			}

			/*
			if($this->dvd_rip_opts)
				$cmd[] = $this->dvd_rip_opts;
			*/

			$str = implode(" ", $cmd);

			$arg_output = escapeshellarg($this->output_filename);
			$str .= " -o $arg_output";

			return $str;

		}

	}
