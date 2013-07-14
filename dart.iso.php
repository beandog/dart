<?
	/**
	 * --dump-iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	

	// FIXME
	// Rewrite this whole thing, because "iso" variables are
	// ambiguous.  Do they mean the target iso, the source device
	// or what?
	// Also, each option does completely different things like
	// rip, info and dump_iso that they all need to be separated
	// properly.

	if($access_device && $dvds_model_id) {

		if($verbose)
			shell::msg("[ISO]");

		// Get the collection ID to prefix the filename
		// of the ISO, for easy indexing by cartoons, movies, etc.
		$collection_id = $dvds_model->get_collection_id();
		$collection_id = intval($collection_id);

		// Get the series ID
		$series_id = $dvds_model->get_series_id();

		// Add the series title
		$str = strtoupper($series_title);
		$str = preg_replace("/[^0-9A-Z \-_.]/", '', $str);
		$str = str_replace(' ', '_', $str);
		$str = substr($str, 0, 28);

		// Get the target filename
		$target_iso = $export_dir;
		$target_iso .= str_pad($collection_id, 1, '0');
		$target_iso .= ".".str_pad($series_id, 3, '0', STR_PAD_LEFT);
		$target_iso .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		if(strlen($series_title))
			$target_iso .= ".$str.iso";
		else
			$target_iso .= ".".$dvds_model->title.".iso";

		$display_iso = basename($target_iso);
		if($verbose)
			shell::msg("* Target filename: $display_iso");

		// Cleanup symlinks and rename files as needed.
		// See if the target filename exists
		$iso_exists = file_exists($target_iso);
		if($verbose && $iso_exists)
			shell::stdout("* Target filename exists, ready for next!");

		// Check if the device and ISO are symlinks
		$device_is_symlink = is_link($device);
		$iso_is_symlink = false;
		if($iso_exists) {
			$iso_is_symlink = is_link($target_iso);
			if($iso_is_symlink)
				shell::stdout("* $display_iso is a symlink to $device");
			// Eject the drive
			if($access_drive)
				$drive->open();
		}

		// Two things to check for and modify here:
		// 1) If the target ISO is a symlink, remove the
		//    symlink and move the source device to that filename
		// 2) If the target ISO doesn't exist, and this file is an
		//    ISO already, then just move it.

		// If they're both symlinks, then just keep going ...
		// Also, we are only interested if we are moving the
		// source device to a filename that was a readlink.
		if($device_is_iso && !($device_is_symlink && $iso_is_symlink) && !$device_is_symlink && !$iso_exists && !$info) {

			if($iso_is_symlink) {
				if($verbose)
					shell::stdout("* Removing old symlink before renaming $device");
				unlink($target_iso);
			}

			if($verbose)
				shell::stdout("* Moving $device to $display_iso");
			rename($device, $target_iso);
			$iso_exists = true;

		}

		// Notify that the original device is not being modified
		if($device_is_iso && $iso_exists && !$info && $verbose) {
			shell::stdout("* Ignoring source file $device");
		}

		// Dump the DVD contents to an ISO on the filesystem
		if(($rip || $dump_iso) && !$iso_exists && !$device_is_iso) {

			$tmpfname = $target_iso.".dd";

			if($verbose) {
				shell::stdout("* Dumping to ISO ... ", false);
			}
			$success = $dvd->dump_iso($tmpfname);

			if(filesize($tmpfname)) {
				$smap = $tmpfname.".smap";
				if(file_exists($smap))
					unlink($smap);
				rename($tmpfname, $target_iso);
				chmod($target_iso, 0644);
				unset($tmpfname);
				shell::stdout("* DVD copy successful. Ready for another :D");
				$drive->open();
			} else {
				shell::msg("* DVD extraction failed :(");
			}

		}

	}
