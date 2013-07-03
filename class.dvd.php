<?

	class DVD {
	
		private $device;
		private $lsdvd;
		private $id;
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

			$pathinfo = pathinfo($device);
			if($pathinfo['extension'] == "iso")
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

		/**
		 * Poll the drive for a ready status and loaded
		 *
		 */
		function cddetect($accept_drive_not_ready = false) {

			$exec = "cddetect -d".$this->getDevice()." 2>/dev/null";
			exec($exec, $arr, $return);
			
			if($return === 0 || ($accept_drive_not_ready && current($arr) == "drive not ready!"))
				return true;
			else
				return false;

		}
		
		function close_tray() {
			// Ignore exit code if it dies
			shell::cmd("eject -t ".$this->getDevice(), false, true);
		}
		
		function eject() {
			// Ignore exit code if it dies
			shell::cmd("eject ".$this->getDevice(), false, true);
		}
		
		function mount() {
			shell::cmd("eject -t ".$this->getDevice());
			shell::cmd("mount ".$this->getDevice(), true, true, false, array(0, 32, 64));
		}
		
		function unmount() {
			shell::cmd("umount ".$this->getDevice());
		}

		function is_iso() {
			return $this->is_iso;
		}
		
		/** Metadata **/
		
		private function disc_id() {
			$arr = shell::cmd("dvd_id ".$this->getDevice(true));
			$var = current($arr);
			if(strlen($var) == 32)
				$this->id = $var;
		}
		
		public function getID() {
			if(!$this->id)
				$this->disc_id();
			return $this->id;
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
			else
				$exec = "mplayer dvd:// -dvd-device ".escapeshellarg($this->getDevice())." -frames 60 -nosound -vo null -noconfig all";
			shell::cmd($exec);
		
		}
		
		public function dump_iso($dest) {
		
			$dest = shell::escape_string($dest);
		
			$exec = "pv -pter -w 80 ".$this->getDevice()." | dd of=$dest 2> /dev/null";
			$exec .= '; echo ${PIPESTATUS[*]}';
			
			exec($exec, $arr);
			
			foreach($arr as $exit_code)
				if(intval($exit_code))
					return false;
			
			return true;
			
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
		
		public function getSize() {
		
			$device = $this->getDevice();
			
			if(substr($device, 0, 4) == "/dev") {
			
				$exec = "/bin/df $device | tail -n 1 | tr -s '[:blank:]' '\\t' | cut -f 2";
				
				$var = current(shell::cmd($exec));
				
				return $var;
			
			}
			
			return null;
		
		}
		
	}
?>
