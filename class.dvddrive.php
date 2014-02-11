<?php

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
		 * Sleep until the drive is ready to access
		 */
		function wait_until_ready() {

			if($this->debug)
				shell::stdout("! drive::wait_until_ready(".$this->device.")");

			while(!$this->is_ready())
				usleep(10000);

			return true;

		}

		/**
		 * Get the status of a DVD drive
		 */
		function get_status() {

			if($this->debug)
				shell::stdout("! drive::get_status(".$this->device.")");

			$cmd = "tray_status ".$this->getDevice();
			exec($cmd, $arr, $return);

			return $return;
		}

		/**
		 * Check if the drive has a DVD inside the tray
		 */
		function has_media() {

			if($this->debug)
				shell::stdout("! drive::has_media(".$this->device.")");

			$this->wait_until_ready();

			$status = $this->get_status();
			if($status == 4)
				return true;
			else
				return false;

		}

		/**
		 * Check if a tray is open
		 */
		function is_open() {

			if($this->debug)
				shell::stdout("! drive::is_open(".$this->device.")");

			$this->wait_until_ready();

			$status = $this->get_status();
			if($status == 2)
				return true;
			else
				return false;

		}

		/**
		 * Check if a tray is closed
		 */
		function is_closed() {

			if($this->debug)
				shell::stdout("! drive::is_closed(".$this->device.")");

			$this->wait_until_ready();

			$status = $this->get_status();
			if($status == 1 || $status == 4)
				return true;
			else
				return false;
		}

		/**
		 * Check if the drive is ready to access
		 */
		function is_ready() {
			if($this->debug)
				shell::stdout("! drive::is_ready(".$this->device.")");

			$status = $this->get_status();
			if($status != 3)
				return true;
			else
				return false;
		}

		/**
		 * Open the DVD tray
		 * If it is already opened, return false
		 */
		function open() {

			$this->wait_until_ready();

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
		 * README.devices: Running `ejcct -t` on my Memorex DVD drive sometimes
		 * throws "Buffer I/O error on device sr0, logical block 512", so just
		 * ignore it.
		 *
		 * README.eject I think there *may* be a bug where, after manually closing
		 * a tray and the device is polling, and then running 'eject -t', the
		 * drive opens up and then closes.
		 */
		function close() {

			if($this->debug)
				shell::stdout("! drive::close(".$this->device.")");

			$this->wait_until_ready();

			if($this->is_open()) {
				$cmd = "eject -t ".$this->getDevice()." 2>&1 > /dev/null";
				system($cmd);
			}

			return true;

		}

		function mount() {

			$this->wait_until_ready();

			if($this->is_open())
				$this->close_tray();

			if($this->has_media()) {
				shell::cmd("mount ".$this->getDevice(), true, true, false, array(0, 32, 64));
				return true;
			} else
				return false;
		}

		function unmount() {
			$this->wait_until_ready();
			shell::cmd("umount ".$this->getDevice());
		}

		// Use Handbrake to access the device and scan for media
		// Handbrake seems to be much more patient regarding
		// polling a tray and waiting to see if it is loaded.
		function load_css() {

			if($this->debug)
				shell::stdout("! drive::load_css(".$this->device.")");

			$this->wait_until_ready();

			if(!$this->is_closed())
				$this->close_tray();

			$cmd = "handbrake --scan -i ".$this->getDevice()." 2>&1 > /dev/null";
			exec($cmd, $arr, $return);

			return $return;

		}

	}
?>
