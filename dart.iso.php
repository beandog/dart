<?
	/**
	 * --iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	
	if($access_device && $dvds_model_id) {
	
		if($verbose)
			shell::msg("[ISO]");
	
		// Get the target filename
		$iso = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
		
		$display_iso = basename($iso);
		if($verbose)
			shell::msg("* Target filename: $display_iso");

		if(file_exists($iso)) {

			// Move the symlink to the target filename
			if($is_symlink && $device_is_iso) {
				$readlink = readlink($iso);
				if($verbose)
					shell::stdout("* Moving to target ISO");
				$bool = rename($readlink, $iso);
				if(!$bool) {
					shell::stdout("Moving $device to $iso failed");
					exit 1;
				}
			}

			if($verbose && !$readlink)
				shell::msg("* File exists");
			if(!($device_is_iso || $info)) {
				$drive->open();
				if($verbose)
					shell::stdout("* Next DVD, please! :)");
			}
		} else {
			shell::msg("* File doesn't exist");
		}

		// Dump the DVD contents to an ISO on the filesystem
		if(($rip || $dump_iso) && !file_exists($iso) && !$device_is_iso && !$symlink) {
		
			$tmpfname = tempnam($export_dir, "tmp");
		
			if($verbose) {
				shell::stdout("* Reading DVD, hit 'q' to quit", true);
				shell::stdout("* Dumping to ISO ... ", false);
			}
			$success = $dvd->dump_iso($tmpfname, 'readdvd', true);
			shell::stdout(" done!", true);

			shell::stdout("dump_iso() return value");
			var_dump($success);
			
			if(filesize($tmpfname)) {
				$smap = $tmpfname.".smap";
				if(file_exists($smap))
					unlink($smap);
				rename($tmpfname, $iso);
				unset($tmpfname);
				$drive->open();
				$ejected = true;
			} else {
				shell::msg("DVD extraction failed");
			}
		
		}
		
		// If reading from a file that is an ISO, rename it
		// to the full target name
		if($device_is_iso && !file_exists($iso)) {
			rename($device, $iso);
		}
	}
