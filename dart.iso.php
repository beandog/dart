<?
	/**
	 * --iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	
	if($access_device) {
	
		if($verbose)
			shell::msg("[ISO]");
	
		// Get the target filename
		$iso = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
		
		if($verbose)
			shell::msg("* Filename: $iso");
		
		// Dump the DVD contents to an ISO on the filesystem
		if($rip && !file_exists($iso) && !$device_is_iso && !$handbrake) {
		
			$tmpfname = tempnam($export_dir, "tmp");
		
			if($verbose)
				shell::msg("* Dumping contents");
			$success = $dvd->dump_iso($tmpfname);
			
			if($success) {
				rename($tmpfname, $iso);
				unset($tmpfname);
				$dvd->eject();
				$ejected = true;
			} else {
				shell::msg("DVD extraction failed");
			}
		
		}
		
		// Check if the device is an ISO, and we need
		// a symlink to the standardized ISO filename
		if(($device_is_iso || $handbrake) && !file_exists($iso))
			symlink($device, $iso);
	}