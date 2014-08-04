<?php

	class DVDDrive {

		public $device;
		public $debug;
		public $arr_drive_status;

		function __construct($device = "/dev/dvd") {

			$this->setDevice($device);
			$this->debug = false;

			$this->arr_drive_status = array("", "CDS_NO_DISC", "CDS_TRAY_OPEN", "CDS_DRIVE_NOT_READY", "CDS_DISK_OK");

		}

		/** Hardware **/
		function setDevice($str) {
			$str = trim($str);
			if(is_string($str))
				$this->device = $str;
		}

		function getDevice() {

			return $this->device;

		}

		function set_debug($bool = true) {
			$this->debug = $bool;
		}

		/**
		 * Sleep until the drive is ready to access
		 */
		function wait_until_ready() {

			if($this->debug)
				echo "! drive::wait_until_ready(".$this->device.")\n";

			do {
				sleep(2);
			} while($this->is_ready() === false);

			return true;

		}

		/**
		 * Sleep until the drive is closed
		 */
		function wait_until_closed() {

			echo "! drive::wait_until_closed(".$this->device.")\n";

			do {
				$this->wait_until_ready();
			} while(!$this->is_closed());

			return true;

		}

		/**
		 * Sleep until the drive is open
		 */
		function wait_until_open() {

			echo "! drive::wait_until_open(".$this->device.")\n";

			do {
				$this->wait_until_ready();
			} while(!$this->is_open());

			return true;

		}

		/**
		 * Get the status of a DVD drive
		 */
		function get_status() {

			if($this->debug)
				echo "! drive::get_status(".$this->device.")\n";

			$command = "dvd_drive_status ".$this->device;
			exec($command, $arr, $return);

			if($this->debug)
				echo "! drive status: ".$this->arr_drive_status[$return]."\n";

			return $return;
		}

		/**
		 * Check if the drive has a DVD inside the tray
		 */
		function has_media() {

			if($this->debug)
				echo "! drive::has_media(".$this->device.")\n";

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
				echo "! drive::is_open(".$this->device.")\n";

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
				echo "! drive::is_closed(".$this->device.")\n";

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
				echo "! drive::is_ready(".$this->device.")\n";

			$status = $this->get_status();
			if($status != 3)
				return true;
			else
				return false;
		}

		/**
		 * Open the DVD tray
		 *
		 * Pass *all* control / decss / check of the drive to dvd_eject
		 */
		function open() {

			if($this->debug)
				echo "! drive::open(".$this->device.")\n";

			$cmd = "dvd_eject ".$this->device;
			passthru($cmd, $retval);

			if($retval === 0 || $retval === 2)
				return true;
			else
				return false;

		}

		/**
		 * Close the tray
		 *
		 * If the tray is closed and has media, dvd_eject will
		 * return a status of 2.
		 */
		function close() {

			if($this->debug)
				echo "! drive::close(".$this->device.")\n";

			$cmd = "dvd_eject -t ".$this->device;
			passthru($cmd, $retval);

			if($retval === 0)
				return true;
			else
				return false;

		}

	}

?>
