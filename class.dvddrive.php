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
			sleep(1);
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
				$exec = "cddetect -d".$this->getDevice()." 2> /dev/null";
				exec($exec, $arr, $return);
				sleep(1);

				$str = current($arr);
				if($str == 'tray open!')
					return true;
				else
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

				$exec = "eject ".$this->getDevice()." &";
				exec($exec);

				return true;
			}
			return false;
		}
		
		/**
		 * Close the tray
		 *
		 * README: Because of how a DVD tray operates, I've yet to find a way
		 * to accurately detect if the tray is closed *and* ready to access.
		 * As such, the easiest approach is also the simplest: just wait a
		 * few seconds after closing the tray OR to have HandBrake run a scan
		 * on it (testing this 2nd one now).
		 *
		 * README.devices: Running `ejcct -t` on my Memorex DVD drive sometimes
		 * throws "Buffer I/O error on device sr0, logical block 512", so just
		 * ignore it.
		 */
		function close() {

			if($this->is_open()) {
				$exec = "eject -t ".$this->getDevice();
				$this->load_css();
			}

			return true;

		}
		
		function mount() {
			if($this->is_open())
				$this->close_tray();
			shell::cmd("eject -t ".$this->getDevice());
			sleep(1);

			if($this->has_media()) {
				shell::cmd("mount ".$this->getDevice(), true, true, false, array(0, 32, 64));
				return true;
			} else
				return false;
		}
		
		function unmount() {
			shell::cmd("umount ".$this->getDevice());
		}

		// Use Handbrake to access the device and scan for media
		// Handbrake seems to be much more patient regarding
		// polling a tray and waiting to see if it is loaded.
		function load_css($frames = 30) {
		
			// $frames = abs(intval($frames));
			// $cmd = "mplayer dvd:// -dvd-device ".$this->getDevice()." -frames $frames -nosound -vo null -noconfig all 2>&1 > /dev/null";
			$cmd = "handbrake --scan -i ".$this->getDevice()." 2>&1 > /dev/null";
			exec($cmd, $arr, $return);
			sleep(1);
			return $return;
		
		}
		
	}
?>
