<?php

	class FFMpeg {

		public $debug = false;
		public $verbose = false;
		public $dry_run = false;

		// ffmpeg
		public $ffmpeg = 'ffmpeg';
		public $ffmpeg_output = '/dev/null';
		public $ffmpeg_opts = '';
		public $input_opts = '';
		public $disable_stats = false;
		public $duration = 0;

		// DVD source
		public $input_filename = '-';
		public $output_filename = 'ffmpeg.mkv';

		// Video
		public $vcodec = '';
		public $vcodec_opts = '';
		public $video_filters = array();

		// Audio
		public $audio = true;
		public $acodec = '';
		public $acodec_opts = '';
		public $audio_streams = array();

		// Subtitles
		public $subtitle_streams = array();

		public function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		public function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}

		public function set_binary($str) {
			$this->binary = $str;
		}

		public function set_duration($int) {
			$this->duration = abs(intval($int));
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filename = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function input_opts($str) {
			$this->input_opts = $str;
		}

		public function set_dry_run($bool = true) {
			$this->dry_run = (boolean)$bool;
		}

		public function set_vcodec($str) {
			$this->vcodec = $str;
		}

		public function set_vcodec_opts($str) {
			$this->vcodec_opts = $str;
		}

		public function add_video_filter($str) {
			$this->video_filters[] = $str;
		}

		public function set_acodec($str) {
			$this->acodec = $str;
		}

		public function set_acodec_opts($str) {
			$this->acodec_opts = $str;
		}

		public function disable_stats() {
			$this->disable_stats = true;
		}

		public function add_audio_stream($streamid = '0x80') {

			$streamid = trim($streamid);

			if($streamid) {
				$this->audio_streams[] = $streamid;
				$this->set_acodec('copy');
			}

		}

		public function add_subtitle_stream($streamid = '0x20') {

			$streamid = trim($streamid);

			if($streamid)
				$this->subtitle_streams[] = $streamid;

		}

		public function add_opts($str) {
			$this->ffmpeg_opts = $str;
		}

		public function get_ffmpeg_arguments() {

			$args = array();

			if($this->vcodec)
				$args['vcodec'] = $this->vcodec;
			if($this->vcodec_opts)
				$args['vcodec_opts'] = $this->vcodec_opts;
			if(count($this->video_filters)) {
				$vf = implode(",", $this->video_filters);
				$args['vf'] = $vf;
			}

			if($this->acodec)
				$args['acodec'] = $this->acodec;
			if($this->acodec_opts)
				$args['acodec_opts'] = $this->acodec_opts;

			if($this->duration)
				$args['t'] = $this->duration;

			return $args;

		}

		public function get_executable_string() {

			$cmd[] = $this->binary;
			$cmd[] = "-fflags '+genpts'";
			$cmd[] = "-hide_banner";
			if($this->disable_stats)
				$cmd[] = "-nostats";
			if($this->verbose)
				$cmd[] = "-report";
			if($this->input_opts)
				$cmd[] = $this->input_opts;
			$arg_input = escapeshellarg($this->input_filename);
			$cmd[] = "-i $arg_input";
			$cmd[] = "-map '0:v'";

			if(count($this->audio_streams)) {
				foreach($this->audio_streams as $streamid) {
					$cmd[] = "-map 'i:$streamid'";
				}
			}

			if(count($this->subtitle_streams)) {
				$cmd[] = "-scodec 'copy'";
				foreach($this->subtitle_streams as $streamid) {
					$cmd[] = "-map 'i:$streamid?'";
				}
			}

			$args = $this->get_ffmpeg_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "-$key $arg_value";
			}

			if($this->ffmpeg_opts)
				$cmd[] = $this->ffmpeg_opts;

			if($this->output_filename == "-")
				$cmd[] = "-f 'null'";

			$str = implode(" ", $cmd);

			$arg_output = escapeshellarg($this->output_filename);
			$str .= " -y $arg_output";

			return $str;

		}

	}
