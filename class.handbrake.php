<?php

	class Handbrake {

		// Handbrake
		public $binary = "HandBrakeCLI";
		public $verbose = false;
		public $debug = false;
		public $dvdnav = true;
		public $preset;
		public $track;
		public $http_optimize;
		public $flags = array();
		public $args = array();
		public $scan_complete = false;
		public $do_not_scan = false;
		public $dry_run = false;
		public $output;
		public $duration = 0;

		// DVD source
		public $dvd;
		public $dvd_num_audio_tracks;
		public $dvd_num_subtitles;

		// Video
		public $video_encoder;
		public $video_quality;
		public $video_framerate;
		public $deinterlace;
		public $decomb;
		public $comb_detect;
		public $detelecine;
		public $grayscale;
		public $max_height;
		public $max_width;
		public $height;
		public $width;
		public $auto_anamorphic;
		public $h264_profile;
		public $h264_level;
		public $x264_preset;
		public $x264_tune;
		public $x264 = array();
		public $color_matrix;

		// Audio
		public $audio = true;
		public $audio_encoders = array();
		public $audio_tracks = array();
		public $audio_streams = array();
		public $audio_bitrate;
		public $audio_mixdown;
		public $audio_fallback;

		// Container
		public $add_chapters;
		public $format;
		public $starting_chapter;
		public $ending_chapter;

		// Subtitles
		public $subtitle_tracks = array();
		public $srt_language = 'eng';
		public $closed_captioning = false;
		public $closed_captioning_ix = null;

		function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}

		public function set_binary($str) {
			$this->binary = $str;
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
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->track = $track;
		}

		public function set_dry_run($bool = true) {
			$this->dry_run = (boolean)$bool;
		}

		public function add_chapters($bool = true) {
			$this->add_chapters = (boolean)$bool;
		}

		public function set_audio_bitrate($int) {
			$int = abs(intval($int));
			if($int)
				$this->audio_bitrate = $int;
		}

		public function set_audio_downmix($str) {

			$str = trim($str);
			if(strlen($str))
				$this->audio_mixdown = $str;

		}

		public function set_video_encoder($str) {
			$this->video_encoder = $str;
		}

		public function set_video_quality($int) {
			$int = abs(intval($int));
			$this->video_quality = $int;
		}

		public function set_video_framerate($int) {
			$this->video_framerate = $int;
		}

		public function add_audio_track($int) {
			$int = abs(intval($int));
			if($int)
				$this->audio_tracks[] = $int;
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
		public function add_audio_encoder($str) {
			if(!is_null($str))
				$this->audio_encoders[] = $str;
		}

		public function enable_audio($bool = true) {
			$this->audio = (boolean)$bool;
		}

		public function deinterlace($bool = true) {
			$this->deinterlace = (boolean)$bool;
		}

		public function decomb($bool = true) {
			$this->decomb = (boolean)$bool;
		}

		public function comb_detect($bool = true) {
			$this->comb_detect = (boolean)$bool;
		}

		public function detelecine($bool = true) {
			$this->detelecine = (boolean)$bool;
		}

		public function dvdnav($bool = true) {
			$this->dvdnav = (boolean)$bool;
		}

		public function grayscale($bool = true) {
			$this->grayscale = (boolean)$bool;
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

		public function set_h264_profile($str) {
			if($str)
				$this->h264_profile = $str;
		}

		public function set_h264_level($str) {
			if($str)
				$this->h264_level = $str;
		}

		public function set_x264_preset($str) {
			$this->x264_preset = $str;
		}

		public function set_x264_tune($str) {
			$this->x264_tune = $str;
		}

		public function set_http_optimize($bool = true) {
			$this->http_optimize = (bool)$bool;
		}

		public function set_color_matrix($str) {
			$this->color_matrix = strtolower($str);
		}

		// FIXME do checks for audio types
		public function set_audio_fallback($str) {
			$this->audio_fallback = $str;
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

			// Check for deinterlacing filter
			if($this->deinterlace)
				$options[] = "--deinterlace=bob";

			// Check for detelecine filter
			if($this->detelecine)
				$options[] = "--detelecine";

			// Check for decombing filter
			if($this->decomb && !$this->comb_detect) {
				$options[] = "--decomb";
			}

			if($this->comb_detect) {
				$options[] = "--decomb=eedi2bob";
				$options[] = "--comb-detect=permissive";
			}

			// Check for grayscale
			if($this->grayscale)
				$options[] = "--grayscale";

			// Check for auto anamorphic
			if($this->auto_anamorphic)
				$options[] = "--auto-anamorphic";

			// Check for no-dvdnav
			if(!$this->dvdnav)
				$options[] = "--no-dvdnav";

			// Check for HTTP optimization
			if($this->http_optimize)
				$options[] = "--optimize";

			// If audio is enabled and no tracks have been specifically selected,
			// then choose the first English one
			if($this->audio && !count($this->audio_tracks))
				$options[] = "--first-audio";

			// Set constant framerate
			if(!is_null($this->video_framerate)) {
				$options[] = '--cfr';
			}

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
			$args['--encoder'] = $this->video_encoder;

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

			// Set video framerate
			if(!is_null($this->video_framerate)) {
				$args['--rate'] = $this->video_framerate;
			}

			// Set H.264 profile
			if(!is_null($this->h264_profile)) {
				$args['--encoder-profile'] = $this->h264_profile;
			}

			// Set H.264 level
			if(!is_null($this->h264_level)) {
				$args['--encoder-level'] = $this->h264_level;
			}

			// Set x264 preset
			if($this->x264_preset) {
				$args['--encoder-preset'] = $this->x264_preset;
			}

			// Set x264 tune option
			if($this->x264_tune) {
				$args['--encoder-tune'] = $this->x264_tune;
			}

			// Set color matrix
			if($this->color_matrix) {
				$args['--color-matrix'] = $this->color_matrix;
			}

			// Set duration for QA
			if($this->duration) {
				$args['--stop-at'] = "duration:".$this->duration;
			}

			// Set x264 encoding options
			// This should always be set last, since it can
			// override both the preset and tune options.
			if(count($this->x264)) {
				$args['--encopts'] = $this->get_x264opts();
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
				} else {
					$args['--audio-lang-list'] = 'eng';
				}

				// Add audio encoders
				if(count($this->audio_encoders)) {
					$str = implode(",", $this->audio_encoders);
					$args['--aencoder'] = $str;
				}

				// Set fallback audio encoder -- this is used if Handbrake
				// cannot copy or encode the audio with previous arguments
				if($this->audio_fallback) {
					$args['--audio-fallback'] = $this->audio_fallback;
				}

				if($this->audio_bitrate) {
					$args['--ab'] = $this->audio_bitrate;
				}

				if($this->audio_mixdown) {
					$args['--mixdown'] = $this->audio_mixdown;

				}

			}


			/** Subtitles **/

			// Add subtitle tracks
			if(count($this->subtitle_tracks)) {
				$str = implode(",", $this->subtitle_tracks);
				$args['--subtitle'] = $str;
			}

			/**
			 * Container
			 */

			// Add format
			if(!is_null($this->format)) {
				$args['--format'] = $this->format;
			}

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

			$options = $this->get_options();

			foreach($options as $str)
				$cmd[] = escapeshellarg($str);

			$str = $this->binary." ".implode(" ", $cmd);

			$arg_input = escapeshellarg($this->input);
			$arg_output = escapeshellarg($this->output);
			$str .= " --input $arg_input";
			$str .= " --output $arg_output";

			return $str;

		}

		public function set_x264($key, $value) {

			if(is_null($value) && array_key_exists($key, $this->x264))
				unset($this->x264[$key]);
			elseif(!is_null($value))
				$this->x264[$key] = $value;


		}

		public function set_x264opts($str) {

			if(!$str) {
				$this->x264 = array();
				return;
			}

			$arr = explode(":", $str);

			foreach($arr as $str2) {
				$arr2 = explode("=", $str2);
				$this->set_x264($arr2[0], $arr2[1]);
			}

		}

		public function set_preset($preset) {

			$this->preset = $preset;

		}

		public function get_x264opts() {

			if($this->x264) {

				foreach($this->x264 as $key => $value)
					$arr[] = "$key=$value";

				$str = implode(":", $arr);
			}

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
			$cmd = $this->binary." --scan --verbose $options --input $arg_input 2>&1";

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

			if($this->dry_run) {
				echo "* Dry run\n";
				echo escapeshellcmd($str);
				echo "\n";
				return 1;
			}

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
