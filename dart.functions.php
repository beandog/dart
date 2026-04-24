<?php

	// Allow cleanly killing running encodes
	pcntl_async_signals(true);

	function sig_handler($signo) {

		if($signo == SIGTERM)
			exit;

		if($signo != SIGINT)
			return;

		global $dart_status;

		if($dart_status == 'encode_episode') {

			global $filename;

			$arg_filename = escapeshellarg($filename);
			echo "* Removing $arg_filename\n";
			if(file_exists($filename))
				unlink($filename);

		}

		echo "Goodbye!\n";
		posix_kill(posix_getpid(), SIGUSR1);

		exit;

	}

	pcntl_signal(SIGINT, "sig_handler");

	// File_Find class from PEAR
	// https://pear.php.net/package/File_Find

	require_once 'pear/File/Find.php';
	$file_find = new File_Find;

	// Get DVD total filesize, rounding up to megabytes, so unless it's not a file it will always be 1+
	function dvd_filesize($device) {

		if(!file_exists($device))
			return 0;

		$dvd_filename = realpath($device);

		$bytes = 0;
		$megabytes = 0;

		if(is_file($dvd_filename))
			$bytes = filesize($dvd_filename);

		if(dirname($dvd_filename) == '/dev') {

			$str = shell_exec("udfinfo $dvd_filename 2> /dev/null");
			$str = trim($str);

			$arr = explode("\n", $str);
			$arr = preg_grep('/^blocks=/', $arr);
			$str = current($arr);

			$blocks = substr($str, 7);
			$blocks = intval($blocks);
			$bytes = $blocks * 2048;

		}

		if(is_dir($dvd_filename)) {

			global $file_find;

			$arr_maptree = $file_find->maptree($dvd_filename);

			if(!is_array($arr_maptree))
				return 0;

			$arr_filenames = $arr_maptree[1];

			$bytes = 0;
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

?>
