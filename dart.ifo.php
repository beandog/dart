<?php
	/**
	 * --dump-ifo
	 *
	 * Copy a disc's IFOs to the harddrive
	 */

	// Continue if we can access the device (source file)
	// and it has a database reord.
	if($opt_dump_ifo && $access_device && $dvds_model_id && !$broken_dvd) {

		/** IFO Information **/
		echo "[IFO]\n";

		// Get the collection ID to prefix the directory filename
		// of the DVD, for easy indexing by cartoons, movies, etc.
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
		$target_ifo_dir = $ifo_export_dir;
		$target_ifo_dir .= str_pad($collection_id, 1, '0');
		$target_ifo_dir .= ".".str_pad($series_id, 3, '0', STR_PAD_LEFT);
		$target_ifo_dir .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		if(strlen($series_title))
			$target_ifo_dir .= ".$str";
		else
			$target_ifo_dir .= ".".$dvds_model->title;

		$display_ifo = basename($target_ifo_dir);
		if($debug)
			echo "* Directory: $target_ifo_dir\n";
		else
			echo "* Directory: $display_ifo\n";

		$target_video_ts_dir = $target_ifo_dir.'/VIDEO_TS';

		/** Filename and filesystem operations **/

		// See if the target directory exists.
		clearstatcache();
		$target_dir_exists = file_exists($target_ifo_dir);
		$target_video_ts_dir_exists = file_exists($target_video_ts_dir);

		if(!$target_dir_exists || !$target_video_ts_dir_exists) {

			if(!$target_dir_exists)
				mkdir($target_ifo_dir);
			$success = $dvd->dump_ifo($target_ifo_dir);

			if($success)
				echo "* Backed up IFOs\n";
			else
				echo "* Failed :(\n";

		} else  {

			echo "* IFO directory exists, skipping backup\n";

		}

		// Eject the disc if exporting the IFO, and nothing else
		if($opt_wait && !$opt_rip && !$dump_iso && !$opt_import) {
			$drive->open();
		}

	}
