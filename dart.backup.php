<?php
	/**
	 * --backup
	 *
	 * Copy a disc's content to the harddrive
	 */

	// Continue if we can access the device (source file)
	// and it has a database record.
	if($access_device && $dvds_model_id && $opt_backup && !$broken_dvd && !$opt_makemkv) {

		$backup_passed = null;

		if($arg_backup_dir) {
			$cwdir = getcwd();
			chdir($arg_backup_dir);
		}

		/** ISO Information **/
		echo "[ISO]\n";

		// Get the collection ID to prefix the filename
		// of the ISO, for easy indexing by cartoons, movies, etc.
		$collection_id = $dvds_model->get_collection_id();
		$collection_id = intval($collection_id);

		// Get the series ID and title
		$series_id = $dvds_model->get_series_id();
		$series_title = '';
		if($series_id) {
			$series_model = new Series_Model($series_id);
			$series_title = $series_model->title;
			$collection_title = $series_model->get_collection_title();
			$nsix = $series_model->nsix;
		} else {
			$collection_title = "";
			$nsix = 'NSIX';
		}

		// Get the series title
		$str = strtoupper($series_title);
		$str = preg_replace("/[^0-9A-Z \-_.]/", '', $str);
		$str = str_replace(' ', '_', $str);
		$str = substr($str, 0, 28);

		// Get the target filename
		$target_iso = str_pad($collection_id, 1, '0');
		$target_iso .= ".".str_pad($series_id, 3, '0', STR_PAD_LEFT);
		$target_iso .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		$target_iso .= ".$nsix";
		$target_iso .= ".iso";

		$isos_dir = $backup_dir;
		$target_iso = realpath($isos_dir).'/'.$target_iso;
		$target_rip = realpath($isos_dir).'/'.basename($target_iso, '.iso').".R1p";

		$display_iso = basename($target_iso);
		echo "* Filename: $display_iso\n";

		/** Filename and filesystem operations **/

		// See if the target filename exists. This
		// is for the source regardless of whether it is
		// a block device or an ISO.
		clearstatcache();
		$target_iso_exists = file_exists($target_iso);

		// Operations on a block device or ISO
		if($device_is_hardware || $device_is_iso) {

			$target_rip_exists = file_exists($target_rip);

			// Check if the drive is already ripping
			$is_ripping = false;
			$cmd = "pgrep -l '^(dvd_backup|bluray_backup)' -a";
			$str = trim(shell_exec($cmd));
			if(str_contains($str, $device)) {
				echo "* Backup in progress for $device to ".basename($target_rip)."\n";
				goto next_disc;
			}

			// If we have access to the device, and we
			// are trying to dump it, and the output filename
			// already exists, just eject the drive.
			if($target_iso_exists && $opt_backup) {
				echo "* Filename: $display_iso exists\n";
			}

			// See if backing up individual title sets
			if($dvds_model->has_max_tracks())
				$opt_title_sets = true;
			else
				$opt_title_sets = false;

			// Dump the DVD contents to an ISO on the filesystem
			if(!$target_iso_exists && !$is_ripping && $opt_backup && $access_device && !$opt_title_sets) {

				$arg_device = escapeshellarg($device);
				$arg_target_rip = escapeshellarg($target_rip);
				$arg_target_iso = escapeshellarg($target_iso);

				$logfile = "/tmp/".str_replace('.iso', '.log', basename($target_iso));
				$arg_logfile = escapeshellarg($logfile);

				echo "* Backing up $arg_device to $arg_target_iso\n";

				if($disc_type == 'dvd') {

					$dvd_backup_command = "dvd_backup $arg_device -n $arg_target_rip";

					if($debug)
						$dvd_backup_command .= " -v";

					echo "* Executing: $dvd_backup_command\n";
					echo "* Watch $arg_logfile for output\n";

					$dvd_backup_command .= " 2>&1 | tee $arg_logfile";

					passthru($dvd_backup_command, $retval);

				} elseif($disc_type == 'bluray') {

					$bluray_backup_command = "bluray_backup $arg_device -d $arg_target_rip";

					if($debug) {
						$bluray_backup_command .= " -s";
						echo "* Not backing up m2ts video files\n";
						echo "* Executing: $bluray_backup_command\n";
					}

					passthru($bluray_backup_command, $retval);

				}

				if($retval === 0) {

					echo "* Backup successful. Ready for another :D\n";

					if(file_exists($target_rip) && !file_exists($target_iso))
						rename($target_rip, $target_iso);

					$backup_passed = true;

				} else {

					echo "* Backup failed! :(\n";

					rename($target_rip, "$target_rip.FAIL");

					$backup_passed = true;

				}

			}

			// Dump the DVD title sets individually
			if(!$target_iso_exists && !$is_ripping && $opt_backup && $access_device && $opt_title_sets) {

				$arr_title_sets = $dvds_model->get_title_sets();
				$str_title_sets = implode(' ', $arr_title_sets);

				echo "* Title sets: $str_title_sets\n";

				foreach($arr_title_sets as $title_set) {

					$title_set_filename = "$target_rip/VIDEO_TS/VTS_". str_pad($title_set, 2, 0, STR_PAD_LEFT) ."_1.VOB";

					if(file_exists($title_set_filename)) {
						echo "* Skipping ".basename($title_set_filename)."\n";
						continue;
					}

					echo "* Dumping $device VTS $title_set to $target_iso\n";

					$dvd_dump_iso_success = $dvd->dvdbackup_title_set($target_iso, $title_set);

					if(!$dvd_dump_iso_success) {
						echo "* DVD extraction failed :(\n";
						rename($target_rip, "$target_rip.FAIL");
						break;
					}

				 }

				echo "* Backup successful. Ready for another :D\n";
				if(file_exists($target_rip) && !file_exists($target_iso))
					rename($target_rip, $target_iso);

			}

		}

		// Move the ISO to the correct filesystem location
		// *except* in cases where --info is passed
		if(!is_link($device) && $device_is_iso && !file_exists($target_iso) && !$opt_info && !$opt_encode_info && !$opt_copy_info) {
			if(!is_dir($isos_dir))
				mkdir($isos_dir, 0755, true);
			rename($device, $target_iso);
			echo "* Moving $device to ISOs dir\n";
		}

	}

	// Use MakeMKV to backup device
	// Don't do any checks outside of arguments, since scanning discs is a pain with MakeMKV, especially UHD
	// Only print the command for now
	// Use 64 MB for cache, which is the smallest option in the GUI; default for Blu-rays is 1 GB
	if($opt_backup && $opt_makemkv) {

		// MakeMKV sees disc ids backwards on tails
		if($device == "/dev/sr0")
			$makemkv_disc = 1;
		elseif($device == "/dev/sr1")
			$makemkv_disc = 0;

		$disc_type = get_disc_type($device);

		// Dump to current directory by default -- not scanning device to get name
		$backup_dir = "makemkv.$disc_type.".basename($device).".iso";
		if($arg_backup_dir)
			$backup_dir = $arg_backup_dir;

		// Forcing skip-existing here since backing up a bluray takes a long time
		if(is_dir($backup_dir))
			goto next_disc;

		// Getting commands in the right order is tricky, so don't change
		$makemkv_backup_command = "makemkvcon backup --decrypt --cache=64 --noscan -r --progress=-same disc:$makemkv_disc $backup_dir";

		if($opt_time)
			$makemkv_backup_command = "tout $makemkv_backup_command";

		echo "$makemkv_backup_command\n";
		echo "dart --rename-iso ".escapeshellarg(realpath(getcwd())."/".$backup_dir)."\n";

		goto next_disc;

	}

	if($arg_backup_dir)
		chdir($cwdir);

	next_disc:

?>
