<?

	class DVD {
	
		private $device;
		private $lsdvd;
		private $id;
		private $serial_id;
		private $vmg_id;
		private $provider_id;
		private $is_iso;
	
		function __construct($device = "/dev/dvd") {
		
			$this->setDevice($device);
			
		}
		
		/** Hardware **/

		function setDevice($str) {
			$str = trim($str);
			if(is_string($str))
				$this->device = $str;

			$dirname = dirname($str);
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
			$arr = shell::cmd("dvd_id ".$this->getDevice(true));
			$var = current($arr);
			if(strlen($var) == 32)
				$this->id = $var;
		}

		// Use serial number from HandBrake 0.9.9
		private function serial_id() {
		
			$exec = "handbrake --scan -i ".$this->getDevice(true)." 2>&1";
			exec($exec, $arr, $return);
			$match = preg_grep("/.*Serial.*/", $arr);
			$explode = explode(' ', current($match));
			$str = end($explode);

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
				
				$this->setTitle((string)$this->sxe->title);
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
		public function load_css($use_lsdvd = false) {
		
			if($use_lsdvd)
				$this->lsdvd(true);
			else {
				$exec = "mplayer dvd:// -dvd-device ".escapeshellarg($this->getDevice())." -frames 60 -nosound -vo null -noconfig all 2>&1 > /dev/null";
				exec($exec, $arr, $return);
			}

			return $return;
		
		}
		
		public function dump_iso($dest, $method = 'readdvd', $display_output = false) {
		
			$dest = shell::escape_string($dest);
			$device = $this->getDevice();
		
			if($method == 'readdvd') {

				$tmpfile = tempnam(sys_get_temp_dir(), "readdvd");
				$cmd = "readdvd -d $device -o $dest 2>&1 > /dev/null";
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
		
		public function getTitle() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->title;
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
		 */
		public function getSize($format = 'MB') {
		
			$device = $this->getDevice();

			if($this->is_iso()) {
				$exec = "stat -c %s $device";
			} else {
				$exec = "blockdev --getsize64 $device";
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
