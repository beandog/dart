<?php

	require_once 'config.local.php';
	require_once 'dart.device.php';

	class DVDDrive {

		public $device;
		public $debug;
		public $has_media = false;
		public $arr_drive_status = array('', 'CDS_NO_DISC', 'CDS_TRAY_OPEN', 'CDS_DRIVE_NOT_READY', 'CDS_DISK_OK', 'CDS_ERR_DEVTYPE', 'CDS_ERR_OPEN');

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
		 * Check if drive works and its status
		 */
		function load_wsl_drive() {

			global $ps1_dirname;

			$arg_device = escapeshellarg($this->device);

			$ps1_filename = $ps1_dirname."dvd_drive_status.ps1";

			$os = os();

			if($os == 'wsl')
				$cmd = "powershell.exe -File '$ps1_filename' $arg_device";
			elseif($os == 'windows')
				$cmd = "powershell.exe -InputFormat none -File $ps1_filename $arg_device";

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
		 * Get the status of a DVD drive
		 */
		function get_status() {

			global $os;

			$arg_device = escapeshellarg($this->device);

			echo "* Checking drive status ... ";

			if($os == 'wsl' || $os == 'windows') {

				$cmd = "powershell.exe -ExecutionPolicy Bypass -File /usr/local/bin/dvd_drive_status.ps1 $arg_device";

				exec($cmd, $arr);

				$str = implode("\n", $arr);

				$json = json_decode($str, true);

				if($json['has_media'] == 1) {
					$status = 'has media :D';
					$message = 'Drive is ready and has media';
					$ready = true;
					$retval = 4;
				} else {
					$status = 'device ready';
					$message = 'Drive is ready but there is no media';
					$retval = 0;
				}

			} elseif($os == 'tux') {

				$command = "dvd_drive_status $arg_device";
				exec($command, $arr, $retval);

				switch($retval) {

					case 0:
						$status = 'device ready';
						$message = 'Drive is ready but there is no media';
						break;

					case 1:
						$status = 'no disc';
						break;

					case 2:
						$status = 'tray open';
						$message = 'Tray is open';
						break;

					case 3:
						$status = 'drive not ready';
						$mesage = "Drive isn't ready, sleeping two seconds and trying again ...";
						$retry = true;
						break;

					case 4:
						$status = 'has media :D';
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

			}

			echo "$status\n";

			return $retval;

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

			$cmd = "dvd_eject $arg_device";

			if($this->debug)
				echo "* Executing $cmd\n";
			else
				$cmd .= " &> /dev/null";

			exec($cmd, $arr, $retval);

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

		function close_tray() {

			$arg_device = escapeshellarg($this->device);

			$os = os();

			if($os == 'tux')
				return $this->close_tux_tray();

			if($os == 'wsl' || $os == 'windows')
				return $this->close_wsl_tray();

		}

		/**
		 * Close the tray
		 *
		 * Use dvd_eject to close the tray and wait until it can be opened
		 *
		 * If the tray is closed and has media, dvd_eject will return a
		 * status of 2.
		 */
		function close_tux_tray($disc_type = '') {

			$arg_device = escapeshellarg($this->device);

			if($disc_type == 'dvd')
				$cmd = "dvd_eject -t -w -d $arg_device";
			else
				$cmd = "dvd_eject -t -w $arg_device";

			if($this->debug)
				echo "* Executing $cmd\n";
			else
				$cmd .= " &> /dev/null";

			passthru($cmd, $retval);

			if($retval === 0)
				return true;

			return false;

		}

		function close_wsl_tray($disc_type = '') {

			$arg_device = escapeshellarg($this->device);

			$powershell_script_file = $powershell_scripts_dir."dvd_eject.ps1";
			$cmd = "powershell.exe -File $powershell_script_file $arg_device";

			if($this->debug)
				echo "* Running $cmd\n";

			exec($cmd, $output, $retval);

			return true;

		}

	}

?>
