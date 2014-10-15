<?php

	$encode_video = false;
	$encode_stage_passed = false;
	$encode_stage_skipped = false;
	$encode_stage_complete = false;

	// If the final target file exists, skip the encoding stage
	if(file_exists($target_files['episode_mkv']))
		$encode_stage_skipped = true;

	// If the remuxed video file exists, skip the encoding stage
	if(file_exists($queue_files['mkvmerge_output_filename']))
		$encode_stage_skipped = true;

	// If the queue x264 file exists, mark the stage as complete
	if(file_exists($queue_files['handbrake_output_filename']))
		$encode_stage_skipped = true;

	// Enable encoding if target file, mkvmerge output file, and handbrake output
	// file don't exist
	if(!file_exists($target_files['episode_mkv']))
		if(!file_exists($queue_files['mkvmerge_output_filename']))
			if(!file_exists($queue_files['handbrake_output_filename']))
				$encode_video = true;

	// Override all settings if encoding is forced
	if($force_encode) {
		$encode_video = true;
		$encode_stage_skipped = false;
	}

	// Ignore everything on a dry run
	if($dry_run)
		$encode_stage_skipped = true;

	// Skip the encoding
	if($encode_stage_skipped)
		goto encode_stage_complete;

	// Begin encoding stage
	if($encode_video) {

		echo "\n";
		echo "* Stage: Encode Video\n";

		// Build the encoding command based on debugging preferences
		$arg = escapeshellarg($queue_files['handbrake_log']);
		if($debug)
			$command = "$handbrake_command 2>&1 | tee $arg";
		else
			$command = "$handbrake_command 2> $arg";

		// Execute the encoder
		passthru($command, $exit_code);

		// Keep track of exit code
		$encodes_model->encoder_exit_code = $exit_code;

		// Check exit code
		$encode_stage_passed = ($exit_code === 0 ? true : false);

		// Check for encoding log
		if(!file_exists($queue_files['handbrake_log'])) {
			echo "* HandBrake log file DOES NOT EXIST\n";
			$encode_stage_passed = false;
		} else {
			$encodes_model->encode_output = file_get_contents($queue_files['handbrake_log']);
		}

	}

	// Do additional checks outside of the encoding
	// Check if file is at least 2 MB in size
	if($encode_stage_passed) {
		$bytes = filesize($queue_files['handbrake_output_filename']);
		$megabytes = $bytes / 1048576;
		if($megabytes < 2) {
			echo "* HandBrake output file too small: $megabytes MB\n";
			$encode_stage_passed = false;
		}
	}

	// Store endtime
	if($encode_stage_passed)
		$encodes_model->encode_finish = date('%r');

	encode_stage_complete:

	$encode_stage_complete = true;
