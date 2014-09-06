<?php

	// Supports HandBrake versions 0.9.9, 0.10

	class Handbrake {

		// Handbrake
		public $binary = "HandBrakeCLI";
		public $version = "0.10";
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

		// DVD source
		public $dvd;
		public $dvd_num_audio_tracks;
		public $dvd_num_subtitles;

		// Video
		public $video_bitrate;
		public $video_encoder;
		public $video_encoders = array('x264', 'ffmpeg4', 'ffmpeg2', 'theora');
		public $video_quality;
		public $crop;
		public $deinterlace;
		public $decomb;
		public $detelecine;
		public $grayscale;
		public $two_pass;
		public $two_pass_turbo;
		public $h264_profile;
		public $h264_profiles = array('auto', 'high', 'main', 'baseline');
		public $h264_level;
		public $h264_levels = array('auto', '1.0', '1.b', '1.1', '1.2', '1.3', '2.0', '2.1', '2.2', '3.0', '3.1', '3.2', '4.0', '4.1', '4.2', '5.0', '5.1', '5.2');
		public $x264_preset;
		public $x264_presets = array('ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow', 'placebo');
		public $x264_tune;
		public $x264_tuning_options = array('film', 'animation', 'grain', 'stillimage', 'psnr', 'ssim', 'fastdecode', 'zerolatency');
		public $x264 = array();

		// Audio
		public $audio = true;
		public $audio_encoders = array();
		public $audio_tracks = array();
		public $audio_streams = array();
		public $audio_bitrate;
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

		public function set_version($str) {
			$this->version = $str;
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
			if($track) {
				$this->track = $track;
				return true;
			} else {
				return false;
			}
		}

		public function set_dry_run($bool = true) {
			$this->dry_run = (boolean)$bool;
		}

		public function output_format($str) {
			$this->format = $str;
		}

		public function add_chapters($bool = true) {
			$this->add_chapters = (boolean)$bool;
		}

		public function set_video_bitrate($int) {
			$int = abs(intval($int));

			if($int) {
				$this->video_bitrate = $int;
				return true;
			} else {
				return false;
			}
		}

		public function set_audio_bitrate($int) {
			$int = abs(intval($int));
			if($int)
				$this->audio_bitrate = $int;
		}

		public function set_video_encoder($str) {
			if($str == 'x264' || $str == 'ffmpeg4' || $str == 'ffmpeg2' || $str == 'theora') {
				$this->video_encoder = $str;
				return true;
			} else {
				return false;
			}
		}

		public function set_video_quality($int) {
			$int = abs(intval($int));
			if($int)
				$this->video_quality = $int;
		}

		public function set_two_pass($bool = true) {
			$bool = (bool)$bool;
			$this->two_pass = $bool;
		}

		public function set_two_pass_turbo($bool = true) {
			$bool = (bool)$bool;
			$this->two_pass_turbo = $bool;
		}

		public function add_audio_track($int) {
			$int = abs(intval($int));
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

		public function enable_audio() {
			$this->audio = true;
		}

		public function autocrop($bool = true) {
			$bool = (boolean)$bool;
			if($bool)
				$this->crop = null;
			else
				$this->crop = "0:0:0:0";
			return true;
		}

		public function deinterlace($bool = true) {
			$this->deinterlace = (boolean)$bool;
		}

		public function decomb($bool = true) {
			$this->decomb = (boolean)$bool;
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

		public function set_h264_profile($str) {
			if(in_array($str, $this->h264_profiles)) {
				$this->h264_profile = $str;
				return true;
			} else {
				return false;
			}
		}

		public function set_h264_level($str) {
			if(in_array($str, $this->h264_levels)) {
				$this->h264_level = $str;
				return true;
			} else {
				return false;
			}
		}

		public function set_x264_preset($str) {
			if(in_array($str, $this->x264_presets)) {
				$this->x264_preset = $str;
				return true;
			} else {
				return false;
			}
		}

		public function set_x264_tune($str) {
			if(in_array($str, $this->x264_tuning_options)) {
				$this->x264_tune = $str;
				return true;
			} else {
				return false;
			}
		}

		public function set_http_optimize($bool = true) {
			$this->http_optimize = (bool)$bool;
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
				$options[] = "--deinterlace";

			// Check for decombing filter
			if($this->decomb)
				$options[] = "--decomb";

			// Check for detelecine filter
			if($this->detelecine)
				$options[] = "--detelecine";

			// Check for grayscale
			if($this->grayscale)
				$options[] = "--grayscale";

			// Check for no-dvdnav
			if(!$this->dvdnav)
				$options[] = "--no-dvdnav";

			// Check for HTTP optimization
			if($this->http_optimize)
				$options[] = "--optimize";

			// Two pass encoding options
			if($this->two_pass)
				$options[] = "--two-pass";
			if($this->two_pass_turbo)
				$options[] = "--turbo";

			return $options;

		}

		public function get_arguments() {

			$args = array();

			/**
			 * Handbrake
			 **/

			// Add preset
			if(!is_null($this->preset)) {
				if($this->version == '0.9.9')
					$args['--preset'] = $this->preset;
				else
					$args['--encoder-preset'] = $this->preset;
			}

			// Set track #
			if($this->track)
				$args['--title'] = $this->track;

			/**
			 * Video
			 **/

			// Set encoder
			$args['--encoder'] = $this->video_encoder;

			// Add video bitrate
			if($this->video_bitrate) {
				$args['--vb'] = $this->video_bitrate;
			}

			// Add video quality
			if(!is_null($this->video_quality)) {
				$args['--quality'] = $this->video_quality;
			}

			// Set cropping parameters
			if(!is_null($this->crop)) {
				$args['--crop'] = $this->crop;
			}

			// Set H.264 profile
			if(!is_null($this->h264_profile)) {
				if($this->version == '0.9.9')
					$args['--h264-profile'] = $this->h264_profile;
				else
					$args['--encoder-profile'] = $this->h264_profile;
			}

			// Set H.264 level
			if(!is_null($this->h264_level)) {
				if($this->version == '0.9.9')
					$args['--h264-level'] = $this->h264_level;
				else
					$args['--encoder-level'] = $this->h264_level;
			}

			// Set x264 preset
			if(!is_null($this->x264_preset)) {
				if($this->version == '0.9.9')
					$args['--x264-preset'] = $this->x264_preset;
				else
					$args['--encoder-preset'] = $this->x264_preset;
			}

			// Set x264 tune option
			if(!is_null($this->x264_tune)) {
				if($this->version == '0.9.9')
					$args['--x264-tune'] = $this->x264_tune;
				else
					$args['--encoder-tune'] = $this->x264_tune;
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

				// Select audio streams to encode.  If none are specified,
				// just use the first one.
				if(count($this->audio_tracks)) {
					$str = implode(",", $this->audio_tracks);
					$args['--audio'] = $str;
				} else
					$args['--audio'] = 1;

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

			} else {
				$args['--audio'] = 1;
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

			$cmd = "";

			$options = $this->get_options();

			foreach($options as $str)
				$cmd[] = escapeshellarg($str);

			$args = $this->get_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

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
		 * some reason or another.  This uses proc_open() to cleanly run the
		 * process, monitor it for timing out, and kill it if necessary.
		 *
		 * Default to 10 seconds, which is a generous amount of time.
		 *
		 */
		public function scan($max_wait_time = 10) {

			$options = '';

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
			unlink($output_file);

			// Strip out library messages
			$pattern = "/^(libdvdread|libdvdnav|libbluray)/";
			$arr = preg_grep($pattern, $arr, PREG_GREP_INVERT);

			// Strip out "[00:11:22] scan: " strings
			$arr = preg_replace("/\[\d{2}:\d{2}:\d{2}\] scan: /", "", $arr);

			// Find all the lines that list the audio and subtitle streams
			$arr_scan_streams = array_merge(preg_grep("/^id=0/", $arr));

			$this->dvd['streams']['audio'] = array();
			$this->dvd['streams']['subtitle'] = array();

			$stream_ix['audio'] = 1;
			$stream_ix['subtitle'] = 1;

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
				$tmp = explode(' ', $stream_data[2]);
				$stream_3cc = substr($tmp[0], 4);
				$stream_ext = substr($tmp[1], 4);

				$this->dvd['streams'][$stream_type][] = array(
					'track' => $stream_ix[$stream_type],
					'id' => $stream_id,
					'lang' => $stream_lang,
					'3cc' => $stream_3cc,
					'ext' => $stream_ext,
				);

				$stream_ix[$stream_type]++;

			}

			$this->dvd_num_audio_tracks = count($this->dvd['streams']['audio']);
			$this->dvd_num_subtitles = count($this->dvd['streams']['subtitle']);

			// Sample source string: Closed Captions (iso639-2: eng) (Text)(CC)
			$closed_captioning = preg_grep("/.*Closed Captions.*eng.*/", $arr);

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
