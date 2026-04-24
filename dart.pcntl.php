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

		posix_kill(posix_getpid(), SIGUSR1);

		exit;

	}

	pcntl_signal(SIGINT, "sig_handler");

