<?
	/**
	 * --iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	// Get the target filename
	$iso = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
	
	// Check if needed
	if($rip && !file_exists($iso) && !$device_is_iso) {
			
		$tmpfname = tempnam($export_dir, "tmp");
	
		$dvd->dump_iso($tmpfname);
		rename($tmpfname, $iso);
		unset($tmpfname);
	
	}