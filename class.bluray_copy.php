<?php

	class BlurayCopy {

		public $debug = false;
		public $verbose = false;
		public $dry_run = false;

		// bluray_copy
		public $bluray_copy = 'bluray_copy';
		public $bluray_copy_opts = '';
		public $bluray_copy_output = '/dev/null';

		// Blu-ray source
		public $input_filename = '/dev/sr0';
		public $output_filename = 'bluray_copy.m2ts';

		// Video
		public $track = null;
		public $starting_chapter = null;
		public $ending_chapter = null;

		// Audio
		public $audio_tracks = array();

		// Subtitles
		public $pgs_tracks = array();

		public function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		public function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}

		public function set_binary($str) {
			$this->binary = $str;
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filename = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->track = $track;
		}

		public function add_audio_track($str) {
			$this->audio_tracks[] = abs(intval($str));
		}

		public function add_pgs_track($str) {
			$this->pgs_tracks[] = abs(intval($str));
		}

		public function set_dry_run($bool = true) {
			$this->dry_run = (boolean)$bool;
		}

		public function set_chapters($starting_chapter = 1, $ending_chapter = 1) {
			$this->starting_chapter = intval($starting_chapter);
			$this->ending_chapter = intval($ending_chapter);
		}

		public function get_bluray_copy_arguments() {

			$args = array();

			if(!is_null($this->track))
				$args['-p'] = intval($this->track);

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

			$args = $this->get_bluray_copy_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			$str = implode(" ", $cmd);

			$arg_output = escapeshellarg($this->output_filename);
			$str .= " -o - > $arg_output";

			return $str;

		}

	}
