<?php
	/**
	 * --dump-iso
	 *
	 * Copy a disc's content to the harddrive
	 */

	// Continue if we can access the device (source file)
	// and it has a database record.
	if($access_device && $dvds_model_id && $opt_dump_iso && !$broken_dvd) {

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

		// Operations on a block device
		if($device_is_hardware) {

			$target_rip_exists = file_exists($target_rip);

			// Check if the drive is already ripping
			$is_ripping = false;
			$output = array();
			exec("pgrep -af dvdbackup", $output, $retval);
			if($retval === 0) {
				$pattern = "/".basename($target_rip)."$/";
				$num_procs = count(preg_grep($pattern, $output));
				if($num_procs)
					$is_ripping = true;
			}

			if($is_ripping)
				echo "* dvbackup in progress for $device to ".basename($target_rip)."\n";

			// If we have access to the device, and we
			// are trying to dump it, and the output filename
			// already exists, just eject the drive.
			if($target_iso_exists && $opt_dump_iso) {
				echo "* Filename: $display_iso exists\n";
				if($batch_mode) {
					if($debug) {
						echo "* Batch mode: enabled\n";
						echo "* Ejecting disk\n";
					}
					$drive->open();
				} else {
					if($debug) {
						echo "* Batch mode: disabled\n";
						echo "* Not ejecting disk\n";
					}
				}
			}

			// See if backing up individual title sets
			if($dvds_model->has_max_tracks())
				$opt_title_sets = true;
			else
				$opt_title_sets = false;

			// Dump the DVD contents to an ISO on the filesystem
			if(!$target_iso_exists && !$is_ripping && $opt_dump_iso && $access_device && !$opt_title_sets) {

				$logfile = "/tmp/dvdbackup.log";

				echo "* Dumping $device to $target_iso\n";
				$dvd_dump_iso_success = $dvd->dvdbackup($target_iso, $logfile);

				if($dvd_dump_iso_success) {
					echo "* DVD copy successful. Ready for another :D\n";
					if(file_exists($target_rip) && !file_exists($target_iso))
						rename($target_rip, $target_iso);
					$drive->open();
				} else {
					echo "* DVD extraction failed :(\n";
					rename($target_rip, "$target_rip.FAIL");
				}

			}

			// Dump the DVD title sets individually
			if(!$target_iso_exists && !$is_ripping && $opt_dump_iso && $access_device && $opt_title_sets) {

				$arr_title_sets = $dvds_model->get_title_sets();
				$str_title_sets = implode(' ', $arr_title_sets);

				echo "* Title sets: $str_title_sets\n";

				$logfile = "/tmp/dvdbackup.log";

				foreach($arr_title_sets as $title_set) {

					$title_set_filename = "$target_rip/VIDEO_TS/VTS_". str_pad($title_set, 2, 0, STR_PAD_LEFT) ."_1.VOB";

					if(file_exists($title_set_filename)) {
						echo "* Skipping ".basename($title_set_filename)."\n";
						continue;
					}

					echo "* Dumping $device VTS $title_set to $target_iso\n";

					$dvd_dump_iso_success = $dvd->dvdbackup_title_set($target_iso, $title_set, $logfile);

					if(!$dvd_dump_iso_success) {
						echo "* DVD extraction failed :(\n";
						rename($target_rip, "$target_rip.FAIL");
						break;
					}

				 }

				echo "* DVD copy successful. Ready for another :D\n";
				if(file_exists($target_rip) && !file_exists($target_iso))
					rename($target_rip, $target_iso);
				$drive->open();

			}

		}

		// Move the ISO to the correct filesystem location
		// *except* in cases where --info is passed
		if(!is_link($device) && $device_is_iso && !file_exists($target_iso) && !$opt_info && !$opt_encode_info && !$opt_copy_info && !$opt_rip_info && !$opt_pts_info) {
			if(!is_dir($isos_dir))
				mkdir($isos_dir, 0755, true);
			rename($device, $target_iso);
			echo "* Moving $device to ISOs dir\n";
		}

	}

	next_disc:

?>
