<?php

	class DVD {

		private $device;
		private $dvd_info;
		private $is_iso;
		private $debug;

		public $opened;

		public $dvdread_id;
		public $title;
		public $tracks;
		public $longest_track;
		public $provider_id;
		public $size;

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
				$this->opened = false;
			else
				$this->opened = true;

			$this->dvdread_id = $this->dvdread_id();
			$this->title = $this->title();
			$this->tracks = $this->tracks();
			$this->longest_track = $this->longest_track();
			$this->provider_id = $this->provider_id();
			$this->size = $this->size();

			return $bool;

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
		public function title() {

			if(!$this->opened)
				return null;

			$title = $this->dvd_info['dvd']['title'];

			$title = trim($title);

			return $title;
		}

		/** Tracks **/
		public function tracks() {

			if(!$this->opened)
				return null;

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info)) {

				if($this->debug) {
					echo "! getNumTracks(): DVD has no tracks!!!  This is bad.\n";
				}

				return 0;

			}

			$num_tracks = count($this->dvd_info['tracks']);

			return $num_tracks;

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
				echo "! dvd->size($format)\n";

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

	}
?>
