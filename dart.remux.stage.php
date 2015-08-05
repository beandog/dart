<?php

	$remux_video = false;
	$remux_stage_passed = false;
	$remux_stage_skipped = false;
	$remux_stage_complete = false;

	// If the final target file exists, skip the remuxing stage
	if(file_exists($target_files['episode_mkv']))
		$remux_stage_skipped = true;

	// If the remuxed video file exists, skip the remuxing stage
	if(file_exists($queue_files['mkvmerge_output_filename']))
		$remux_stage_skipped = true;

	// Enable remuxing if the queue remux file doesn't exist and the encoded
	// x264 file does.
	if(!file_exists($target_files['episode_mkv']))
		if(!file_exists($queue_files['mkvmerge_output_filename']))
			if(file_exists($queue_files['handbrake_output_filename']))
				$remux_video = true;

	// Override all settings if remux is forced
	if($force_remux) {
		$remux_video = true;
		$remux_stage_skipped = false;
	}

	// Ignore everything on a dry run
	if($dry_run) {
		$remux_video = false;
		$remux_stage_skipped = true;
	}

	// Skip the remuxing
	if($remux_stage_skipped)
		goto remux_stage_complete;

	if($remux_video) {

		echo "* Stage: Remux Video\n";

		// Build the encoding command based on debugging preferences
		$arg = escapeshellarg($queue_files['mkvmerge_log']);
		if($debug)
			$command = "$mkvmerge_command 2>&1 | tee $arg";
		else
			$command = "$mkvmerge_command &> $arg";

		// Execute the remux
		passthru($command, $exit_code);

		// Keep track of exit code
		$encodes_model->remux_exit_code = $exit_code;

		// Check exit code
		$remux_stage_passed = ($exit_code < 2 ? true : false);

		// Check for remux log
		if(!file_exists($queue_files['mkvmerge_log'])) {
			echo "* mkvmerge log file DOES NOT EXIST\n";
			$remux_stage_passed = false;
		} else {
			$mkvmerge_log = file_get_contents($queue_files['mkvmerge_log']);
			$mkvmerge_log = mb_convert_encoding($mkvmerge_log, 'UTF-8');
			$encodes_model->remux_output = $mkvmerge_log;
		}

	}

	// Do additional checks outside of the encoding
	// Check if file is at least 2 MB in size
	if($remux_stage_passed) {
		$bytes = filesize($queue_files['mkvmerge_output_filename']);
		$megabytes = $bytes / 1048576;
		if($megabytes < 2) {
			echo "* mkvmerge output file too small: $megabytes MB\n";
			$mkvmerge_stage_passed = false;
		}
	}

	remux_stage_complete:

	$remux_stage_complete = true;
