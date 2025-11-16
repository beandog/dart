<?php

	class FFMpeg {

		public $debug = false;
		public $verbose = false;
		public $quiet = false;

		// ffmpeg
		public $encoder = 'ffmpeg';
		public $ffmpeg = false;
		public $ffpipe = false;
		public $ffprobe = false;
		public $ffplay = false;
		public $ffmpeg_opts = array();
		public $ffmpeg_args = array();
		public $metadata = array();
		public $input_opts = '';
		public $duration = 0;
		public $fullscreen = false;
		public $disc_type = 'dvd';
		public $genpts = false;
		public $overwrite = null;

		// DVD source
		public $input_filename = '';
		public $output_filename = '';
		public $dvd_track = 0;

		// Chapters
		public $start_chapter = 0;
		public $stop_chapter = 0;

		// Video
		public $container = '';
		public $vcodec = 'copy';
		public $vcodec_opts = '';
		public $video_filters = array();
		public $crf = 0;
		public $cq = 0;
		public $qmin = null;
		public $qmax = null;
		public $tune = '';
		public $preset = '';
		public $rc_lookahead = 0;

		// Audio
		public $acodec = 'copy';
		public $acodec_opts = '';
		public $audio_streams = array();

		// Subtitles
		public $subtitles = false;
		public $scodec = 'copy';
		public $subtitle_streams = array();
		public $remove_cc = false;
		public $ssa_filename = '';

		public function debug($bool = true) {
			$this->debug = $this->verbose = boolval($bool);
		}

		public function verbose($bool = true) {
			$this->verbose = boolval($bool);
		}

		public function quiet($bool = true) {
			$bool = boolval($bool);
			if($bool == false)
				$this->debug = $this->verbose = false;
			$this->quiet = $bool;
		}

		public function overwrite($bool) {
			$this->overwrite = boolval($bool);
		}

		public function set_encoder($str) {

			if($str == 'ffmpeg')
				$this->ffmpeg = true;
			elseif($str == 'ffpipe')
				$this->ffpipe = true;
			elseif($str == 'ffprobe')
				$this->ffprobe = true;
			elseif($str == 'ffplay')
				$this->ffplay = true;

			$this->encoder = $str;

		}

		public function add_metadata($key, $value) {
			$this->metadata[$key] = $value;
		}

		public function set_duration($int) {
			$this->duration = abs(intval($int));
		}

		public function fullscreen($bool = true) {
			$this->fullscreen = boolval($bool);
		}

		public function generate_pts($bool = true) {
			$this->genpts = true;
		}

		/** Filename **/
		public function input_filename($src) {
			if($src == '-')
				$this->input_filename = '-';
			else
				$this->input_filename = realpath($src);
		}

		// Don't use realpath here, because the file may be created right before this transcode
		public function add_ssa_filename($src) {
			$this->ssa_filename = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
			$this->container = pathinfo($str, PATHINFO_EXTENSION);
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

		public function enable_subtitles() {
			$this->subtitles = true;
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

		public function set_crf($int) {
			$this->crf = $int;
		}

		public function set_cq($int) {
			$this->cq = $int;
		}

		public function set_qmin($int) {
			$this->qmin = $int;
		}

		public function set_qmax($int) {
			$this->qmax = $int;
		}

		public function set_tune($str) {
			$this->tune = $str;
		}

		public function set_preset($str) {
			$this->preset = $str;
		}

		public function add_video_filter($str) {
			$this->video_filters[] = $str;
		}

		public function set_rc_lookahead($int) {
			$this->rc_lookahead = intval($int);
		}

		public function set_acodec($str) {
			if($str == 'aac')
				$str = 'libfdk_aac';
			$this->acodec = $str;
		}

		public function set_acodec_opts($str) {
			$this->acodec_opts = $str;
		}

		public function add_audio_stream($streamid = '0x80') {

			$streamid = trim($streamid);

			if($streamid)
				$this->audio_streams[] = $streamid;

		}

		public function add_subtitle_stream($streamid = '0x20') {

			$streamid = trim($streamid);

			if($streamid)
				$this->subtitle_streams[] = $streamid;

		}

		public function add_option($str) {
			$this->ffmpeg_opts[] = $str;
		}

		public function add_argument($key, $value) {
			$this->ffmpeg_args[$key] = $value;
		}

		public function cropdetect() {

			$arr[] = $this->encoder;
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

			if($this->debug)
				echo "* Executing $cmd\n";

			exec($cmd, $arr_output);

			$crop = current($arr_output);

			return $crop;

		}

		public function ffprobe() {

			$cmd[] = 'ffprobe';

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

			if($this->vcodec == 'x265')
				$this->vcodec = 'libx264';
			elseif($this->vcodec == 'x265')
				$this->vcodec = 'libx265';

			if($this->vcodec_opts)
				$args['vcodec_opts'] = $this->vcodec_opts;

			if($this->crf)
				$args['crf'] = $this->crf;

			if($this->tune)
				$args['tune'] = $this->tune;

			if($this->preset)
				$args['preset'] = $this->preset;

			if($this->acodec_opts)
				$args['acodec_opts'] = $this->acodec_opts;

			if($this->acodec == 'libfdk_aac')
				$args['vbr'] = 5;

			if(count($this->video_filters)) {
				$vf = implode(",", $this->video_filters);
				$args['vf'] = $vf;
			}

			if($this->cq) {
				$args['b:v'] = 0;
				$args['cq'] = $this->cq;
			}

			if($this->qmin)
				$args['qmin'] = $this->qmin;

			if($this->qmax)
				$args['qmax'] = $this->qmax;

			if($this->rc_lookahead)
				$args['rc-lookahead'] = $this->rc_lookahead;

			if($this->container == 'mp4')
				$args['movflags'] = '+faststart';

			if($this->duration)
				$args['t'] = $this->duration;

			return $args;

		}

		public function get_executable_string() {

			$encoder = $this->encoder;

			if($this->encoder == 'ffpipe')
				$encoder = 'ffmpeg';

			$cmd[] = $encoder;

			if($this->debug) {
				$cmd[] = "-report";
				$cmd[] = "-loglevel 'debug'";
			} elseif($this->verbose) {
				$cmd[] = "-report";
				$cmd[] = "-loglevel 'verbose'";
			} elseif($this->quiet) {
				$cmd[] = "-v 'quiet'";
				$cmd[] = '-stats';
			}

			if($this->genpts)
				$cmd[] = "-fflags +genpts";

			if($this->disc_type == 'dvd')
				$cmd[] = "-f 'dvdvideo'";

			if($this->input_opts)
				$cmd[] = $this->input_opts;

			if($this->dvd_track && $this->disc_type == 'dvd')
				$cmd[] = "-title '".$this->dvd_track."'";

			if($this->disc_type == 'bluray' && !$this->ffpipe)
				$cmd[] = "-playlist '".$this->dvd_track."'";

			if($this->start_chapter && $this->disc_type == 'dvd')
				$cmd[] = "-chapter_start '".$this->start_chapter."'";
			if($this->stop_chapter && $this->disc_type == 'dvd')
				$cmd[] = "-chapter_end '".$this->stop_chapter."'";

			if($this->start_chapter && $this->disc_type == 'bluray')
				$cmd[] = "-chapter '".$this->start_chapter."'";

			$arg_input = escapeshellarg($this->input_filename);

			if($this->disc_type == 'bluray' && !$this->ffpipe)
				$arg_input = "bluray:$arg_input";
			$cmd[] = "-i $arg_input";

			if($this->ssa_filename && $this->ffmpeg) {
				$arg_ssa_filename = escapeshellarg($this->ssa_filename);
				$cmd[] = "-i $arg_ssa_filename";
			}

			if(($this->disc_type == 'dvd'|| $this->disc_type == 'dvdcopy') && $this->ffmpeg) {

				$cmd[] = "-map 'v'";

				if(count($this->audio_streams) && $this->ffmpeg) {
					foreach($this->audio_streams as $streamid) {
						$cmd[] = "-map 'i:$streamid'";
					}
				}

				// This should probably be okay ........ just assume first is English
				if(count($this->subtitle_streams) && $this->ffmpeg) {
					$cmd[] = "-map 'i:0x20?'";
				}

			}

			if($this->disc_type == 'bluray' && ($this->ffmpeg || $this->ffpipe)) {

				$cmd[] = "-map 'v:0'";

				if(count($this->audio_streams)) {
					foreach($this->audio_streams as $streamid) {
						if(is_numeric($streamid[0]))
							$cmd[] = "-map 'i:$streamid'";
						else
							$cmd[] = "-map '$streamid'";
					}
				}

				if(count($this->subtitle_streams)) {
					foreach($this->subtitle_streams as $streamid) {
						$cmd[] = "-map 'i:$streamid'";
					}
				}

			}

			if(!$this->ffplay) {
				$cmd[] = "-vcodec '".$this->vcodec."'";
				$cmd[] = "-acodec '".$this->acodec."'";
				if($this->subtitles)
					$cmd[] = "-scodec '".$this->scodec."'";
			}

			if($this->subtitles && $this->ssa_filename && $this->ffmpeg)
				$cmd[] = "-map '1'";

			// Always set all audio *and* subtitle streams as English
			// Blu-ray audio streams probed with ffmpeg do not see language code, so this will fix that as well
			if(!$this->ffplay)
				$cmd[] = "-metadata:s 'language=eng'";

			$args = $this->get_ffmpeg_arguments();

			foreach($this->ffmpeg_opts as $str)
				$cmd[] = $str;

			foreach($this->ffmpeg_args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "-$key $arg_value";
			}

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

			foreach($this->metadata as $key => $value)
				$cmd[] = "-metadata '$key=$value'";

			$str = implode(" ", $cmd);

			if($this->output_filename) {
				$arg_output = escapeshellarg($this->output_filename);
				if($this->overwrite === true)
					$str .= " -y $arg_output";
				elseif($this->overwrite === false)
					$str .= " -n $arg_output";
				else
					$str .= " $arg_output";
			}

			return $str;

		}

	}
