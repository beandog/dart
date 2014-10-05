<?php

/**
 * --encode
 *
 * Encode the episodes in the queue
 *
 */
if($opt_encode) {

	$num_encoded = 0;
	$first_pass = true;

	echo "[Encode]\n";

	$encode_episodes = $queue_model->get_episodes($skip, $max, $opt_resume);

	if(count($encode_episodes) == 0)
		echo "* No episodes in queue to encode\n";

	$encode_episode_id = array_shift($encode_episodes);

	while($encode_episode_id) {

		$episode_id = $encode_episode_id;

		if(!$first_pass) {
			echo "\n";
			echo "[Episode]\n";
		}
		$first_pass = false;

		// Stages
		$encode_stage_pass = false;
		$metadata_stage_pass = false;
		$remux_stage_pass = false;
		$final_stage_pass = false;

		// new Media Episode
		$episode = new MediaEpisode($episode_id, $export_dir);
		$episode->debug = $debug;
		$episode->encoder_version = $handbrake_version;
		$episode->remux_version = $mkvmerge_version;

		// If episode already exists, remove it from the queue, and move
		// onto the next.
		if($episode->encoded()) {
			$queue_model->remove_episode($episode_id);
			$encode_episode_id = array_shift($encode_episodes);
			continue;
		}

		// Create models
		$episodes_model = new Episodes_Model($episode_id);
		$tracks_model = new Tracks_Model($episode->metadata['track_id']);
		$series_model = new Series_Model($episode->metadata['series_id']);
		$dvds_model = new Dvds_Model($episode->metadata['dvd_id']);

		$episode->create_encodes_entry();

		// Build Handbrake object
		require 'dart.encode.x264.php';
		$episode->encode_stage_command = $handbrake_command;

		// Build Matroska metadata XML file
		require 'dart.encode.mkv.php';
		$episode->matroska_xml = $matroska_xml;
		$episode->remux_stage_command = $remux_stage_command;

		// Create temporary files early
		$episode->create_pre_encode_stage_files();
		$episode->create_pre_metadata_stage_files();
		$episode->create_pre_remux_stage_files();

		// Display Handbrake encode command
		$tmpfile = tmpfile_put_contents($episode->encode_stage_command."\n", 'encode');
		echo "Command:\t$tmpfile\n";

		// Cartoons!
		if($animation) {
			echo "Cartoons!! :D\n";
		}

		if($dry_run) {
			$encode_episode_id = array_shift($encode_episodes);
			continue;
		}

		// Encode video
		if($arg_stage == 'encode' || $arg_stage == 'all') {

			$encode_stage_pass = $episode->encode_stage($force_encode);

			if($encode_stage_pass)
				echo "Handbrake:\tpassed\n";
			else
				echo "Handbrake:\tfailed\n";

			if($arg_stage == 'encode')
				continue;

		}

		// Create metadata XML
		if($arg_stage == 'xml' || ($arg_stage == 'all' && $encode_stage_pass)) {

			$metadata_stage_pass = $episode->metadata_stage($force_metadata);

			if($metadata_stage_pass)
				echo "Metadata:\tpassed\n";
			else
				echo "Metadata:\tfailed\n";

			if($arg_stage == 'xml')
				continue;

		}

		// Mux contents into file Matroska file
		if($arg_stage == 'remux' || ($arg_stage == 'all' && $encode_stage_pass && $metadata_stage_pass)) {

			$remux_stage_pass = $episode->remux_stage($force_remux);

			if($remux_stage_pass)
				echo "Matroska:\tpassed\n";
			else
				echo "Matroska:\tfailed\n";

			if($arg_stage == 'remux')
				continue;

		}

		/*
		 * Final move to new location
		 *
		 * If debugging is enabled, it will copy the final episode file,
		 * but not remove it from the queue.  If a re-encode is begun, and
		 * everything exists, and debugging is not enabled, then it will
		 * be removed from the queue.  Because of that, if an encode session
		 * is started again with the unfinished file in the queue, it will
		 * be moved to the episodes directory, and the queue entry removed.
		 *
		 * The queue directory will be removed if debugging is disabled or
		 * if the final copy is successful.
		 *
		 */
		if($arg_stage == 'final' || ($arg_stage == 'all' && $encode_stage_pass && $metadata_stage_pass && $remux_stage_pass)) {

			$final_stage_pass = $episode->final_stage($force_final);

			if($final_stage_pass)
				echo "Final:\t\tpassed\n";
			else
				echo "Final:\t\tfailed\n";

			if($arg_stage == 'final');
				continue;

		}

		echo "\n";

		if($encode_stage_pass || $metadata_stage_pass || $remux_stage_pass || $final_stage_pass)
			$num_encoded++;

		$encode_episode_id = array_shift($encode_episodes);

	}

}
