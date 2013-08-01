<?

	class DVDDrive {

		private $device;
		private $debug;

		function __construct($device = "/dev/dvd") {

			$this->setDevice($device);
			$this->debug = false;

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

		function set_debug($bool = true) {
			$this->debug = $bool;
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

			if($this->debug)
				shell::stdout("! drive::has_media(".$this->device.")");

			if($this->is_open())
				return false;

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

			if($this->debug)
				shell::stdout("! drive::is_open(".$this->device.")");

			$cmd = "trayopen ".$this->getDevice();
			system($cmd, $return);

			if($return)
				return false;
			else
				return true;

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
		 *
		 * README.eject I think there *may* be a bug where, after manually closing
		 * a tray and the device is polling, and then running 'eject -t', the
		 * drive opens up and then closes.
		 */
		function close($naptime = 30) {

			if($this->debug)
				shell::stdout("! drive::close(".$this->device.")");

			if($this->is_open()) {
				$cmd = "eject -t ".$this->getDevice()." 2>&1 > /dev/null";
				system($cmd);
			}

			$naptime = abs(intval($naptime));
			if($this->debug)
				shell::stdout("! Taking a nap for $naptime seconds");
			if($naptime)
				sleep($naptime);

			// udisks should be able to poll the tray after a nap
			// and give an accurate response.  Also, try to only
			// run load_css if there is media in there, to avoid
			// kernel complaints (but do it manually).
			if($this->has_media() && $naptime) {
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
		function load_css() {

			if($this->debug)
				shell::stdout("! drive::load_css(".$this->device.")");

			$cmd = "handbrake --scan -i ".$this->getDevice()." 2>&1 > /dev/null";
			exec($cmd, $arr, $return);
			sleep(1);
			return $return;

		}

	}
?>
