<?php
	/**
	 * --dump-iso
	 *
	 * Copy a disc's content to the harddrive
	 */

	// Continue if we can access the device (source file)
	// and it has a database record.
	if($access_device && $dvds_model_id && $dump_iso && !$broken_dvd) {

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
		} else {
			$collection_title = "";
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
		if(strlen($series_title))
			$target_iso .= ".$str.iso";
		else
			$target_iso .= ".".$dvds_model->title.".iso";

		$isos_dir = "${export_dir}isos/".safe_filename_title($collection_title)."/".safe_filename_title($series_title)."/";
		$target_iso = $isos_dir.$target_iso;

		$display_iso = basename($target_iso);
		echo "* Filename: $display_iso\n";

		/** Filename and filesystem operations **/

		// See if the target filename exists.  This
		// is for the source regardless of whether it is
		// a block device or an ISO.
		clearstatcache();
		$target_iso_exists = file_exists($target_iso);

		// Operations on a block device
		if($device_is_hardware) {

			// If we have access to the device, and we
			// are trying to dump it, and the output filename
			// already exists, just eject the drive.
			if($target_iso_exists && $dump_iso && $access_drive) {
				echo "* File exists, ejecting drive\n";
				$drive->open();
			}

			// Dump the DVD contents to an ISO on the filesystem
			if(!$target_iso_exists && $dump_iso && $access_device) {

				$tmpfname = $target_iso.".dd";

				echo "* Dumping $device to ISO ... ";
				$success = $dvd->dump_iso($tmpfname);

				if(filesize($tmpfname)) {
					$smap = $tmpfname.".smap";
					if(file_exists($smap))
						unlink($smap);
					if(!is_dir($isos_dir))
						mkdir($isos_dir, 0755, true);
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

		// Move the ISO to the correct filesystem location
		// *except* in cases where --info is passed
		if(!is_link($device) && $device_is_iso && !file_exists($target_iso) && !$opt_info) {
			if(!is_dir($isos_dir))
				mkdir($isos_dir, 0755, true);
			rename($device, $target_iso);
			echo "* Moving $device to ISOs dir\n";
		}

	}

