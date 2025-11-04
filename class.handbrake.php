<?php

	class HandBrake {

		// Handbrake
		public $verbose = false;
		public $debug = false;
		public $dvdnav = true;
		public $preset;
		public $track;
		public $flags = array();
		public $args = array();
		public $scan_complete = false;
		public $do_not_scan = false;
		public $output;
		public $duration = 0;
		public $disc_type = 'dvd';

		// DVD source
		public $dvd;
		public $dvd_num_audio_tracks;
		public $dvd_num_subtitles;

		// Video
		public $container = '';
		public $vcodec;
		public $video_quality;
		public $video_framerate;
		public $video_format = 'ntsc';
		public $video_filter;
		public $max_height;
		public $max_width;
		public $height;
		public $width;
		public $x264_preset;
		public $x264_tune;
		public $x264 = array();
		public $crop;

		// Audio
		public $audio = true;
		public $acodecs = array();
		public $audio_tracks = array();
		public $audio_streams = array();
		public $audio_bitrate;
		public $audio_vbr;

		// Container
		public $add_chapters;
		public $starting_chapter;
		public $ending_chapter;

		// Subtitles
		public $subtitles = false;
		public $subtitle_tracks = array();
		public $closed_captioning = false;
		public $closed_captioning_ix = null;

		function debug($bool = true) {
			$this->debug = $this->verbose = boolval($bool);
		}

		function verbose($bool = true) {
			$this->verbose = boolval($bool);
		}

		public function set_disc_type($str) {
			$this->disc_type = $str;
		}

		public function set_duration($int) {
			$int = abs(intval($int));
			if($int)
				$this->duration = $int;
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input = $src;
		}

		public function output_filename($str) {
			$this->output = $str;
			$this->container = pathinfo($str, PATHINFO_EXTENSION);
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->track = $track;
		}

		public function add_chapters($bool = true) {
			$this->add_chapters = boolval($bool);
		}

		public function set_audio_bitrate($str) {
			$this->audio_bitrate = $str;
		}

		public function set_audio_vbr($int) {
			$this->audio_vbr = abs(intval($int));
		}

		public function set_vcodec($str) {
			$this->vcodec = $str;
		}

		public function set_video_quality($int) {
			$int = abs(intval($int));
			$this->video_quality = $int;
		}

		public function set_video_framerate($float) {
			$this->video_framerate = $float;
		}

		public function add_audio_track($int) {
			$int = abs(intval($int));
			if($int)
				$this->audio_tracks[] = $int;
		}

		public function enable_subtitles() {
			$this->subtitles = true;
		}

		/**
		 * Add an audio stream id to be encoded.
		 *
		 * @param string audio track stream id
		 * @return boolean success
		 */
		public function add_audio_stream($stream_id) {

			if(!$this->scan_complete)
				$this->scan();

			if($this->dvd_has_audio_stream_id($stream_id)) {

				// 0x80 = 128
				$audio_stream_idx = $stream_id - 128;

				// Check to make sure the stream exists
				if(!array_key_exists($audio_stream_idx, $this->dvd['streams']['audio']))
					return false;

				$audio_track = $this->dvd['streams']['audio'][$audio_stream_idx]['track'];
				$this->add_audio_track($audio_track);
				return true;

			}

			return false;

		}

		// FIXME limit to set audio encoders
		public function add_acodec($str) {
			if($str == 'flac')
				$str = 'flac24';
			if(!is_null($str))
				$this->acodecs[] = $str;
		}

		public function enable_audio($bool = true) {
			$this->audio = boolval($bool);
		}

		public function dvdnav($bool = true) {
			$this->dvdnav = boolval($bool);
		}

		public function set_max_height($int) {

			$int = abs(intval($int));
			if($int)
				$this->max_height = $int;

		}

		public function set_max_width($int) {

			$int = abs(intval($int));
			if($int)
				$this->max_width = $int;

		}

		public function set_height($int) {
			$this->height = abs(intval($int));
		}

		public function set_width($int) {
			$this->width = abs(intval($int));
		}

		public function set_x264_preset($str) {
			$this->x264_preset = $str;
		}

		public function set_x264_tune($str) {
			$this->x264_tune = $str;
		}

		public function set_crop($str) {
			$this->crop = $str;
		}

		public function set_preset($preset) {
			$this->preset = $preset;
		}

		public function set_video_format($str) {
			$this->video_format = strtolower($str);
		}

		public function set_video_filter($str) {
			$this->video_filter = $str;
		}

		public function set_x264($key, $value) {
			if(is_null($value) && array_key_exists($key, $this->x264))
				unset($this->x264[$key]);
			elseif(!is_null($value))
				$this->x264[$key] = $value;
		}

		public function add_subtitle_track($int) {
			$int = abs(intval($int));
			$this->subtitle_tracks[] = $int;
		}

		/**
		 * Check to see if the closed captioning exists, and if so
		 * go ahead and add it to the list of subtitles to include.
		 *
		 * @param boolean added
		 */
		public function add_closed_captioning() {

			if(!$this->scan_complete)
				$this->scan();

			if($this->closed_captioning) {
				$this->add_subtitle_track($this->closed_captioning_ix);
				return true;
			} else
				return false;

		}

		/**
		 * Add a subtitle stream id
		 *
		 * @param string subtitle track stream id
		 * @return boolean success
		 */
		public function add_subtitle_stream($stream_id) {

			if(!$this->scan_complete)
				$this->scan();

			if($this->dvd_has_subtitle_stream_id($stream_id)) {

				// 0x20 = 32
				$subtitle_stream_idx = $stream_id - 32;

				// Check to make sure the stream exists
				if(!array_key_exists($subtitle_stream_idx, $this->dvd['streams']['subtitle']))
					return false;

				$subtitle_track = $this->dvd['streams']['subtitle'][$subtitle_stream_idx]['track'];
				$this->add_subtitle_track($subtitle_track);
				return true;

			}

			return false;

		}

		public function get_options() {

			$options = array();

			// Check for muxing chapters
			if($this->add_chapters)
				$options[] = "--markers";

			// Check for no-dvdnav
			if(!$this->dvdnav)
				$options[] = "--no-dvdnav";

			// If audio is enabled and no tracks have been specifically selected,
			// then choose the first English one for DVD. For Blu-ray, there can bee
			// all kinds of channel numbers, just grab them all and select in player.
			if($this->audio && !count($this->audio_tracks) && $this->disc_type == 'dvd')
				$options[] = "--first-audio";
			if($this->audio && !count($this->audio_tracks) && $this->disc_type == 'bluray')
				$options[] = "--all-audio";

			// Set constant framerate
			$options[] = '--cfr';

			// MP4
			if($this->container == 'mp4')
				$options[] = '--optimize';

			return $options;

		}

		public function get_arguments() {

			$args = array();

			/**
			 * Handbrake
			 **/

			// Add preset
			if($this->preset)
				$args['--encoder-preset'] = $this->preset;

			// Set track #
			if($this->track)
				$args['--title'] = $this->track;

			/**
			 * Video
			 **/

			// Set encoder
			$args['--encoder'] = $this->vcodec;

			// Add video quality
			if(!is_null($this->video_quality)) {
				$args['--quality'] = $this->video_quality;
			}

			// Set max and width and height
			if(!is_null($this->max_width)) {
				$args['--maxWidth'] = $this->max_width;
			}
			if(!is_null($this->max_height)) {
				$args['--maxHeight'] = $this->max_height;
			}

			// Set custom width and height
			if(!is_null($this->width)) {
				$args['--width'] = $this->width;
			}
			if(!is_null($this->height)) {
				$args['--height'] = $this->height;
			}

			if($this->video_framerate)
				$args['--rate'] = $this->video_framerate;

			// Set x264 preset
			if($this->x264_preset) {
				$args['--encoder-preset'] = $this->x264_preset;
			}

			// Set x264 tune option
			if($this->x264_tune) {
				$args['--encoder-tune'] = $this->x264_tune;
			}

			// Set cropping
			if($this->crop) {
				$args['--crop'] = $this->crop;
			}

			// Set duration for QA
			if($this->duration) {
				$args['--stop-at'] = "duration:".$this->duration;
			}

			/**
			 * Audio
			 **/

			// Add audio tracks
			if($this->audio) {

				// Select audio streams to encode, default to English if no
				// streams are specified.
				if(count($this->audio_tracks)) {
					$str = implode(",", $this->audio_tracks);
					$args['--audio'] = $str;
				}

				// Add audio encoders
				if(count($this->acodecs)) {
					$str = implode(",", $this->acodecs);
					$args['--aencoder'] = $str;
				}

				if($this->audio_bitrate) {
					$args['--ab'] = $this->audio_bitrate;
				}

				if($this->audio_vbr) {
					$args['--aq'] = $this->audio_vbr;
				}

			}

			/** Subtitles **/

			// Add subtitle tracks
			if(count($this->subtitle_tracks) && $this->subtitles) {
				$str = implode(",", $this->subtitle_tracks);
				$args['--subtitle'] = $str;
			}

			if(!$this->subtitles) {
				$args['--subtitle'] = 'none';
			}

			/**
			 * Container
			 */

			// Add chapters
			if(!empty($this->starting_chapter)) {
				$args['--chapters'] = $this->starting_chapter."-";
			}
			if(!empty($this->ending_chapter)) {
			 	$args['--chapters'] .= $this->ending_chapter;
			}

			return $args;

		}

		public function get_executable_string() {

			$cmd = array();

			$args = $this->get_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			// Deinterlace video
			if($this->video_filter) {

				if($this->video_filter == 'bwdif') {
					$cmd[] = "'--bwdif=bob'";
				} else {

					$cmd[] = "'--comb-detect=permissive'";

					if($this->video_filter == 'bob')
						$cmd[] = "'--decomb=bob'";
					elseif($this->video_filter == 'eedi2')
						$cmd[] = "'--decomb=eedi2'";
					elseif($this->video_filter == 'eedi2bob')
						$cmd[] = "'--decomb=eedi2bob'";

				}

			}

			$options = $this->get_options();

			foreach($options as $str)
				$cmd[] = escapeshellarg($str);

			$str = "HandBrakeCLI ".implode(" ", $cmd);

			$arg_input = escapeshellarg($this->input);
			$arg_output = escapeshellarg($this->output);
			$str .= " --input $arg_input";
			$str .= " --output $arg_output";

			return $str;

		}

		/**
		 * There are cases where HandBrakeCLI will fail on scanning a title for
		 * some reason or another. This uses proc_open() to cleanly run the
		 * process, monitor it for timing out, and kill it if necessary.
		 *
		 * Default to 10 seconds, which is a generous amount of time.
		 *
		 */
		public function scan($max_wait_time = 10) {

			$options = '';

			$this->dvd['streams']['audio'] = array();
			$this->dvd['streams']['subtitle'] = array();

			$stream_ix['audio'] = 1;
			$stream_ix['subtitle'] = 1;

			$this->dvd_num_audio_tracks = count($this->dvd['streams']['audio']);
			$this->dvd_num_subtitles = count($this->dvd['streams']['subtitle']);

			if($this->track)
				$options = "--title ".$this->track;

			$arg_input = escapeshellarg($this->input);
			$cmd = "HandBrakeCLI --scan --verbose $options --input $arg_input 2>&1";

			$output_file = tempnam(sys_get_temp_dir(), "handbrake-scan");

			$descriptor = array(
				0 => array('pipe', 'r'),
				1 => array('file', $output_file, 'w'),
				2 => array('file', '/dev/null', 'a')
			);

			if($this->debug) {
				echo "* Executing: $cmd\n";
				echo "* Saving output to $output_file\n";
			}

			$resource = proc_open($cmd, $descriptor, $pipes);

			$wait_time = 0;

			while($wait_time < $max_wait_time + 1) {

				$proc_status = proc_get_status($resource);

				if($proc_status['running'] === false) {
					break;
				}

				sleep(1);
				$wait_time++;

			}

			$proc_status = proc_get_status($resource);

			if($proc_status['running']) {

				$killed = posix_kill($proc_status['pid'] + 1, SIGKILL);
				if(!$killed) {
					echo "\n!!! Could not kill HandBrakeCLI, please manually kill PID ".$proc_status['pid']."\n";
				}

				$this->do_not_scan = true;
				proc_close($resource);

				return false;

			}

			$arr = file($output_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if(!$this->debug)
				unlink($output_file);

			// Strip out library messages
			$pattern = "/^(libdvdread|libdvdnav|libbluray)/";
			$arr = preg_grep($pattern, $arr, PREG_GREP_INVERT);

			// Strip out "[00:11:22] scan: " strings
			$arr = preg_replace("/\[\d{2}:\d{2}:\d{2}\] scan: /", "", $arr);

			// Find all the lines that list the audio and subtitle streams
			$arr_scan_streams = array_merge(preg_grep("/^id=0/", $arr));

			foreach($arr_scan_streams as $scan_stream) {

				$tmp = explode(" ", $scan_stream);
				$stream_type = $tmp[1];

				if(substr($scan_stream, 0, 6) == 'id=0x8')
					$stream_type = 'audio';
				elseif(substr($scan_stream, 0, 6) == 'id=0x2' || substr($scan_stream, 0, 6) == 'id=0x3')
					$stream_type = 'subtitle';
				else
					break;

				$stream_data = explode(', ', $scan_stream);
				$stream_id = substr($stream_data[0], 3);
				$stream_lang = substr($stream_data[1], 5);

				$this->dvd['streams'][$stream_type][] = array(
					'track' => $stream_ix[$stream_type],
					'id' => $stream_id,
					'lang' => $stream_lang,
				);

				$stream_ix[$stream_type]++;

			}

			// Sample source strings:
			// Closed Captions (iso639-2: eng) (Text)(CC)
			// Closed Caption [CC608]
			// Avoid false positives: "English (Closed Caption) (iso639-2: eng) (Bitmap)(VOBSUB)"
			// I have seen *one* DVD where the language is und for the CC, the rest
			// all being eng. I'm not going to scan for language. :)

			// In 1.9.2 there are these two possibilities:
			// English Closed Caption (4:3) [VOBSUB]
			// English, Closed Caption [CC608]

			$closed_captioning = preg_grep("/.*Closed Caption(\s.*CC608|s?.*Text.*CC*)/", $arr);

			if(count($closed_captioning)) {
				$this->closed_captioning = true;
				$this->closed_captioning_ix = $this->dvd_num_subtitles + 1;
			}

			$this->scan_complete = true;

			return true;

		}

		/**
		 * Scan all the audio streams to see if it has one matching the
		 * argument.
		 *
		 * Example stream id: '0x80' for first audio track stream id
		 *
		 * @param string DVD stream id
		 * @return boolean
		 */
		public function dvd_has_audio_stream_id($dvd_stream_id) {

			if(!$this->scan_complete)
				$this->scan();

			foreach($this->dvd['streams']['audio'] as $arr) {

				$hb_stream_id = substr($arr['id'], 0, 4);;

				if($hb_stream_id == $dvd_stream_id)
					return true;

			}

			return false;

		}

		/**
		 * Scan all the subtitles to see if it has one matching the
		 * argument.
		 *
		 * Example stream id: '0x20' for first subp stream id
		 *
		 * @param string DVD stream id
		 * @return boolean
		 */
		public function dvd_has_subtitle_stream_id($dvd_stream_id) {

			if(!$this->scan_complete)
				$this->scan();

			foreach($this->dvd['streams']['subtitle'] as $arr) {

				$hb_stream_id = substr($arr['id'], 0, 4);;

				if($hb_stream_id == $dvd_stream_id)
					return true;

			}

			return false;

		}

		public function set_chapters($a, $b) {

			$this->starting_chapter = $a;
			$this->ending_chapter = $b;

		}

		public function encode() {

			$str = $this->get_executable_string();

			if($this->debug)
				echo "Executing: ".escapeshellcmd($str)."\n";

			$return_var = null;

			if($this->debug) {
				$cmd = escapeshellcmd($str);
				passthru($cmd, $return_var);
			} else {
				$cmd = escapeshellcmd($str)." 2> /dev/null";
				passthru($cmd, $return_var);
			}

			return $return_var;

		}

	}
?>
