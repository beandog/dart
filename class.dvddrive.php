<?php

	require_once 'config.local.php';
	require_once 'dart.device.php';

	class DVDDrive {

		public $device;
		public $debug;
		public $disc_type = '';
		public $has_media = false;
		public $arr_drive_status = array("", "CDS_NO_DISC", "CDS_TRAY_OPEN", "CDS_DRIVE_NOT_READY", "CDS_DISK_OK", "CDS_ERR_DEVTYPE", "CDS_ERR_OPEN");

		/**
		 * Load the class. Assume it's a device, and return false otherwise.
		 */
		function __construct($device, $debug) {

			if($debug)
				echo "[DVDDrive]\n";

			$device_type = get_device_type($device);

			if($debug)
				echo "* Device type: $device_type\n";

			$this->device = $device;

			$this->debug = boolval($debug);

			$arg_device = escapeshellarg($device);

			if($debug)
				echo "* Accessing DVD drive $arg_device\n";

			if($device_type == 'windows')
				return true;

			if($device_type == 'device' && file_exists($device))
				return true;

			if($device_type != 'device')
				echo "* $arg_device is not a device\n";

			if(!file_exists($device))
				echo "* $arg_device doesn't exist\n";

			return false;

		}

		function load_drive() {

			$os = os();

			if($os == 'tux')
				return $this->load_tux_drive();
			elseif($os == 'wsl' || $os == 'windows')
				return $this->load_wsl_drive();
			else
				return false;

		}

		function get_tux_drive_status() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* Getting Linux optical drive status for $arg_device\n";

			$cmd = "dvd_drive_status $arg_device &> /dev/null";
			exec($cmd, $output, $retval);

			$drive_status = $this->arr_drive_status[$retval];
			$d_drive_status = escapeshellarg($drive_status);

			if($this->debug)
				echo "* Drive status: $d_drive_status";

			return $retval;

		}

		/**
		 * Do everything to get drive ready, including retrying if busy, and return result
		 */
		function load_tux_drive() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* Loading Linux optical drive $arg_device\n";

			$retval = get_tux_drive_status();

			$message = '';
			$ready = false;
			$retry = false;

			switch($retval) {

				case 0:
					$status = 'device ready';
					$message = 'Drive is ready but there is has media';
					break;

				case 1:
					$status = 'no disc';
					break;

				case 2:
					$status = 'tray open';
					$message = 'Try is open, close the tray manually\n';
					break;

				case 3:
					$status = 'drive not ready';
					$mesage = "Drive isn't ready, sleeping two seconds and trying again ...";
					$retry = true;
					break;

				case 4:
					$status = 'loaded';
					$message = 'Drive is ready and has media';
					$ready = true;
					break;

				case 5:
					$status = 'wrong device type';
					$message = "Device is not an optical drive!";
					break;

				case 6:
					$status = 'error opening';
					$message = "Drive couldn't be opened, sleeping two seconds and ttrying again";
					$retry = true;
					break;

			}

			if($ready)
				$this->has_media = true;

			if($ready == true && $this->debug) {
				echo "* Device $arg_device is ready and loaded!\n";
				return true;
			}

			if($ready == true)
				return true;

			if($retry == true) {

				$max_retries = 5;

				if($debug)
					echo "* Waiting for drive to be ready ... max $max_retries tries\n";
				$num_retries = 0;

				while($num_retries < $max_retries) {

					if($this->debug)
						echo "* Attempt # ".($num_retries + 1)." ...\n";

					$num_retries++;
					sleep(1);

					$retval = $this->get_tux_drive_status();

					if($retval == 3) {
						$ready = true;
						break;
					}

				}

				if($ready && $this->debug)
					echo "* Tried $num_tries total\n";

				if(!$ready) {
					echo "* Waiting for drive to be ready failed, quitting\n";
					return false;
				}

			}

			if($this->debug)
				echo "* Drive status: $status\n";

			if($message)
				echo "* $message\n";

			return false;

		}

		/**
		 * Check if drive works and its status
		 */
		function load_wsl_drive() {

			global $ps1_dirname;

			$arg_device = escapeshellarg($this->device);

			$ps1_filename = $ps1_dirname."dvd_drive_status.ps1";
			$cmd = "powershell.exe -File '$ps1_filename' $arg_device";

			if($this->debug)
				echo "* Running $cmd\n";

			exec($cmd, $output, $retval);

			if($retval)
				return false;

			$json = implode("\n", $output);

			if($this->debug)
				echo "$json\n";

			if(!json_validate($json)) {
				echo "* Could not parse output from $cmd\n";
				return false;
			}

			$arr_json = json_decode($json, true);

			$this->has_media = $arr_json['has_media'];

			if($this->debug && $arr_json['has_media']) {
				echo "* Device has media\n";
				return true;
			}

			if($arr_json['has_media'])
				return true;

			if($this->debug && $arr_json['status'] == 'OK') {
				echo "* Device status reports 'OK'\n";
				return true;
			} elseif($this->debug && $arr_json['status'] != 'OK') {
				$d_drive_status = escapeshellarg($arr_json['status']);
				echo "* Device status: $d_drive_status\n";
				return false;
			}

			return false;

		}

		/** Hardware **/

		/**
		 * Basic check to see if drive is accessible
		 */
		function access_device() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::access_device($arg_device)\n";

			$cmd = "dvd_drive_status $arg_device &> /dev/null";
			exec($cmd, $output, $retval);

			if($this->debug)
				echo "* drive::dvd_drive_status: ".current($output)."\n";

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

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::wait_until_ready($arg_device)\n";

			do {
				sleep(2);
			} while($this->is_ready() === false);

			return true;

		}

		/**
		 * Sleep until the drive is closed
		 */
		function wait_until_closed() {

			$arg_device = escapeshellarg($this->device);

			echo "* drive::wait_until_closed($arg_device)\n";

			do {
				$this->wait_until_ready();
			} while(!$this->is_closed());

			return true;

		}

		/**
		 * Sleep until the drive is open
		 */
		function wait_until_open() {

			$arg_device = escapeshellarg($this->device);

			echo "* drive::wait_until_open($arg_device)\n";

			do {
				$this->wait_until_ready();
			} while(!$this->is_open());

			return true;

		}

		/**
		 * Get the status of a DVD drive
		 */
		function get_status() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::get_status($arg_device)\n";

			$command = "dvd_drive_status $arg_device";
			exec($command, $arr, $retval);

			if($this->debug)
				echo "* drive status: ".$this->arr_drive_status[$retval]."\n";

			return $retval;
		}

		/**
		 * Check if the drive has a DVD inside the tray
		 */
		function has_media() {

			$os = os();
			if($os == 'wsl' || $os == 'windows')
				return $this->has_media;

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::has_media($arg_device)\n";

			$this->wait_until_ready();

			$status = $this->get_status();

			if($status == 4) {
				$this->has_media = true;
				return true;
			}

			return false;

		}

		/**
		 * Check if a tray is open
		 */
		function is_open() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::is_open($arg_device)\n";

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

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::is_closed($arg_device)\n";

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

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* drive::is_ready($arg_device)\n";

			$status = $this->get_status();

			if($status != 3)
				return true;
			else
				return false;
		}

		/**
		 * Open the DVD tray
		 */
		function eject() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* Ejecting drive $arg_device\n";

			$os = os();

			if($os == 'tux')
				return $this->eject_tux_drive();

			if($os == 'wsl' || $os == 'windows')
				return $this->eject_wsl_drive();

			return false;

		}

		function eject_tux_drive() {

			$arg_device = escapeshellarg($this->device);

			$cmd = "eject $arg_device";

			if($this->debug)
				echo "* Executing $cmd\n";

			exec($cmd, $arr, $retval);

			if($this->debug)
				echo "* eject retval: $retval\n";

			if($retval === 0 || $retval === 2)
				return true;

			return false;

		}

		function eject_wsl_drive() {

			$arg_device = escapeshellarg($this->device);

			$cmd = "powershell.exe -Command \"(New-Object -comObject Shell.Application).Namespace(17).ParseName($arg_device).InvokeVerb('Eject')\"";

			if($this->debug)
				echo "* Executing $cmd\n";

			shell_exec($cmd);

			return true;

		}

		/**
		 * Close the tray
		 *
		 * If the tray is closed and has media, dvd_eject will
		 * return a status of 2.
		 */
		function close() {

			$arg_device = escapeshellarg($this->device);

			if($this->debug)
				echo "* Trying to close $arg_device\n";

			$os = os();

			if($os == 'tux')
				return $this->close_tux_tray();

			if($os == 'wsl' || $os == 'windows')
				return $this->close_wsl_tray();


		}

		function close_tux_tray() {

			$arg_device = escapeshellarg($this->device);

			$cmd = "eject -t $arg_device";

			if($debug)
				echo "* Executing $cmd\n";

			passthru($cmd, $retval);

			if($retval === 0)
				return true;

			return false;

		}

		function close_wsl_tray() {

			$arg_device = escapeshellarg($this->device);

			$powershell_script_file = $powershell_scripts_dir."dvd_eject.ps1";
			$cmd = "powershell.exe -File $powershell_script_file $arg_device";

			if($this->debug)
				echo "* Running $cmd\n";

			exec($cmd, $output, $retval);

			return true;

		}

		/**
		 * Get the optical disc type - DVD or Blu-ray
		 *
		 * Currently unused, dart.functions.php has get_disc_type, but keeping this here
		 * if needed.
		 *
		 */
		function disc_type() {

			$arg_device = escapeshellarg($device);

			if($this->debug)
				echo "* drive::disc_type($arg_device)\n";

			$os = os();

			if($os == 'wsl' || $os == 'windows') {
				echo "* Unsupported on WSL, assuming DVD\n";
				return 'dvd';
			}

			$command = "disc_type $arg_device 2> /dev/null";
			exec($command, $arr, $retval);

			$disc_type = current($arr);

			if($retval || $disc_type == '') {
				if($this->debug)
					echo "* disc_type returned no type\n";
				return '';
			}

			return $disc_type;

		 }

	}

?>
