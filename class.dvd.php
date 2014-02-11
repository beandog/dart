<?php

	class DVD {

		private $device;
		private $lsdvd;
		private $id;
		private $serial_id;
		private $vmg_id;
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

			$arr = shell::cmd("dvd_id ".$this->getDevice(true));
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
				$arr = shell::cmd($str);
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

				$this->vmg_id = (string)$this->sxe->vmg_id;
				$this->provider_id = (string)$this->sxe->provider_id;

				foreach($this->sxe->track as $track) {
					$this->num_tracks++;
				}

			}

		}

		/**
		 * Play one frame of the DVD device
		 *
		 * This is so the DVD drive doesn't lock up when trying
		 * to access the disc.  By decoding the CSS once,
		 * it should prevent problems. :)
		 */
		public function load_css($program = 'mplayer') {

			if($this->debug)
				echo "! dvd->load_css()\n";

			if($program == 'lsdvd') {
				$this->lsdvd(true);
				return true;
			}

			if($program == 'mplayer') {
				$exec = "mplayer dvd:// -dvd-device ".escapeshellarg($this->getDevice())." -frames 60 -nosound -vo null -noconfig all 2>&1 > /dev/null";
				exec($exec, $arr, $return);
				return $return;
			}

			return $false;

		}

		public function dump_iso($dest, $method = 'ddrescue') {

			if($this->debug)
				echo "! dvd->dump_iso($dest, $method)\n";

			$dest = shell::escape_string($dest);
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

				$cmd = "ddrescue -b 2048 -n $device $dest $logfile";
				passthru($cmd, $return);

				$return = intval($return);

				if($return) {
					return false;
				} else {
					return true;
				}

			} elseif($method == 'readdvd') {

				// readdvd README
				// Sadly, three strikes and it's out. :(
				// 1. It sometimes hangs while being run in the
				//    background, but will continue reading the DVD.
				//    When run in the foreground (in those same cases)
				//    it sometimes works.  I originally thought that it
				//    was somehow related to readdvd expecting user input,
				//    but it behaves good on some DVDs, and poorly on others
				// 2. When it does hang, it's usually because it can't read a
				//    title because it has hung on dvdcss.  Other programs
				//    such as handbrake, etc., can read it just fine.
				// 3. I tried compiling it myself from source, but it would
				//    not link against it's own libraries. :(
				// Altogether, it's a binary I *want* to work, but it's
				// not really coming together.  I consistently had better luck
				// with pv.
				$tmpfile = tempnam(sys_get_temp_dir(), "readdvd");
				// $cmd = "readdvd -d $device -o $dest 2>&1 > /dev/null";
				$outfile = "$dest.readdvd.out";
				$pidfile = "$dest.readdvd.pid";
				// $cmd = "( nohup readdvd -d $device -o $dest > /dev/null 2>&1 > $outfile & echo $! > $pidfile ) > /dev/null 2>&1";
				$cmd = "nohup readdvd -d $device -o $dest > /dev/null 2>&1 > $outfile & echo $! > $pidfile";
				exec($cmd, $arr, $return);
				shell::stdout("* System command: $cmd");
				die;
				system($cmd, $return);
				if(intval($return))
					return false;
				else
					return true;

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

		public function getVMGID() {

			if(!$this->sxe)
				$this->lsdvd();

			return $this->vmg_id;

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

			$device = $this->getDevice();

			if($this->is_iso()) {
				$exec = "stat -c %s $device";
			} else {
				$exec = "blockdev --getsize64 $device 2> /dev/null";
			}

			$b_size = current(shell::cmd($exec));

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
