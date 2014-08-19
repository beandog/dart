<?php

	class DVD {

		private $device;
		private $dvd_info_json;
		private $lsdvd;
		private $id;
		private $is_iso;
		private $sxe;
		private $debug;

		public $opened;

		function __construct($device = "/dev/dvd") {

			$this->device = realpath($device);

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

			return $bool;

		}

		function setDebug($bool = true) {
			$this->debug = (bool)$bool;
		}

		/** Hardware **/

		function is_iso() {
			return $this->is_iso;
		}

		private function dvd_info() {

			$command = "dvd_info --json ".escapeshellarg($this->device);

			if($this->debug)
				echo "! dvd_info(): $command\n";

			exec("dvd_info --json ".escapeshellarg($this->device), $arr, $retval);
			$output = implode('', $arr);

			if($retval !== 0 || !count($arr))
				return false;

			// Create an assoc. array
			$json = json_decode($output, true);

			if(is_null($json)) {
				if($this->debug)
					echo "! dvd_info(): json_decode() failed\n";
				return false;
			}

			$this->dvd_info_json = $json;

			return true;

		}

		/** Metadata **/

		// Use dvd_info to get dvdread id
		public function dvdread_id() {

			if($this->debug)
				echo "! dvd->dvdread_id()\n";

			$dvdread_id = $this->dvd_info_json['dvd']['dvdread id'];

			if(strlen($dvdread_id) != 32)
				return false;

			return $dvdread_id;

		}

		// Use serial number from HandBrake 0.9.9
		public function getSerialID() {

			if($this->debug)
				echo "! dvd->serial_id()\n";

			$exec = "HandBrakeCLI --scan -i ".escapeshellarg($this->device)." 2>&1";
			exec($exec, $arr, $return);

			if($return !== 0) {
				if($this->debug)
					echo "! getSerialID(): HandBrakeCLI quit with exit code $return\n";
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

		private function lsdvd($force = false) {

			if($this->debug)
				echo "! dvd->lsdvd()\n";

			if(empty($this->lsdvd['output']) || $force) {
				$str = "lsdvd -Ox -v -a -s -c ".escapeshellarg($this->device);
				$arr = command($str);
				$str = implode("\n", $arr);

				// Fix broken encoding on langcodes, standardize output
				$str = str_replace('Pan&Scan', 'Pan&amp;Scan', $str);
				$str = str_replace('P&S', 'P&amp;S', $str);
				$str = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $str);

				$this->xml = $str;

				$this->sxe = simplexml_load_string($str) or die("Couldn't parse lsdvd XML output");


			}

		}

		public function dump_iso($dest, $method = 'ddrescue') {

			if($this->debug)
				echo "! dvd->dump_iso($dest, $method)\n";

			$dest = escapeshellarg($dest);

			// ddrescue README
			// Since I've used dd in the past, ddrescue seems like a good
			// alternative that can work around broken sectors, which was
			// the main feature I liked about readdvd to begin with.
			// It does come with a lot of options, so I'm testing these out
			// for now; however, I have seen multiple examples of using these
			// arguments for DVDs.
			if($method == 'ddrescue') {

				$logfile = getenv('HOME')."/.ddrescue/".$this->getID().".log";

				if(file_exists($logfile))
					unlink($logfile);

				$command = "ddrescue -b 2048 -n ".escapeshellarg($this->device)." $dest $logfile";
				passthru($command, $return);

				$return = intval($return);

				if($return) {
					return false;
				} else {
					return true;
				}

			} elseif($method == 'pv') {
				$exec = "pv -pter -w 80 ".escapeshellarg($this->device)." | dd of=$dest 2> /dev/null";
				$exec .= '; echo ${PIPESTATUS[*]}';

				exec($exec, $arr);

				foreach($arr as $exit_code)
					if(intval($exit_code))
						return false;

				return true;
			}

		}

		public function dump_ifo($dest) {

			if($this->debug)
				echo "! dvd->dump_ifo($dest)\n";

			chdir($dest);

			$exec = "dvd_backup_ifo ".escapeshellarg($this->device)." &> /dev/null";

			$arr = array();

			exec($exec, $arr, $return);

			$return = intval($return);

			if($return) {
				return false;
			} else {
				return true;
			}

		}

		/**
		 * Get the DVD title
		 */
		public function getTitle() {

			$title = $this->dvd_info_json['dvd']['title'];

			$title = trim($title);

			return $title;
		}

		public function getXML() {
			if(!$this->xml)
				$this->lsdvd();
			return $this->xml;
		}

		/** Tracks **/
		public function getNumTracks() {

			if(!$this->opened)
				return null;

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info_json)) {

				if($this->debug) {
					echo "! getNumTracks(): DVD has no tracks!!!  This is bad.\n";
				}

				return 0;

			}

			$num_tracks = count($this->dvd_info_json['tracks']);

			return $num_tracks;

		}

		public function getLongestTrack() {

			if(!$this->opened)
				return null;

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info_json)) {

				if($this->debug) {
					echo "! getLongestTrack(): DVD has no tracks!!!  This is bad.\n";
				}

				return null;

			}

			// Loop through all the lengths of the tracks, and set the one
			// with the longest amount of msecs to the longest.  If a following
			// one has equal length than an earlier one, then default to the first
			// one with that maximum length.

			$tracks =& $this->dvd_info_json['tracks'];

			$longest_track = 1;
			$longest_track_msecs = 0;

			foreach($tracks as $arr) {

				if($arr['msecs'] > $longest_track_msecs) {

					$longest_track = $arr['ix'];
					$longest_track_msecs = $arr['msecs'];

				}

			}

			return $longest_track;

		}

		public function getProviderID() {

			if(!$this->opened)
				return null;

			$dvd =& $this->dvd_info_json;

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
		public function getSize($format = 'MB') {

			if($this->debug)
				echo "! dvd->getSize($format)\n";

			if($this->is_iso()) {
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
			$gb_size = intval($mb_size / 1024);

			if($format == 'MB')
				return $mb_size;

			if($format == 'GB')
				return $gb_size;

		}

	}
?>
