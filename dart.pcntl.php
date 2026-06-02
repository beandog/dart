<?php

if(extension_loaded('posix')) {

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
			global $device;
			global $opt_eject;

			$arg_filename = escapeshellarg($filename);
			echo "* Removing $arg_filename\n";
			if(file_exists($filename))
				unlink($filename);

			if(($device == '/dev/sr0' || $device == '/dev/sr1') && $opt_eject)
				passthru("eject $device");

		}

		posix_kill(posix_getpid(), SIGUSR1);

		exit;

	}

	pcntl_signal(SIGINT, "sig_handler");

} else {

	function sig_handler($signo) {

		echo "Handling signals not supported on WSL or Windows for PHP\n";
		exit;

	}

}

