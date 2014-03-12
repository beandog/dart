<?php
	/**
	 * --dump-iso
	 *
	 * Copy a disc's content to the harddrive
	 */

	// Continue if we can access the device (source file)
	// and it has a database reord.
	if($access_device && $dvds_model_id) {

		/** ISO Information **/
		if($verbose)
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
		}

		// Get the series title
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
			echo "* Target filename: $display_iso\n";

		/** Filename and filesystem operations **/

		// See if the target filename exists.  This
		// is for the source regardless of whether it is
		// a block device or an ISO.
		$target_iso_exists = file_exists($target_iso);
		if($verbose && $target_iso_exists)
			echo "* target file exists\n";

		// Operations on a block device
		if($device_is_hardware) {

			if($debug);
				echo "! device is not an ISO\n";

			// If we have access to the device, and we
			// are trying to dump it, and the output filename
			// already exists, just eject the drive.
			if($target_iso_exists && $dump_iso && $access_drive) {
				if($verbose)
					echo "* ISO dump exists, ejecting drive\n";
				$drive->open();
			}

			// Dump the DVD contents to an ISO on the filesystem
			if(!$target_iso_exists && $dump_iso && $access_device) {

				$tmpfname = $target_iso.".dd";

				if($verbose) {
					echo "* Dumping to ISO ... ";
				}
				$success = $dvd->dump_iso($tmpfname);

				if(filesize($tmpfname)) {
					$smap = $tmpfname.".smap";
					if(file_exists($smap))
						unlink($smap);
					rename($tmpfname, $target_iso);
					chmod($target_iso, 0644);
					unset($tmpfname);
					echo "* DVD copy successful. Ready for another :D\n";
					$drive->open();
				} else {
					echo "* DVD extraction failed :(\n";
				}
			}
		}

		// Operations on filename
		if($device_is_iso) {

			if($debug) {
				echo "* Full path of device: ".realpath($device)."\n";
			}

			// Move the filename to standard naming convention for the ISOs, so that it
			// is always apparent where the device can be *accessed*.

			// First, rename the existing file to the basename of the target ISO, if it
			// is not already.
			$source_dirname = dirname($device);

			$target_rename = $source_dirname."/".basename($target_iso);
			$target_rename_exists = file_exists($target_rename);

			// Rename the file to its new syntax if it's not already done
			if(!$target_rename_exists) {

				// Rename the file
				if($verbose)
					echo "* Moving $display_device to $target_rename\n";
				if($debug)
					echo "* Moving $device to $target_rename\n";

				rename($device, $target_rename);

				// Now update the filenames after they have moved
				$device = $target_rename;
				$target_iso_exists = true;

			}

		}

	}
