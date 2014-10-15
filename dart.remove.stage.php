<?php

	$remove_queue = false;
	$remove_series = false;
	$remove_stage_passed = false;
	$remove_stage_skipped = false;
	$remove_stage_complete = false;

	// Skip the stage if the episode queue directory is already gone
	if(!is_dir($episode_queue_dir))
		$remove_stage_skipped = true;

	// Skip the stage if the series queue dir is already gone
	if(!is_dir($series_queue_dir))
		$remove_stage_skipped = true;

	// If the target filename exists, removing the queue is okay
	if(file_exists($target_files['episode_mkv']))
		$remove_queue = true;

	// If debugging, don't remove the queue
	if($debug) {
		$remove_queue = false;
		$remove_stage_skipped = true;
	}

	// Override all settings if renaming is forced
	if($force_final) {
		$remove_queue = true;
		$remove_stage_skipped = false;
	}

	// Ignore everything on a dry run
	if($dry_run) {
		$remove_queue = false;
		$remove_stage_skipped = true;
	}

	// Skip the remove stage
	if($remove_stage_skipped)
		goto remove_stage_complete;

	// Only remove the files that were specifically created by the encode
	// stages.  If extra files are there, ignore them.
	if($remove_queue) {

		if(file_exists($queue_files['handbrake_script']))
			unlink($queue_files['handbrake_script']);
		if(file_exists($queue_files['handbrake_log']))
			unlink($queue_files['handbrake_log']);
		if(file_exists($queue_files['handbrake_output_filename']))
			unlink($queue_files['handbrake_output_filename']);
		if(file_exists($queue_files['metadata_xml_file']))
			unlink($queue_files['metadata_xml_file']);
		if(file_exists($queue_files['mkvmerge_script']))
			unlink($queue_files['mkvmerge_script']);
		if(file_exists($queue_files['mkvmerge_log']))
			unlink($queue_files['mkvmerge_log']);
		if(file_exists($queue_files['mkvmerge_output_filename']))
			unlink($queue_files['mkvmerge_output_filename']);

		// Remove the episode queue dir
		$scandir = scandir($episode_queue_dir);
		if(count($scandir) == 2)
			rmdir($episode_queue_dir);

		// Check to see if there are any more than the ISO symlink left
		// in the series queue dir
		$scandir = scandir($series_queue_dir);
		if(count($scandir) <= 3)
			$remove_series = true;

	}

	// Remove the series queue dir
	if($remove_series) {

		if(is_link($queue_files['dvd_iso_symlink']))
			unlink($queue_files['dvd_iso_symlink']);

		rmdir($series_queue_dir);

	}

	remove_stage_complete:

	$remove_stage_complete = true;
