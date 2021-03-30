<?php

	class Mkvmerge {

		public $debug = false;
		public $verbose = false;
		public $dry_run = false;

		// mkvmerge
		public $binary = 'mkvmerge';
		public $chapters = '';
		public $mkvmerge_opts = '';
		public $mkvmerge_output = '/dev/null';

		// mkvmerge source
		public $input_filename = '/dev/sr0';
		public $input_filenames = array();
		public $output_filename = 'media_track.mkv';

		// Video
		public $video_tracks = array();

		// Audio
		public $audio_tracks = array();

		// Subtitles
		public $subtitle_tracks = array();

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
			$this->input_filenames[] = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function add_input_filename($str) {
			$this->input_filenames[] = $str;
		}

		public function add_video_track($str) {
			$this->video_tracks[] = abs(intval($str));
		}

		public function add_audio_track($str) {
			$this->audio_tracks[] = abs(intval($str));
		}

		public function add_subtitle_track($str) {
			$this->subtitle_tracks[] = abs(intval($str));
		}

		public function add_chapters($str) {
			$this->chapters = $str;
		}

		public function set_dry_run($bool = true) {
			$this->dry_run = (boolean)$bool;
		}

		public function get_arguments() {

			$args = array();

			$args['-o'] = $this->output_filename;

			if(count($this->video_tracks))
				$args['--video-tracks'] = implode(',', $this->video_tracks);

			if(count($this->audio_tracks))
				$args['--audio-tracks'] = implode(',', $this->audio_tracks);

			if(count($this->subtitle_tracks))
				$args['--subtitle-tracks'] = implode(',', $this->subtitle_tracks);

			if(strlen($this->chapters))
				$args['--chapters'] = $this->chapters;

			$args['--default-language'] = "eng";

			return $args;

		}

		public function get_executable_string() {

			$cmd = array();

			$cmd[] = $this->binary;

			$args = $this->get_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			if(!count($this->subtitle_tracks))
				$cmd[] = "--no-subtitles";

			if($this->verbose)
				$cmd[] = "-v";

			foreach($this->input_filenames as $filename)
				$cmd[] = escapeshellarg($filename);

			$str = implode(" ", $cmd);

			return $str;

		}

	}
