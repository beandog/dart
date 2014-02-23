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
		private $flags = array();
		private $args = array();

		// Video
		private $video_encoder = 'x264';
		private $video_quality = 20;
		private $deinterlace = false;
		private $decomb = true;
		private $detelecine = true;
		private $grayscale = false;
		private $crop = "0:0:0:0";
		private $h264_profile = 'high';
		private $h264_level = '3.1';
		private $x264_preset = 'medium';
		private $x264_tune = 'film';
		private $x264 = array();

		// Audio
		private $audio_tracks = array();
		private $audio_encoders = array();
		private $audio_streams = array();

		// Container
		private $format = 'mkv';
		private $add_chapters = false;
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

			if($str == 'mkv' || $str == 'mp4')
				$this->format = $str;

		}

		public function add_chapters($bool = true) {
			$this->add_chapters = (boolean)$bool;
		}

		public function set_video_encoder($str) {
			if($str == 'x264' || $str == 'ffmpeg4' || $str == 'ffmpeg2' || $str == 'theora')
				$this->video_encoder = $str;
		}

		public function set_video_quality($int) {

			$int = intval($int);

			$this->video_quality = $int;

		}

		public function add_audio_track($int) {

			$int = intval($int);

			$this->audio_tracks[] = $int;

		}

		public function add_audio_stream($stream_id) {

			// Add the audio track only if the stream ID is available from scan
			if(array_key_exists($stream_id, $this->audio_streams))
				$this->add_audio_track($this->audio_streams[$stream_id]);

		}

		public function add_audio_encoder($str) {

			if(!is_null($str))
				$this->audio_encoders[] = $str;

		}

		public function autocrop($bool = true) {

			$bool = (boolean)$bool;

			if($bool)
				$this->crop = null;
			else
				$this->crop = "0:0:0:0";


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
			$this->h264_profile = $str;
		}

		public function set_h264_level($str) {
			$this->h264_profile = $str;
		}

		public function set_x264_preset($str) {
			$this->x264_preset = $str;
		}

		public function set_x264_tune($str) {
			if($str == 'animation' || $str == 'grain' || $str == 'film')
				$this->x264_tune = $str;
		}

		public function add_subtitle_track($int) {

			$int = intval($int);

			$this->subtitle_tracks[] = $int;

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
				// FIXME?
				// Why would I ever willingly disable audio?
				//$args['--audio'] = 'none';
				$args['--audio'] = 1;
			}

			// Add audio encoders
			if(count($this->audio_encoders)) {
				$str = implode(",", $this->audio_encoders);
				$args['--aencoder'] = $str;
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

			if($this->verbose && !$this->debug)
				shell::cmd("$str", true, false, $this->verbose, array(0));
			else
				shell::cmd($str, !$this->verbose, false, $this->debug, array(0));

		}

	}
?>
