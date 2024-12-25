<?php

	class DVDDrive {

		public $device;
		public $debug;
		public $binary = '/usr/bin/dvd_drive_status';
		public $dvd_eject_binary = '/usr/bin/dvd_eject';
		public $arr_drive_status;
		public $disc_type;

		function __construct($device = "/dev/dvd") {

			$this->device = realpath($device);

			$this->debug = false;

			$this->arr_drive_status = array("", "CDS_NO_DISC", "CDS_TRAY_OPEN", "CDS_DRIVE_NOT_READY", "CDS_DISK_OK", "CDS_ERR_DEVTYPE", "CDS_ERR_OPEN");

			if(file_exists('/usr/local/bin/dvd_drive_status'))
				$this->binary = '/usr/local/bin/dvd_drive_status';
			if(file_exists('/usr/local/bin/dvd_eject'))
				$this->dvd_eject_binary = '/usr/local/bin/dvd_eject';

			$this->disc_type = '';

		}

		/** Hardware **/

		function set_debug($bool = true) {
			$this->debug = $bool;
		}

		/**
		 * Basic check to see if drive is accessible
		 */
		function access_device() {

			if($this->debug)
				echo "* drive::access_device(".$this->device.")\n";

			$cmd = $this->binary." ".$this->device." &> /dev/null";
			passthru($cmd, $retval);

			if($retval == 5) {
				if($this->debug)
					echo "* drive::access_device: device exists, but is not a DVD drive\n";
				return false;
			} elseif($retval == 6) {
				if($this->debug)
					echo "* drive::access_device: cannot access device\n";
				return false;
			} elseif($retval == 7) {
				if($this->debug)
					echo "* drive::access_device: cannot find a device\n";
				return false;
			}

			return true;

		}

		/**
		 * Sleep until the drive is ready to access
		 */
		function wait_until_ready() {

			if($this->debug)
				echo "* drive::wait_until_ready(".$this->device.")\n";

			do {
				sleep(2);
			} while($this->is_ready() === false);

			return true;

		}

		/**
		 * Sleep until the drive is closed
		 */
		function wait_until_closed() {

			echo "* drive::wait_until_closed(".$this->device.")\n";

			do {
				$this->wait_until_ready();
			} while(!$this->is_closed());

			return true;

		}

		/**
		 * Sleep until the drive is open
		 */
		function wait_until_open() {

			echo "* drive::wait_until_open(".$this->device.")\n";

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
				echo "* drive::get_status(".$this->device.")\n";

			$arg_device = escapeshellarg($this->device);
			$command = $this->binary." $arg_device";
			$return = 0;
			exec($command, $arr, $return);

			if($this->debug)
				echo "* drive status: ".$this->arr_drive_status[$return]."\n";

			return $return;
		}

		/**
		 * Check if the drive has a DVD inside the tray
		 */
		function has_media() {

			if($this->debug)
				echo "* drive::has_media(".$this->device.")\n";

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
				echo "* drive::is_open(".$this->device.")\n";

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
				echo "* drive::is_closed(".$this->device.")\n";

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
				echo "* drive::is_ready(".$this->device.")\n";

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
		function eject() {

			if($this->debug)
				echo "* drive::eject(".$this->device.")\n";

			$arg_device = escapeshellarg($this->device);

			// dvd_eject has a race condition if tray is closed, immediately mounted, and then ejected.
			// It will also check if it's mounted, but do it here so it skips that
			// process completely.
			// This is a workaround until I can debug the race condition in dvd_eject
			$cmd = "umount $arg_device &> /dev/null";
			exec($cmd);

			$cmd = $this->dvd_eject_binary." $arg_device";
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
				echo "* drive::close(".$this->device.")\n";

			$arg_device = escapeshellarg($this->device);
			$cmd = $this->dvd_eject_binary." -t $arg_device";
			passthru($cmd, $retval);

			if($retval === 0)
				return true;
			else
				return false;

		}

		/**
		 * Get the optical disc type - DVD or Blu-ray
		 *
		 */
		// FIXME - don't say it's a DVD by default, since it may be looking at a non-disc
		// See dart.functions.php for same code. Not sure where this is called, and could be a
		// major cleanup, so skipping for now
		function disc_type() {

			if($this->debug)
				echo "* drive::disc_type(".$this->device.")\n";

			$arg_device = escapeshellarg($this->device);
			$command = "udevadm info $arg_device";
			$return = 0;
			exec($command, $arr, $return);

			if(in_array("E: ID_CDROM_MEDIA_DVD=1", $arr))
				$this->disc_type = "dvd";
			elseif(in_array("E: ID_CDROM_MEDIA_BD=1", $arr))
				$this->disc_type = "bluray";
			else
				$this->disc_type = "dvd";

			return $this->disc_type;

		 }

	}

?>
