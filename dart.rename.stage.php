<?php

	$rename_video = false;
	$rename_stage_passed = false;
	$rename_stage_skipped = false;
	$rename_stage_complete = false;

	// If the final target file exists, skip the encoding stage
	if(file_exists($target_files['episode_mkv']))
		$rename_stage_skipped = true;

	// Rename the video if the target file does not exist, and the queue
	// source file does.
	if(!file_exists($target_files['episode_mkv']))
		if(file_exists($queue_files['mkvmerge_output_filename']))
			$rename_video = true;

	// Override all settings if renaming is forced
	if($force_final) {
		$rename_video = true;
		$rename_stage_skipped = false;
	}

	// Ignore everything on a dry run
	if($dry_run) {
		$rename_video = false;
		$rename_stage_skipped = true;
	}

	// Skip the rename
	if($rename_stage_skipped)
		goto rename_stage_complete;

	// Create the series target dir if it does not exist
	if($rename_video) {
		if(!is_dir($target_files['series_dir'])) {
			$rename_stage_passed = mkdir($target_files['series_dir'], 0777, true);
			if(!$rename_stage_passed) {
				echo "* Creating series target directory FAILED\n";
				$rename_video = false;
			}
		}
	}

	// Rename the video file
	if($rename_video)
		$rename_stage_passed = rename($queue_files['mkvmerge_output_filename'], $target_files['episode_mkv']);

	if(!$rename_stage_passed)
		echo "* Renaming queue file to target episode filename FAILED\n";

	rename_stage_complete:

	$rename_stage_complete = true;
