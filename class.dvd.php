<?php

	class DVD {

		private $device;
		private $dvd_info;
		private $is_iso;
		public $debug;

		public $opened;

		// DVD
		public $dvdread_id;
		public $title;
		public $title_tracks;
		public $longest_track;
		public $provider_id;
		public $size;

		// DVD Track
		public $title_track;
		public $title_track_length;
		public $title_track_msecs;
		public $title_track_vts;
		public $title_track_ttn;
		public $title_track_chapters;
		public $title_track_cells;
		public $title_track_audio_tracks;
		public $title_track_subtitle_tracks;

		// DVD Video
		public $video_codec;
		public $video_format;
		public $video_aspect_ratio;
		public $video_width;
		public $video_height;
		public $video_angles;
		public $video_fps;

		// DVD Audio
		public $audio_track;
		public $audio_track_codec;
		public $audio_track_channels;
		public $audio_track_stream_id;

		function __construct($device = "/dev/dvd", $debug = false) {

			$this->device = realpath($device);
			$this->debug = (bool)$debug;

			if(!file_exists($this->device)) {
				$this->opened = false;
				return null;
			}

			$dirname = dirname($this->device);
			if($dirname != "/dev")
				$this->is_iso = true;
			else
				$this->is_iso = false;

			// Run dvd_info first and return if it passes or not
			$bool = $this->dvd_info();

			if($bool === false)
				return false;
			else
				$this->opened = true;

			bcscale(3);

			$this->dvdread_id = $this->dvdread_id();
			$this->title = $this->title();
			$this->title_tracks = $this->title_tracks();
			$this->longest_track = $this->longest_track();
			$this->provider_id = $this->provider_id();
			$this->size = $this->size();

			return true;

		}

		/** Hardware **/

		private function dvd_info() {

			$cmd = "dvd_info --json ".escapeshellarg($this->device)." 2> /dev/null";

			if($this->debug)
				echo "! dvd_info(): $cmd\n";

			exec($cmd, $output, $retval);

			if($retval !== 0 || !count($output)) {
				echo "! dvd_info(): FAILED\n";
				return false;
			}

			$str = implode('', $output);

			// Create an assoc. array
			$json = json_decode($str, true);

			if(is_null($json)) {
				echo "! dvd_info(): json_decode() failed\n";
				return false;
			}

			$this->dvd_info = $json;

			return true;

		}

		protected function dvd_info_string($arr, $key) {

			if(!array_key_exists($key, $arr))
				return "";
			else
				return $arr[$key];

		}

		protected function dvd_info_number($arr, $key) {

			if(!array_key_exists($key, $arr))
				return 0;
			else
				return $arr[$key];

		}

		/** Metadata **/

		// Use dvd_info to get dvdread id
		public function dvdread_id() {

			if(!$this->opened)
				return null;

			if($this->debug)
				echo "! dvd->dvdread_id()\n";

			$dvdread_id = $this->dvd_info['dvd']['dvdread id'];

			if(strlen($dvdread_id) != 32)
				return false;

			return $dvdread_id;

		}

		public function dump_iso($dest, $method = 'ddrescue') {

			if(!$this->opened)
				return null;

			if($this->debug)
				echo "! dvd->dump_iso($dest, $method)\n";

			// ddrescue README
			// Since I've used dd in the past, ddrescue seems like a good
			// alternative that can work around broken sectors, which was
			// the main feature I liked about readdvd to begin with.
			// It does come with a lot of options, so I'm testing these out
			// for now; however, I have seen multiple examples of using these
			// arguments for DVDs.
			if($method == 'ddrescue') {

				$logfile = getenv('HOME')."/.ddrescue/".$this->dvdread_id().".log";

				if(file_exists($logfile))
					unlink($logfile);

				$cmd = "ddrescue -b 2048 -n ".escapeshellarg($this->device)." ".escapeshellarg($dest)." ".escapeshellarg($logfile);
				passthru($cmd, $retval);

				if($retval !== 0)
					return false;
				else
					return true;

			} elseif($method == 'pv') {
				$exec = "pv -pter -w 80 ".escapeshellarg($this->device)." | dd of=".escapeshellarg($dest)." 2> /dev/null";
				$exec .= '; echo ${PIPESTATUS[*]}';

				exec($exec, $arr);

				foreach($arr as $exit_code)
					if(intval($exit_code))
						return false;

				return true;
			}

		}

		public function dump_ifo($dest) {

			if(!$this->opened)
				return null;

			if($this->debug)
				echo "! dvd->dump_ifo($dest)\n";

			chdir($dest);

			$exec = "dvd_backup_ifo ".escapeshellarg($this->device)." &> /dev/null";

			$arr = array();

			exec($exec, $arr, $retval);

			if($retval !== 0)
				return false;
			else
				return true;

		}

		/**
		 * Get the DVD title
		 */
		private function title() {

			if(!$this->opened)
				return null;

			$title = $this->dvd_info['dvd']['title'];

			$title = trim($title);

			return $title;
		}

		/** Tracks **/
		private function title_tracks() {

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info)) {

				if($this->debug) {
					echo "! title_tracks(): DVD has no tracks!!!  This is bad.\n";
				}

				return 0;

			}

			return count($this->dvd_info['tracks']);

		}

		public function longest_track() {

			if(!$this->opened)
				return null;

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info)) {

				if($this->debug) {
					echo "! longest_track(): DVD has no tracks!!!  This is bad.\n";
				}

				return null;

			}

			// Loop through all the lengths of the tracks, and set the one
			// with the longest amount of msecs to the longest.  If a following
			// one has equal length than an earlier one, then default to the first
			// one with that maximum length.

			$longest_track = 1;
			$longest_track_msecs = 0;

			foreach($this->dvd_info['tracks'] as $arr) {

				if($arr['msecs'] > $longest_track_msecs) {

					$longest_track = $arr['track'];
					$longest_track_msecs = $arr['msecs'];

				}

			}

			return $longest_track;

		}

		public function provider_id() {

			if(!$this->opened)
				return null;

			$dvd =& $this->dvd_info;

			if(array_key_exists('provider id', $dvd['dvd'])) {
				$provider_id = $dvd['dvd']['provider id'];
				$provider_id = trim($provider_id);
			} else
				$provider_id = '';

			return $provider_id;

		}

		/**
		 * Get the size of the filesystem on the device
		 */
		public function size() {

			if($this->debug)
				echo "! dvd->size()\n";

			if($this->is_iso) {
				$stat = stat($this->device);
				$b_size = $stat['size'];
			} else {

				$block_device = basename($this->device, "/dev/");
				$num_sectors = file_get_contents("/sys/block/$block_device/size");
				$b_size = $num_sectors * 512;

			}

			if(!$b_size)
				return 0;

			$kb_size = $b_size / 1024;
			$mb_size = intval($kb_size / 1024);

			return $mb_size;

		}

		// Use serial number from HandBrake 0.9.9
		public function serial_id() {

			if($this->debug)
				echo "! dvd->serial_id()\n";

			$exec = "HandBrakeCLI --scan -i ".escapeshellarg($this->device)." 2>&1";
			exec($exec, $arr, $retval);

			if($retval !== 0) {
				echo "! getSerialID(): HandBrakeCLI quit with exit code $retval\n";
				return null;
			}

			$pattern = "/.*Serial.*/";
			$match = preg_grep($pattern, $arr);

			if(!count($match)) {
				if($this->debug)
					echo "! getSerialID(): HandBrakeCLI did not have a line matching pattern $pattern\n";
				return null;
			}

			$explode = explode(' ', current($match));

			if(!count($explode)) {
				if($this->debug)
					echo "! getSerialID(): Couldn't find a string\n";
				return null;
			}

			$serial_id = end($explode);

			$serial_id = trim($serial_id);

			return $serial_id;

		}

		/** DVD Track **/

		public function load_title_track($title_track) {

			$title_track = abs(intval($title_track));

			if($title_track === 0 || $title_track > $this->title_tracks) {
				return false;
			}

			$this->title_track = $title_track;
			$this->title_track_info = $this->dvd_info['tracks'][$this->title_track - 1];

			$this->title_track_length = $this->title_track_length();
			$this->title_track_msecs = $this->title_track_msecs();
			$this->title_track_seconds = $this->title_track_seconds();
			$this->title_track_vts = $this->title_track_vts();
			$this->title_track_ttn = $this->title_track_ttn();
			$this->title_track_chapters = $this->title_track_chapters();
			$this->title_track_cells = $this->title_track_cells();
			$this->title_track_audio_tracks = $this->title_track_audio_tracks();
			$this->title_track_subtitle_tracks = $this->title_track_subtitle_tracks();

			$this->video_codec = $this->video_codec();
			$this->video_format = $this->video_format();
			$this->video_aspect_ratio = $this->video_aspect_ratio();
			$this->video_width = $this->video_width();
			$this->video_height = $this->video_height();
			$this->video_angles = $this->video_angles();
			$this->video_fps = $this->video_fps();

			return true;

		}

		private function title_track_length() {
			return $this->dvd_info_string($this->title_track_info, 'length');
		}

		private function title_track_msecs() {
			return $this->dvd_info_number($this->title_track_info, 'msecs');
		}

		private function title_track_seconds() {

			bcscale(3);
			$msecs = $this->title_track_msecs();
			$seconds = bcdiv($msecs, 1000);

			return $seconds;

		}

		private function title_track_vts() {
			return $this->dvd_info_number($this->title_track_info, 'vts');
		}

		private function title_track_ttn() {
			return $this->dvd_info_number($this->title_track_info, 'vts');
		}

		private function title_track_audio_tracks() {

			if(!array_key_exists('audio', $this->title_track_info))
				return 0;

			return count($this->title_track_info['audio']);

		}

		private function title_track_subtitle_tracks() {

			if(!array_key_exists('subtitles', $this->title_track_info))
				return 0;

			return count($this->title_track_info['subtitles']);

		}

		private function title_track_chapters() {

			if(!array_key_exists('chapters', $this->title_track_info))
				return 0;

			return count($this->title_track_info['chapters']);

		}

		private function title_track_cells() {

			if(!array_key_exists('cells', $this->title_track_info))
				return 0;

			return count($this->title_track_info['cells']);

		}

		private function video_codec() {
			return $this->dvd_info_string($this->title_track_info['video'], 'codec');
		}

		private function video_format() {
			return $this->dvd_info_string($this->title_track_info['video'], 'format');
		}

		private function video_aspect_ratio() {
			return $this->dvd_info_string($this->title_track_info['video'], 'aspect ratio');
		}

		private function video_width() {
			return $this->dvd_info_number($this->title_track_info['video'], 'width');
		}

		private function video_height() {
			return $this->dvd_info_number($this->title_track_info['video'], 'height');
		}

		private function video_angles() {
			return $this->dvd_info_number($this->title_track_info['video'], 'angles');
		}

		private function video_fps() {
			return $this->dvd_info_number($this->title_track_info['video'], 'fps');
		}

		/** DVD Audio Track **/

		public function load_audio_track($title_track, $audio_track) {

			$title_track = abs(intval($title_track));
			$audio_track = abs(intval($audio_track));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $audio_track === 0 || $audio_track > $this->title_track_audio_tracks) {

				return false;
			}

			$this->audio_track = $audio_track;
			$this->audio_track_info = $this->dvd_info['tracks'][$this->title_track - 1]['audio'][$this->audio_track - 1];

			$this->audio_track_lang_code = $this->audio_track_lang_code();
			$this->audio_track_codec = $this->audio_track_codec();
			$this->audio_track_channels = $this->audio_track_channels();
			$this->audio_track_stream_id = $this->audio_track_stream_id();

			return true;

		}

		private function audio_track_lang_code() {
			return $this->dvd_info_string($this->audio_track_info, 'lang code');
		}

		private function audio_track_codec() {
			return $this->dvd_info_string($this->audio_track_info, 'codec');
		}

		private function audio_track_channels() {
			return $this->dvd_info_number($this->audio_track_info, 'channels');
		}

		private function audio_track_stream_id() {
			return $this->dvd_info_string($this->audio_track_info, 'stream id');
		}

		/** DVD Subtitle Track **/

		public function load_subtitle_track($title_track, $subtitle_track) {

			$title_track = abs(intval($title_track));
			$subtitle_track = abs(intval($subtitle_track));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $subtitle_track === 0 || $subtitle_track > $this->title_track_subtitle_tracks) {

				return false;
			}

			$this->subtitle_track = $subtitle_track;
			$this->subtitle_track_info = $this->dvd_info['tracks'][$this->title_track - 1]['subtitles'][$this->subtitle_track - 1];

			$this->subtitle_track_lang_code = $this->subtitle_track_lang_code();

			return true;

		}

		private function subtitle_track_lang_code() {
			return $this->dvd_info_string($this->subtitle_track_info, 'lang code');
		}

		private function subtitle_track_stream_id() {
			return $this->dvd_info_string($this->subtitle_track_info, 'stream id');
		}

	}

