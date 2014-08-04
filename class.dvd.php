<?php

	class DVD {

		private $device;
		private $lsdvd;
		private $id;
		private $serial_id;
		private $provider_id;
		private $is_iso;
		private $sxe;
		private $debug;
		private $num_tracks = 0;

		function __construct($device = "/dev/dvd") {

			$this->setDevice($device);

		}

		function setDebug($bool = false) {
			$this->debug = (bool)$bool;
		}

		/** Hardware **/

		function setDevice($str) {
			$str = trim($str);
			if(is_string($str)) {
				$str = realpath($str);
				$this->device = $str;
			}

			$dirname = dirname(realpath($str));
			if($dirname != "/dev")
				$this->is_iso = true;
			else
				$this->is_iso = false;

		}

		function getDevice($escape = false) {

			$str = $this->device;

			if($escape)
				$str = escapeshellarg($str);

			return $str;
		}

		function is_iso() {
			return $this->is_iso;
		}

		/** Metadata **/

		// Use disc_id binary from libdvdread
		private function disc_id() {

			if($this->debug)
				echo "! dvd->disc_id()\n";

			$arr = command("dvd_id ".$this->getDevice(true));
			$var = current($arr);
			if(strlen($var) == 32)
				$this->id = $var;
		}

		// Use serial number from HandBrake 0.9.9
		private function serial_id() {

			if($this->debug)
				echo "! dvd->serial_id()\n";

			$exec = "handbrake --scan -i ".$this->getDevice(true)." 2>&1";
			exec($exec, $arr, $return);
			$match = preg_grep("/.*Serial.*/", $arr);
			$explode = explode(' ', current($match));
			$str = end($explode);
			$str = strtolower($str);

			$this->serial_id = $str;

			return $str;
		}

		public function getID() {
			if(!$this->id)
				$this->disc_id();
			return $this->id;
		}

		public function getSerialID() {
			if(!$this->serial_id)
				$this->serial_id();
			return $this->serial_id;
		}

		private function lsdvd($force = false) {

			if($this->debug)
				echo "! dvd->lsdvd()\n";

			if(empty($this->lsdvd['output']) || $force) {
				$str = "lsdvd -Ox -v -a -s -c ".$this->getDevice(true);
				$arr = command($str);
				$str = implode("\n", $arr);

				// Fix broken encoding on langcodes, standardize output
				$str = str_replace('Pan&Scan', 'Pan&amp;Scan', $str);
				$str = str_replace('P&S', 'P&amp;S', $str);
				$str = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $str);

				$this->xml = $str;

				$this->sxe = simplexml_load_string($str) or die("Couldn't parse lsdvd XML output");

				// Use fread() instead
				// $this->setTitle((string)$this->sxe->title);

				$this->setLongestTrack((int)$this->sxe->longest_track);

				$this->provider_id = (string)$this->sxe->provider_id;

				foreach($this->sxe->track as $track) {
					$this->num_tracks++;
				}

			}

		}

		public function dump_iso($dest, $method = 'ddrescue') {

			if($this->debug)
				echo "! dvd->dump_iso($dest, $method)\n";

			$dest = escapeshellarg($dest);
			$device = $this->getDevice();

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

				$command = "ddrescue -b 2048 -n $device $dest $logfile";
				passthru($command, $return);

				$return = intval($return);

				if($return) {
					return false;
				} else {
					return true;
				}

			} elseif($method == 'pv') {
				$exec = "pv -pter -w 80 ".$this->getDevice()." | dd of=$dest 2> /dev/null";
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
			$device = $this->getDevice();

			$exec = "dvd_backup_ifo $device &> /dev/null";

			$arr = array();

			exec($exec, $arr, $return);

			$return = intval($return);

			if($return) {
				return false;
			} else {
				return true;
			}

		}

		private function setTitle($str) {
			$this->title = $str;
		}

		/**
		 * Get the DVD title
		 *
		 * This reads directly from the device to get the title
		 * instead of using lsdvd(), which should in some cases
		 * save a call to the hardware device.
		 *
		 * Also, this is much faster :)
		 */
		public function getTitle() {

			$dvd = fopen($this->getDevice(), 'rb');
			if($dvd === false)
				die("Could not open device ".$this->getDevice()." for reading");

			stream_set_blocking($dvd, 0);

			$fseek = fseek($dvd, 32808, SEEK_SET);

			if($fseek === -1) {
				fclose($dvd);
				die("Could not seek on device ".$this->getDevice());
			}

			$str = fread($dvd, 32);

			if(strlen($str) != 32) {
				fclose($dvd);
				die("Empty string length for title on ".$this->getDevice());
			}

			fclose($dvd);

			$title = rtrim($str);

			$this->setTitle($title);

			return $title;
		}

		public function getXML() {
			if(!$this->xml)
				$this->lsdvd();
			return $this->xml;
		}

		/** Tracks **/
		public function getNumTracks() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->num_tracks;
		}

		private function setLongestTrack($int) {
			$this->longest_track = $int;
		}

		public function getLongestTrack() {
			if(!$this->sxe)
				$this->lsdvd();

			return $this->longest_track;
		}

		public function getProviderID() {

			if(!$this->sxe)
				$this->lsdvd();

			return $this->provider_id;

		}

		/**
		 * Get the disc file size using blockdev
		 * The command returns the size in MB
		 *
		 * TODO: look at using PHP stat() instead of blockdev
		 */
		public function getSize($format = 'MB') {

			if($this->debug)
				echo "! dvd->getSize($format)\n";

			$device = realpath($this->getDevice());

			if($this->is_iso()) {
				$exec = "stat -c %s ".escapeshellarg($device);
			} else {
				$exec = "blockdev --getsize64 ".escapeshellarg($device)." 2> /dev/null";
			}

			$b_size = current(command($exec));

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
