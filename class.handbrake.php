<?php

	class Handbrake {

		// Handbrake
		private $binary = "HandBrakeCLI";
		private $verbose = false;
		private $debug = false;
		private $dvdnav = true;
		private $preset;
		private $filename;
		private $track;
		private $http_optimize;
		private $flags = array();
		private $args = array();

		// Video
		private $video_bitrate;
		private $video_encoder;
		private $video_encoders = array('x264', 'ffmpeg4', 'ffmpeg2', 'theora');
		private $video_quality;
		private $crop;
		private $deinterlace;
		private $decomb;
		private $detelecine;
		private $grayscale;
		private $two_pass;
		private $two_pass_turbo;
		private $h264_profile;
		private $h264_profiles = array('auto', 'high', 'main', 'baseline');
		private $h264_level;
		private $h264_levels = array('auto', '1.0', '1.b', '1.1', '1.2', '1.3', '2.0', '2.1', '2.2', '3.0', '3.1', '3.2', '4.0', '4.1', '4.2', '5.0', '5.1', '5.2');
		private $x264_preset;
		private $x264_presets = array('ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow', 'placebo');
		private $x264_tune;
		private $x264_tuning_options = array('film', 'animation', 'grain', 'stillimage', 'psnr', 'ssim', 'fastdecode', 'zerolatency');
		private $x264 = array();

		// Audio
		private $audio_encoders = array();
		private $audio_tracks = array();
		private $audio_streams = array();
		private $audio_fallback;

		// Container
		private $add_chapters = false;
		private $format;
		private $starting_chapter;
		private $ending_chapter;

		// Subtitles
		private $subtitle_tracks = array();
		private $srt_language = 'eng';
		private $cc = false;
		private $cc_ix;
		private $num_bitmaps;

		function __construct($filename = null) {

			if(!is_null($filename))
				$this->input_filename($filename);

		}

		function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}

		public function set_binary($str = "handbrake") {
			$this->binary = $str;
		}

		/** Filename **/
		public function input_filename($src, $track = null) {
			$this->input = $src;
			if($track)
				$this->input_track($track);
			$this->scan();
		}

		public function output_filename($str) {
			$this->output = $str;
		}

		public function input_track($str) {
			$this->track = $str;
		}

		public function set_debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}

		public function output_format($str) {
			if($str == 'mkv' || $str == 'mp4') {
				$this->format = $str;
				return true;
			} else {
				return false;
			}
		}

		public function add_chapters($bool = true) {
			$this->add_chapters = (boolean)$bool;
			return true;
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
			if($int) {
				$this->video_quality = $int;
				return true;
			} else {
				return false;
			}
		}

		public function add_audio_track($int) {
			$int = abs(intval($int));
			if($int) {
				$this->audio_tracks[] = $int;
				return true;
			} else {
				return false;
			}
		}

		public function add_audio_stream($stream_id) {
			// Add the audio track only if the stream ID is available from scan
			if(array_key_exists($stream_id, $this->audio_streams)) {
				$this->add_audio_track($this->audio_streams[$stream_id]);
				return true;
			} else {
				return false;
			}
		}

		// FIXME limit to set audio encoders
		public function add_audio_encoder($str) {
			if(!is_null($str)) {
				$this->audio_encoders[] = $str;
				return true;
			} else {
				return false;
			}
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
			return true;
		}

		public function decomb($bool = true) {
			$this->decomb = (boolean)$bool;
			return true;
		}

		public function detelecine($bool = true) {
			$this->detelecine = (boolean)$bool;
			return true;
		}

		public function dvdnav($bool = true) {
			$this->dvdnav = (boolean)$bool;
			return true;
		}

		public function grayscale($bool = true) {
			$this->grayscale = (boolean)$bool;
			return true;
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

		public function set_http_optimize($bool) {
			$this->http_optimize = (bool)$bool;
			return true;
		}

		// FIXME do checks for audio types
		public function set_audio_fallback($str) {
			$this->audio_fallback = $str;
		}

		public function add_subtitle_track($int) {
			$int = abs(intval($int));
			if($int) {
				$this->subtitle_tracks[] = $int;
				return true;
			} else {
				return false;
			}
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

			return $options;

		}

		public function get_arguments() {

			$args = array();

			/**
			 * Handbrake
			 **/

			// Add preset
			if(!is_null($this->preset)) {
				$args['--preset'] = $this->preset;
			}

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

			// Set cropping parameters
			if(!is_null($this->crop)) {
				$args['--crop'] = $this->crop;
			}

			// Set H.264 profile
			if(!is_null($this->h264_profile)) {
				$args['--h264-profile'] = $this->h264_profile;
			}

			// Set H.264 level
			if(!is_null($this->h264_level)) {
				$args['--h264-level'] = $this->h264_level;
			}

			// Set x264 preset
			if(!is_null($this->x264_preset)) {
				$args['--x264-preset'] = $this->x264_preset;
			}

			// Set x264 tune option
			if(!is_null($this->x264_tune)) {
				$args['--x264-tune'] = $this->x264_tune;
			}

			// Set x264 encoding options
			if(count($this->x264)) {
				$args['--encopts'] = $this->get_x264opts();
			}

			/**
			 * Audio
			 **/

			// Add audio tracks
			if(count($this->audio_tracks)) {
				$str = implode(",", $this->audio_tracks);
				$args['--audio'] = $str;
			} elseif(count($this->audio_streams)) {

				// FIXME temporary?
				// Hit a bug on a DVD where lsdvd reported
				// 8 English audio tracks, but Handbrake
				// correctly said there is only one.
				// So, in this case, there are audio streams
				// so encoding the first one will work, it's
				// just that none were passed in.

				// This is an obvious workaround to the lsdvd
				// bug.  The correct approach would be to sync
				// up the output of lsdvd's report and handbrake's
				// scan.
				$args['--audio'] = 1;
			} else {
				// If there's no audio tracks or streams, use the first one.
				$args['--audio'] = 1;
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

			$exec = "";

			$options = $this->get_options();

			foreach($options as $str)
				$exec[] = escapeshellarg($str);

			$args = $this->get_arguments();

			foreach($args as $key => $value)
				$exec[] = "$key ".escapeshellarg($value);

			$str = $this->binary." ".implode(" ", $exec);

			$str .= " --input ".shell::escape_string($this->input);
			$str .= " --output ".shell::escape_string($this->output);

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

		public function scan() {

			$options = '';

			if($this->track)
				$options = "--title ".$this->track;

			$exec = $this->binary." --scan --verbose $options --input ".shell::escape_string($this->input)." 2>&1";

			if($this->debug)
				shell::msg("Executing: $exec");

			exec($exec, $arr, $return);

			$audio = preg_grep("/.*(scan: id=8).*/", $arr);

			$audio_index = 1;

			foreach($audio as $str) {

				$stream_id = "0x".substr($str, 20, 2);

				$this->audio_streams[$stream_id] = $audio_index;
				$audio_index++;
			}

			ksort($this->audio_streams);

			$vobsubs = preg_grep("/.*(Bitmap).*/", $arr);

			$this->num_bitmaps = count($vobsubs);

			// Sample source string: Closed Captions (iso639-2: eng) (Text)(CC)
			$cc = preg_grep("/.*Closed Captions.*eng.*/", $arr);

			if(count($cc)) {
				$this->cc = true;
				$this->cc_ix = (count($vobsubs) + 1);
			}

			// FIXME return error code of Handbrake binary

		}

		public function set_chapters($a, $b) {

			$this->starting_chapter = $a;
			$this->ending_chapter = $b;

		}

		public function get_audio_index($stream_id) {

			$var = null;
			if(in_array($stream_id, $this->audio_streams))
				$var = $this->audio_streams[$stream_id];

			return $var;
		}

		public function has_cc() {
			return $this->cc;
		}

		public function get_cc_ix() {
			return $this->cc_ix;
		}

		public function encode() {

			$str = $this->get_executable_string();

			if($this->debug)
				shell::msg("Executing: $str");

			if($this->debug)
				shell::cmd($str, !$this->verbose, false, $this->debug, array(0));
			else
				shell::cmd("$str", true, false, true, array(0));

			// FIXME return exit code of Handbrake

		}

	}
?>
