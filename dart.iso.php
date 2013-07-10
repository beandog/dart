<?
	/**
	 * --dump-iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	
	if($access_device && $dvds_model_id) {
	
		if($verbose)
			shell::msg("[ISO]");
	
		// Get the target filename
		$target_iso = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
		
		$display_iso = basename($target_iso);
		if($verbose)
			shell::msg("* Target filename: $display_iso");

		// Cleanup symlinks and rename files as needed.
		// See if the target filename exists
		$iso_exists = file_exists($target_iso);
		if($verbose && $iso_exists)
			shell::stdout("* Target filename exists");

		// Check if the device and ISO are symlinks
		$device_is_symlink = is_link($device);
		$iso_is_symlink = false;
		if($iso_exists) {
			$iso_is_symlink = is_link($target_iso);
			if($iso_is_symlink)
				shell::stdout("* $display_iso is a symlink to $device");
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

			$tmpfname = tempnam($export_dir, "tmp");
		
			if($verbose) {
				shell::stdout("* Dumping to ISO ... ", false);
			}
			$success = $dvd->dump_iso($tmpfname);
			
			if(filesize($tmpfname)) {
				$smap = $tmpfname.".smap";
				if(file_exists($smap))
					unlink($smap);
				rename($tmpfname, $target_iso);
				unset($tmpfname);
				shell::stdout("* DVD copy successful. Ready for another :D");
				$drive->open();
				$ejected = true;
			} else {
				shell::msg("* DVD extraction failed :(");
			}
		
		}
		
	}
