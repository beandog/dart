<?php

	class HandBrake {

		// Handbrake
		public $verbose = false;
		public $debug = false;
		public $track;
		public $arguments = array();
		public $options = array();
		public $scan_complete = false;
		public $do_not_scan = false;
		public $output;
		public $disc_type = 'dvd';

		// DVD source
		public $device;
		public $dvd_num_audio_tracks;
		public $dvd_num_subtitles;

		// Audio
		public $audio = true;
		public $audio_tracks = array();
		public $audio_streams = array();

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

		/** Filename **/
		public function input_filename($src) {
			$this->device = $src;
		}

		public function input_track($str) {
			$track = abs(intval($str));
			$this->track = $track;
		}

		public function add_chapters($bool = true) {
			$this->add_chapters = boolval($bool);
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

		public function dvdnav($bool = true) {
			if($bool)
				$this->add_option('no-dvdnav');
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

			// If audio is enabled and no tracks have been specifically selected,
			// then choose the first English one for DVD. For Blu-ray, there can bee
			// all kinds of channel numbers, just grab them all and select in player.
			if($this->audio && !count($this->audio_tracks) && $this->disc_type == 'dvd')
				$this->options[] = 'first-audio';
			if($this->audio && !count($this->audio_tracks) && $this->disc_type == 'bluray')
				$this->options[] = 'all-audio';

			return $this->options;

		}

		public function add_argument($key, $value) {
			$this->arguments[$key] = $value;
		}

		public function add_option($str) {
			$this->options[] = $str;
		}

		public function get_arguments() {

			/**
			 * Handbrake
			 **/

			// Add audio tracks
			if($this->audio) {

				// Select audio streams to encode, default to English if no
				// streams are specified.
				if(count($this->audio_tracks)) {
					$str = implode(',', $this->audio_tracks);
					$this->arguments['audio'] = $str;
				}

			}

			/** Subtitles **/

			// Add subtitle tracks
			if(count($this->subtitle_tracks) && $this->subtitles) {
				$str = implode(',', $this->subtitle_tracks);
				$this->arguments['subtitle'] = $str;
			}

			if(!$this->subtitles) {
				$this->arguments['subtitle'] = 'none';
			}

			/**
			 * Container
			 */

			// Add chapters
			if(!empty($this->starting_chapter)) {
				$this->arguments['chapters'] = $this->starting_chapter."-";
			}
			if(!empty($this->ending_chapter)) {
				$this->arguments['chapters'] .= $this->ending_chapter;
			}

			return $this->arguments;

		}

		public function get_executable_string() {

			$cmd = array();
			$cmd[] = 'HandBrakeCLI';
			$cmd[] = '--input '.escapeshellarg($this->device);

			foreach($this->arguments as $key => $value) {
				$value = escapeshellarg($value);
				$cmd[] = "--$key $value";
			}

			foreach($this->options as $str)
				$cmd[] = "--$str";

			$str = implode(' ', $cmd);

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

			$arg_input = escapeshellarg($this->device);
			$cmd = "HandBrakeCLI --scan --verbose $options --input $arg_input 2>&1";

			if(str_contains(php_uname('r'), 'WSL2') || str_contains(php_uname('s'), 'Windows NT'))
				$cmd = str_replace('HandBrakeCLI', 'HandBrakeCLI.exe', $cmd);

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
