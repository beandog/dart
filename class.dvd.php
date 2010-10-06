<?

	class DVD {
	
		private $device;
		private $lsdvd;
		private $id;
		private $vmg_id;
		private $provider_id;
	
		function __construct($device = "/dev/dvd") {
		
			if(!is_null($device))
				$this->setDevice($device);
			else
				$this->setDevice = "";
			
		}
		
		/** Hardware **/
		function setDevice($str) {
			$str = trim($str);
			if(is_string($str))
				$this->device = $str;
		}
		
		function getDevice($escape = false) {
		
			$str = $this->device;
			
			if($escape)
				$str = escapeshellarg($str);
		
			return $str;
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
		
		/** Metadata **/
		
		private function disc_id() {
			$arr = shell::cmd("disc_id ".$this->getDevice(true));
			if(!empty($arr))
				$this->id = current($arr);
		}
		
		public function getID() {
			if(!$this->id)
				$this->disc_id();
			return $this->id;
		}
		
		private function lsdvd() {
		
			if(empty($this->lsdvd['output'])) {
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
		
		public function dump_iso($dest) {
		
			$dest = escapeshellarg($dest);
		
			$exec = "pv -pter -w 80 ".$this->getDevice()."  | dd of=$dest";
			
 			shell::cmd($exec);
		
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