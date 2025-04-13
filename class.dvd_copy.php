<?php

	class DVDCopy {

		public $debug = false;
		public $verbose = false;

		// dvd_copy
		public $dvd_copy = 'dvd_copy';
		public $dvd_copy_opts = '';
		public $dvd_copy_output = '/dev/null';

		// DVD source
		public $input_filename = '/dev/sr0';
		public $output_filename = 'dvd_copy.mpg';

		// Video
		public $track = null;
		public $starting_chapter = null;
		public $ending_chapter = null;

		// Audio
		public $audio = true;
		public $audio_tracks = array();
		public $audio_streams = array();

		// Subtitles
		public $subtitle_tracks = array();
		public $closed_captioning = false;

		public function debug($bool = true) {
			$this->debug = $this->verbose = boolval($bool);
		}

		public function verbose($bool = true) {
			$this->verbose = boolval($bool);
		}

		public function set_binary($str) {
			$this->binary = $str;
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filename = realpath($src);
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->track = $track;
		}

		public function set_chapters($starting_chapter = 1, $ending_chapter = 1) {
			$this->starting_chapter = intval($starting_chapter);
			$this->ending_chapter = intval($ending_chapter);
		}

		public function enable_audio() {
			$this->audio = true;
		}

		public function add_closed_captioning() {

			$this->closed_captioning = true;

		}

		public function get_dvd_copy_arguments() {

			$args = array();

			if(!is_null($this->track))
				$args['-t'] = intval($this->track);

			if($this->starting_chapter > 0) {
				$args['-c'] = intval($this->starting_chapter);
				if($this->ending_chapter > 0)
					$args['-c'] .= '-'.intval($this->ending_chapter);
			}

			return $args;

		}

		public function get_executable_string() {

			$cmd[] = $this->binary;
			$arg_input = escapeshellarg($this->input_filename);
			$cmd[] = $arg_input;

			$args = $this->get_dvd_copy_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			$str = implode(" ", $cmd);

			$arg_output = escapeshellarg($this->output_filename);
			$str .= " -o $arg_output";

			return $str;

		}

	}
