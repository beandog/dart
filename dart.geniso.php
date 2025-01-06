<?php
	/**
	 * --geniso
	 *
	 * Create an ISO file from a directory
	 */

	if($access_device && $dvds_model_id && $opt_geniso && !$broken_dvd) {

		/** ISO Information **/
		echo "[Generate ISO]\n";

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
			$nsix = $series_model->nsix;
		} else {
			$collection_title = "";
			$nsix = 'NSIX';
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
		$target_iso .= ".$nsix";
		$target_iso .= ".iso";

		$isos_dir = $backup_dir;
		$target_iso = realpath($isos_dir).'/'.$target_iso;

		$display_iso = basename($target_iso);
		echo "* Filename: $display_iso\n";

		/** Filename and filesystem operations **/

		// See if the target filename exists. This
		// is for the source regardless of whether it is
		// a block device or an ISO.
		clearstatcache();
		$target_iso_exists = file_exists($target_iso);

		if(!strlen($dvds_model->title)) {
			echo "* DVD is missing volume title!\n";
			goto next_disc;
		}

		$arg_volname = escapeshellarg($dvds_model->title);
		$arg_target_iso = escapeshellarg($target_iso);
		$arg_device = escapeshellarg(realpath($device));

		$cmd = "mkisofs -posix-L -iso-level 3 -input-charset utf-8 -allow-limited-size -udf -volid $arg_volname -o $arg_target_iso $arg_device";
		if($debug)
			$cmd = "mkisofs -verbose -posix-L -iso-level 3 -input-charset utf-8 -allow-limited-size -udf -volid $arg_volname -o $arg_target_iso $arg_device";

		// cdrtools doesn't use 'allow-limited-size' option
		if($hostname == 'tobe') {
			$cmd = "mkisofs -posix-L -iso-level 3 -input-charset utf-8 -udf -volid $arg_volname -o $arg_target_iso $arg_device";
			if($debug)
				$cmd = "mkisofs -verbose -posix-L -iso-level 3 -input-charset utf-8 -udf -volid $arg_volname -o $arg_target_iso $arg_device";

		}

		if($debug)
			echo "$cmd\n";

		if(file_exists($target_iso) && !$debug) {
			echo "* $arg_target_iso file exists, skipping\n";
			goto next_disc;
		}

		passthru($cmd, $retval);

	}

	next_disc:

?>
