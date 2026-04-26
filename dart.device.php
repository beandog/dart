<?php

	function get_device_type($device) {

		$device = realpath($device);

		if(dirname($device) == '/dev')
			return 'device';

		if(is_dir($device))
			return 'directory';

		if(is_file($iso) && filesize($device))
			return 'iso';

		return '';

	}

	function get_disc_type($device) {

		$device = realpath($device);

		$device_type = get_device_type($device);

		if(!$device_type)
			return '';

		if(is_dir($device) && is_dir("$device/VIDEO_TS"))
			return 'dvd';

		if(is_dir($device) && is_dir("$device/BDMV"))
			return 'bluray';

		$arg_device = escapeshellarg($device);

		$str = trim(shell_exec("disc_type $arg_device 2> /dev/null"));

		if($str == 'dvd' || $str == 'bluray' || $str == 'cd')
			return $str;

		return '';

	}

	function udfinfo($device) {

		$device = realpath($device);

		if(get_device_type($device) != 'device')
			return false;

		$arg_device = escapeshellarg($device);
		$cmd = "udfinfo $arg_device 2> /dev/null";

		exec($cmd, $output, $retval);

		if($retval !== 0 || !count($output)) {
			return false;
		}

		$arr_udfinfo = array();

		foreach($output as $str) {
			$arr = explode('=', $str);
			$arr_udfinfo[$arr[0]] = trim($arr[1]);
		}

		extract($arr_udfinfo);

		$udf_info = array(
			'label' => $label,
			'uuid' => $uuid,
			'blocksize' => $blocksize,
			'blocks' => $blocks,
			'numfiles' => $numfiles,
			'numdirs' => $numdirs,
			'udfrev' => $udfrev,
		);

		return $udf_info;

	}

	// Get DVD total filesize, rounding up to megabytes, so unless it's not a file it will always be 1+
	function disc_filesize($device) {

		$device = realpath($device);

		if(!file_exists($device))
			return 0;

		$device_type = get_device_type($device);

		if(!$device_type)
			return 0;

		$bytes = 0;
		$megabytes = 0;

		if($device_type == 'iso')
			$bytes = filesize($dvd_filename);

		if($device_type == 'device') {

			$arr_udf_info = udfinfo($device);

			if(!$arr_udf_info)
				return 0;

			$blocks = intval($arr_udf_info['blocks']);

			$bytes = $blocks * 2048;

		}

		if($device_type == 'directory') {

			require_once 'pear/File/Find.php';
			$file_find = new File_Find;

			$arr_maptree = $file_find->maptree($device);

			if(!is_array($arr_maptree))
				return 0;

			$arr_filenames = $arr_maptree[1];

			foreach($arr_filenames as $filename) {

				$filename = realpath($filename);

				// Look for strange anomalies where the file doesn't actually exist ???
				// This really shouldn't happen unless there happens to be garbage in the directory
				if(!file_exists($filename))
					continue;

				$filesize = filesize($filename);

				if($filesize)
					$bytes += $filesize;

			}

		}

		if($bytes) {
			$megabytes = $bytes / 1048576;
			$megabytes = ceil($megabytes);
			return $megabytes;
		}

		return 0;

	}
