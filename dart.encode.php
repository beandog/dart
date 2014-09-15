<?php

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
if($opt_encode) {

	echo "[Encode]\n";

	$encode_episodes = $queue_model->get_episodes($skip, $max, $opt_resume);

	if(count($encode_episodes) == 0)
		echo "* No episodes in queue to encode\n";

	foreach($encode_episodes as $episode_id) {

		if($num_encoded) {
			echo "\n";
			echo "[Episode]\n";
		}

		$episode = new MediaEpisode($episode_id, $export_dir);

		// If episode already exists, remove it from the queue, and move
		// onto the next.
		if($episode->encoded()) {
			$queue_model->remove_episode($episode_id);
			break;
		}

		$episode->debug = $debug;
		$episode->encoder_version = $handbrake_version;

		$episodes_model = new Episodes_Model($episode_id);
		$tracks_model = new Tracks_Model($episode->metadata['track_id']);
		$series_model = new Series_Model($episode->metadata['series_id']);
		$dvds_model = new Dvds_Model($episode->metadata['dvd_id']);

		// Build the Handbrake object
		require 'dart.encode.x264.php';

		// Store encoder details in episode class
		$episode->encode_stage_command = $handbrake_command;

		$episode->create_encodes_entry();

		$tmpfile = tmpfile_put_contents($episode->encode_stage_command."\n", 'encode');
		echo "Command:\t$tmpfile\n";

		// Cartoons!
		if($animation) {
			echo "Cartoons!! :D\n";
		}

		// Check for existing x264 encoded file, and go straight to creating the XML
		// file and muxing if possible.
		if($episode->x264_passed() && !$force_encode) {

			echo "* x264 queue encoded file exists\n";
			echo "* Jumping to Matroska muxing\n";
			goto goto_matroska_encode;

		}

		// If an episode is in the queue, either failed or running, skip it and go to the next one,
		// but do *not* remove it from the queue.  This means that if an encode failed, it
		// will always loop over it, skipping it for now, until manually reset or removed
		// from the queue.
		if(($episode->x264_running() || $episode->x264_failed()) && !$force_encode) {

			goto goto_encode_next_episode;

		}

		// Create the handbrake script files
		$episode->create_pre_encode_stage_files();

		// Begin the encode if everything is good to go
		if($episode->x264_ready() || $force_encode) {

			if($debug)
				echo "Executing: $handbrake_command\n";

			// Encode video
			$encode_stage_pass = $episode->encode_stage($force_encode);

			// Update queue status
			if($encode_stage_pass)
				echo "Handbrake:\tpassed\n";
			else {
				echo "Handbrake:\tfailed\n";
				echo "See ".$episode->queue_dir." for temporary files.\n";
				goto goto_encode_next_episode;

			}

		}

		// If stage was limited to encoding, then skip the others
		if($arg_stage == 'encode')
			goto goto_encode_next_episode;

		// Goto point for dry runs: Matroska functionality
		goto_matroska_encode:

		// Run through the Matroska functionality *if the x264 file exists, but not the target MKV files*,
		// allowing resume-encoding
		require 'dart.encode.xml.php';

		if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_ready()) {
			require 'dart.encode.mkv.php';
		}

		/** Final Checks **/
		// This is where if everything passed, the episode is completely removed
		// from the queue, the temporary files are removed
		if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_passed()) {

			clearstatcache();

			$episode->create_episodes_dir();
			copy($episode->queue_matroska_mkv, $episode->episode_mkv);
			$num_encoded++;
			$queue_model->remove_episode($episode_id);

			// Cleanup
			if(!$debug && file_exists($episode->episode_mkv))
				$episode->remove_queue_dir();

			$encode_finish_time = time();
			$episode->encodes_model->set_encode_finish($encode_finish_time);

		}

		clearstatcache();

		if(!file_exists($episode->queue_iso_symlink) && !file_exists($episode->episode_mkv)) {

			// At this point, it shouldn't be in the queue.
			echo "* ISO not found (".$episode->queue_iso_symlink."), MKV not found (".$episode->episode_mkv."), force removing episode from queue\n";
			$queue_model->remove_episode($episode_id);

		}

		// Goto point: jump to the next episode
		goto_encode_next_episode:

		$num_encoded++;

	}

}
