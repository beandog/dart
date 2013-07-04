<?

	class DVDDrive {
	
		private $device;
	
		function __construct($device = "/dev/dvd") {
		
			$this->setDevice($device);
			
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

		/**
		 * Check if the drive has a DVD inside the tray
		 *
		 * Note that this will return FALSE in either state:
		 * - the tray is closed and there is not a DVD inside
		 * - the tray is open
		 *
		 */
		function has_media() {

			$device = $this->getDevice();
			$exec = "udisks --show-info $device | grep \"has media\" | awk '{print $3}'";
			exec($exec, $arr, $return);
			$str = current($arr);
			$bool = (bool) $str;

			return $bool;

		}

		/**
		 * Check the status of the tray
		 *
		 * This function will only run *if* has_media returns false,
		 * since the binary will spit out an I/O error if there is
		 * something in the drive and the tray is closed.
		 */
		function is_open() {

			if($this->has_media())
				return false;
			else {
				$exec = "cddetect -d".$this->getDevice();
				exec($exec, $arr, $return);
				$str = current($arr);
				if($str == 'tray open!')
					return true;
				elseif($str == 'no disc!')
					return false;
			}

		}

		/**
		 * Helper function
		 */
		function is_closed() {
			if($this->is_open())
				return false;
			else
				return true;
		}

		/**
		 * Open the DVD tray
		 * If it is already opened, return false
		 */
		function open() {
			if($this->is_closed()) {
				// For good measure, unlock the eject button
				$exec = "eject -i off ".$this->getDevice();
				exec($exec, $arr, $return);
				$exec = "eject ".$this->getDevice();
				exec($exec, $arr, $return);
				exec('sync');
				// sleep(1);
				if($return == 0)
					return true;
			}
			return false;
		}
		
		function close() {
			if($this->is_open()) {
				$exec = "eject -t ".$this->getDevice();
				exec($exec, $arr, $return);

				// Sleep to allow the device to sync
				sleep(4);

				if($return == 0)
					return true;
				else
					return false;
			} else
				return true;

		}
		
		function mount() {
			if($this->is_open())
				$this->close_tray();
			shell::cmd("eject -t ".$this->getDevice());
			if($this->has_media()) {
				shell::cmd("mount ".$this->getDevice(), true, true, false, array(0, 32, 64));
				return true;
			} else
				return false;
		}
		
		function unmount() {
			shell::cmd("umount ".$this->getDevice());
		}

		function load_css($frames = 30) {
		
			$frames = abs(intval($frames));
			$exec = "mplayer dvd:// -dvd-device ".$this->getDevice()." -frames $frames -nosound -vo null -noconfig all";
			exec($exec);
		
		}
		
	}
?>
