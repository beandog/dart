<?php

/**
 * --encode
 *
 * Encode the episodes in the queue
 *
 */
if($opt_encode) {

	$num_encoded = 0;

	echo "[Encode]\n";

	$encode_episodes = $queue_model->get_episodes($skip, $max, $opt_resume);

	if(count($encode_episodes) == 0)
		echo "* No episodes in queue to encode\n";

	foreach($encode_episodes as $episode_id) {

		if($num_encoded) {
			echo "\n";
			echo "[Episode]\n";
		}

		// Stages
		$encode_stage_pass = false;
		$metadata_stage_pass = false;
		$remux_stage_pass = false;

		// new Media Episode
		$episode = new MediaEpisode($episode_id, $export_dir);

		// If episode already exists, remove it from the queue, and move
		// onto the next.
		if($episode->encoded()) {
			$queue_model->remove_episode($episode_id);
			break;
		}

		// Create models
		$episodes_model = new Episodes_Model($episode_id);
		$tracks_model = new Tracks_Model($episode->metadata['track_id']);
		$series_model = new Series_Model($episode->metadata['series_id']);
		$dvds_model = new Dvds_Model($episode->metadata['dvd_id']);

		// Build Handbrake object
		require_once 'dart.encode.x264.php';
		$episode->encode_stage_command = $handbrake_command;

		// Build Matroska metadata XML file
		require_once 'dart.encode.mkv.php';
		$episode->metadata_xml = $metadata_xml;
		$episode->remux_stage_command = $remux_stage_command;

		// Create encode files
		$episode->create_pre_encode_stage_files();
		$episode->create_metadata_xml_file();
		$episode->create_pre_remux_stage_files();

		// Display Handbrake encode command
		$tmpfile = tmpfile_put_contents($episode->encode_stage_command."\n", 'encode');
		echo "Command:\t$tmpfile\n";

		// Cartoons!
		if($animation) {
			echo "Cartoons!! :D\n";
		}

		if($dry_run)
			break;

		// Encode video
		if($arg_stage == 'encode' || $arg_stage == 'all') {

			$encode_stage_pass = $episode->encode_stage($force_encode);

			if($encode_stage_pass)
				echo "Handbrake:\tpassed\n";
			else
				echo "Handbrake:\tfailed\n";

			if($arg_stage == 'encode')
				break;

		}

		if($arg_stage == 'xml' || $arg_stage == 'all') {

			$metadata_stage_pass = $episode->metadata_stage($force_metadata);

			if($metadata_stage_pass)
				echo "Metadata:\tpassed\n";
			else
				echo "Metadata:\tfailed\n";

			if($arg_stage == 'xml')
				break;

		}

		// Create metadata XML
		if($arg_stage == 'remux' || $arg_stage == 'all') {

			$remux_stage_pass = $episode->remux_stage($force_remux);

			if($remux_stage_pass)
				echo "Matroska:\tpassed\n";
			else
				echo "Matroska:\tfailed\n";

			if($arg_stage == 'remux')
				break;

		}

		echo "\n";

		if($encode_stage_pass || $metadata_stage_pass || $remux_stage_pass)
			$num_encoded++;

	}

}
