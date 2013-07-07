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
		
		if($verbose) {
			$display_iso = basename($iso);
			shell::msg("* Target filename: $display_iso");

			if(file_exists($iso))
				if($symlink)
					shell::msg("* Symlink exists");
				else {
					shell::msg("* File exists");
					if(!$device_is_iso) {
						$drive->open();
						if($verbose)
							shell::stdout("* Next DVD, please! :)");
					}
				}
			else {
				shell::msg("* File doesn't exist");
				if(!$dump_iso)
					shell::msg("* Not ripping ISO");
			}
		}

		// Dump the DVD contents to an ISO on the filesystem
		if(($rip || $dump_iso) && !file_exists($iso) && !$device_is_iso && !$symlink) {
		
			$tmpfname = tempnam($export_dir, "tmp");
		
			if($verbose) {
				shell::stdout("* Reading DVD, hit 'q' to quit", true);
				shell::stdout("* Dumping to ISO ... ", false);
			}
			$success = $dvd->dump_iso($tmpfname, 'readdvd', true);
			
			if($success && filesize($tmpfname)) {
				shell::stdout(" done!", true);
				rename($tmpfname, $iso);
				unset($tmpfname);
				$drive->open();
				$ejected = true;
			} else {
				shell::msg("DVD extraction failed");
			}
		
		}
		
		// Check if the device is an ISO, and we need
		// a symlink to the standardized ISO filename
		if(($device_is_iso || $symlink) && !file_exists($iso))
			symlink($device, $iso);
	}
