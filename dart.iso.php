<?
	/**
	 * --iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	// Get the target filename
	$iso = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
	
	// Dump the DVD contents to an ISO on the filesystem
	if($rip && !file_exists($iso) && !$device_is_iso) {
			
		$tmpfname = tempnam($export_dir, "tmp");
	
		$dvd->dump_iso($tmpfname);
		rename($tmpfname, $iso);
		unset($tmpfname);
		
		$dvd->eject();
		$ejected = true;
	
	}
	
	// Check if the device is an ISO, and we need
	// a symlink to the standardized ISO filename
	if($device_is_iso && !file_exists($iso))
		symlink($device, $iso);