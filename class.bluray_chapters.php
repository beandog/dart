<?php

	class BlurayChapters {

		// bluray_info
		public $binary = 'bluray_info';

		// Blu-ray source
		public $track;
		public $input_filename = '/dev/sr0';
		public $output_filename = 'bluray_chapters.txt';

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

		public function set_chapters($starting_chapter = 1, $ending_chapter = 1) {
			$this->starting_chapter = intval($starting_chapter);
			$this->ending_chapter = intval($ending_chapter);
		}

		public function get_arguments() {

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
			$cmd[] = escapeshellarg($this->input_filename);

			$args = $this->get_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			$cmd[] = "-d";

			$cmd[] = "--xchap";

			$cmd[] = "> ".escapeshellarg($this->output_filename);

			$str = implode(" ", $cmd);

			return $str;

		}

	}
