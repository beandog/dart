<?php

	class FFMpeg {

		public $debug = false;
		public $verbose = false;

		// ffmpeg
		public $binary = 'ffmpeg';
		public $ffmpeg_output = '/dev/null';
		public $ffmpeg_opts = '';
		public $input_opts = '';
		public $disable_stats = false;
		public $duration = 0;
		public $fullscreen = false;
		public $disc_type = 'dvd';
		public $genpts = false;

		// DVD source
		public $input_filenames = array();
		public $output_filename = '';
		public $dvd_track = 0;

		// Chapters
		public $start_chapter = 0;
		public $stop_chapter = 0;

		// Video
		public $vcodec = '';
		public $vcodec_opts = '';
		public $video_filters = array();
		public $crf = 0;
		public $tune = '';

		// Audio
		public $audio = true;
		public $acodec = '';
		public $acodec_opts = '';
		public $audio_streams = array();

		// Subtitles
		public $subtitle_streams = array();
		public $remove_cc = false;

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

		public function fullscreen($bool = true) {
			$this->fullscreen = (boolean)$bool;
		}

		public function generate_pts($bool = true) {
			$this->genpts = true;
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filenames[] = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function set_disc_type($str) {
			$this->disc_type = $str;
		}

		public function input_opts($str) {
			$this->input_opts = $str;
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->dvd_track = $track;
		}

		// Closed captioning streams in ffmpeg are always garbled, so allow removing them
		// https://trac.ffmpeg.org/wiki/HowToExtractAndRemoveClosedCaptions
		public function remove_closed_captioning() {
			$this->remove_cc = true;
		}

		public function set_chapters($start, $stop) {
			$start = abs(intval($start));
			$stop = abs(intval($stop));
			if($start)
				$this->start_chapter = $start;
			if($stop)
				$this->stop_chapter = $stop;
		}

		public function set_vcodec($str) {
			$this->vcodec = $str;
		}

		public function set_vcodec_opts($str) {
			$this->vcodec_opts = $str;
		}

		public function set_crf($str) {
			$crf = abs(intval($str));
			if($crf)
				$this->crf = $crf;
		}

		public function set_tune($str) {
			$this->tune = $str;
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

		public function cropdetect() {

			$arr[]  = $this->binary;
			$arr[] = "-f 'dvdvideo'";

			if($this->dvd_track)
				$arr[] = "-title '".$this->dvd_track."'";
			if($this->start_chapter)
				$arr[] = "-chapter_start '".$this->start_chapter."'";
			if($this->stop_chapter)
				$arr[] = "-chapter_end '".$this->stop_chapter."'";

			$arg_input = escapeshellarg($this->input_filename);
			$arr[] = "-i $arg_input -vf 'cropdetect=round=1' -t '15' -f null - 2>&1 | grep cropdetect | tail -n 1 | grep -o '[^=]*$'";

			$cmd  = implode(' ', $arr);

			exec($cmd, $arr_output);

			$crop = current($arr_output);

			return $crop;

		}

		public function ffprobe() {

			$cmd[] = $this->binary;

			if($this->debug)
				$cmd[] = "-loglevel 'debug'";
			elseif($this->verbose)
				$cmd[] = "-loglevel 'verbose'";

			if($this->disc_type == 'dvd')
				$cmd[] = "-f 'dvdvideo'";

			if($this->dvd_track && $this->disc_type == 'dvd')
				$cmd[] = "-title '".$this->dvd_track."'";
			if($this->disc_type == 'bluray')
				$cmd[] = "-playlist '".$this->dvd_track."'";
			if($this->start_chapter && $this->disc_type == 'dvd')
				$cmd[] = "-chapter_start '".$this->start_chapter."'";
			if($this->start_chapter && $this->disc_type == 'bluray')
				$cmd[] = "-chapter '".$this->start_chapter."'";

			$arg_input = escapeshellarg($this->input_filename);
			if($this->disc_type == 'bluray')
				$arg_input = "bluray:$arg_input";
			$cmd[] = "-i $arg_input";

			$str = implode(' ', $cmd);

			return $str;

		}

		public function get_ffmpeg_arguments() {

			$args = array();

			if($this->vcodec)
				$args['vcodec'] = $this->vcodec;
			if($this->vcodec_opts)
				$args['vcodec_opts'] = $this->vcodec_opts;

			if($this->crf)
				$args['crf'] = $this->crf;

			if($this->tune)
				$args['tune'] = $this->tune;

			if($this->acodec)
				$args['acodec'] = $this->acodec;
			if($this->acodec_opts)
				$args['acodec_opts'] = $this->acodec_opts;

			if(count($this->video_filters)) {
				$vf = implode(",", $this->video_filters);
				$args['vf'] = $vf;
			}

			if($this->duration)
				$args['t'] = $this->duration;

			return $args;

		}

		public function get_executable_string() {

			$cmd[] = $this->binary;

			if($this->debug) {
				$cmd[] = "-report";
				$cmd[] = "-loglevel 'debug'";
			} elseif($this->verbose) {
				$cmd[] = "-report";
				$cmd[] = "-loglevel 'verbose'";
			}

			if($this->genpts)
				$cmd[] = "-fflags +genpts";

			if($this->disc_type == 'dvd')
				$cmd[] = "-f 'dvdvideo'";

			if($this->input_opts)
				$cmd[] = $this->input_opts;

			if($this->dvd_track && $this->disc_type == 'dvd')
				$cmd[] = "-title '".$this->dvd_track."'";

			if($this->disc_type == 'bluray')
				$cmd[] = "-playlist '".$this->dvd_track."'";

			if($this->start_chapter && $this->disc_type == 'dvd')
				$cmd[] = "-chapter_start '".$this->start_chapter."'";
			if($this->stop_chapter && $this->disc_type == 'dvd')
				$cmd[] = "-chapter_end '".$this->stop_chapter."'";

			if($this->start_chapter && $this->disc_type == 'bluray')
				$cmd[] = "-chapter '".$this->start_chapter."'";

			$input_filename = array_shift($this->input_filenames);
			$arg_input = escapeshellarg($input_filename);
			if($this->disc_type == 'bluray')
				$arg_input = "bluray:$arg_input";
			$cmd[] = "-i $arg_input";

			$maps = count($this->input_filenames);
			foreach($this->input_filenames as $input_filename) {
				$arg_input = escapeshellarg($input_filename);
				$cmd[] = "-i $arg_input";
			}

			if(($this->disc_type == 'dvd'|| $this->disc_type = 'dvdcopy')  && $this->binary == 'ffmpeg') {

				$cmd[] = "-map 'v'";

				if(count($this->audio_streams) && $this->binary == 'ffmpeg') {
					foreach($this->audio_streams as $streamid) {
						$cmd[] = "-map 'i:$streamid'";
					}
				}

				// This should probably be okay ........ just assume first is English
				if(count($this->subtitle_streams) && $this->binary == 'ffmpeg') {
					$cmd[] = "-map 'i:0x20?'";
					$cmd[] = "-scodec 'copy'";
				}

			}

			if($this->disc_type == 'bluray' && $this->binary == 'ffmpeg') {

				$cmd[] = "-map 'v:0'";
				$cmd[] = "-map 'i:0x1100'";
				$cmd[] = "-map 'i:0x1200?'";

				$cmd[] = "-codec 'copy'";

			}

			for($map = 0; $map < $maps; $map++)
				$cmd[] = "-map '". ($map + 1)."'";

			if(count($this->subtitle_streams) || count($this->input_filenames))
				$cmd[] = "-metadata:s:s 'language=eng'";

			$args = $this->get_ffmpeg_arguments();

			if($this->ffmpeg_opts)
				$cmd[] = $this->ffmpeg_opts;

			if($this->output_filename == "-")
				$cmd[] = "-f 'null'";

			if($this->fullscreen)
				$cmd[] ="-fs";

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "-$key $arg_value";
			}

			if($this->remove_cc)
				$cmd[] = "-bsf:v 'filter_units=remove_types=6'";

			$str = implode(" ", $cmd);

			if($this->output_filename) {
				$arg_output = escapeshellarg($this->output_filename);
				$str .= " -y $arg_output";
			}

			return $str;

		}

	}
