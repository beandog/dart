<?php

/**
 * --encode
 *
 * Encode the episodes in the queue
 *
 */
if($opt_encode) {

	// Keep track
	$episodes_encoded = 0;

	// Certain first pass elements
	$first_pass = true;

	// Get the episodes to encode from the queue
	$encode_episodes = $queue_model->get_episodes($skip, $max);
	$num_queue_episodes = count($encode_episodes);
	if($num_queue_episodes)
		echo "[Encode: $num_queue_episodes episodes]\n";

	// Get the first episode id, and resort the array
	$encode_episode_id = array_shift($encode_episodes);

	// While the episode id is set, encode!
	while($encode_episode_id) {

		$episode_id = $encode_episode_id;

		if(!$first_pass) {
			echo "\n";
			echo "[Episode]\n";
		}
		$first_pass = false;

		// Stages
		$encode_stage_pass = false;
		$remux_stage_pass = false;
		$final_stage_pass = false;

		// Toggle to remove the episode from the queue
		$remove_encode_queue = false;

		// Create models
		$episodes_model = new Episodes_Model($episode_id);
		$episode = $episodes_model->get_metadata();

		$tracks_model = new Tracks_Model($episode['track_id']);
		$series_model = new Series_Model($episode['series_id']);
		$dvds_model = new Dvds_Model($episode['dvd_id']);
		$encodes_model = new Encodes_Model();

		// Build encoding variables
		require 'dart.encode.prepare.php';

		// If episode already exists, remove it from the queue, and move
		// onto the next.
		clearstatcache();
		if(file_exists($target_files['episode_mkv'])) {
			$remove_encode_queue = true;
			goto next_episode;
		}

		// Create temporary directory
		if(!is_dir($episode_queue_dir))
			mkdir($episode_queue_dir, 0755, true);

		// Check for ISO file, symlink
		if(!file_exists($dvd_source_iso)) {
			echo "* Source ISO $dvd_source_iso does not exist\n";
			goto next_episode;

		}
		if(!is_link($queue_files['dvd_iso_symlink']))
			symlink($dvd_source_iso, $queue_files['dvd_iso_symlink']);

		// Build Handbrake object
		require 'dart.x264.php';

		// Save Handbrake queue files
		file_put_contents($queue_files['handbrake_script'], "$handbrake_command\n");
		chmod($queue_files['handbrake_script'], 0777);
		$tmpfile = tempnam(sys_get_temp_dir(), 'encode');
		file_put_contents($tmpfile, "$handbrake_command\n");

		if($container == 'mkv')

			// Build Matroska metadata XML file
			require 'dart.mkv.php';

			// Save Matroska queue files
			file_put_contents($queue_files['mkvmerge_script'], "$mkvmerge_command\n");
			file_put_contents($queue_files['metadata_xml_file'], $matroska_xml);
			chmod($queue_files['mkvmerge_script'], 0777);

		}

		// Display Handbrake encode command
		echo "Command:\t$tmpfile\n";

		// Cartoons!
		if($animation)
			echo "Cartoons!! :D\n";

		// Encode video
		if($arg_stage == 'encode' || $arg_stage == 'all') {

			require 'dart.encode.stage.php';

			if($encode_stage_passed)
				echo "Handbrake:\tpassed\n";
			elseif($encode_stage_skipped)
				echo "Handbrake:\tskipped\n";
			else
				echo "Handbrake:\tfailed\n";

			if($arg_stage == 'encode')
				goto next_episode;

		}

		// Mux contents into file Matroska file
		if($container == 'mkv') {

			if($arg_stage == 'remux' || $arg_stage == 'all') {

				require 'dart.remux.stage.php';

				if($remux_stage_passed)
					echo "Matroska:\tpassed\n";
				elseif($remux_stage_skipped)
					echo "Matroska:\tskipped\n";
				else
					echo "Matroska:\tfailed\n";

				if($arg_stage == 'remux')
					goto next_episode;

			}

		}

		// Remove queue files, move to final location
		if($arg_stage == 'final' || $arg_stage == 'all') {

			require 'dart.rename.stage.php';
			require 'dart.remove.stage.php';

		}

		// Finally, remove the episode from the queue
		if(file_exists($target_files['episode_mkv']))
			$remove_encode_queue = true;

		// Don't remove if it's a dry run
		if($dry_run)
			$remove_encode_queue = false;

		echo "\n";

		$episodes_encoded++;

		next_episode:

		if($remove_encode_queue)
			$queue_model->remove_episode($episode_id);

		// Get the next episode in the encoding queue
		// At the moment, this is designed to stop when the number of
		// episodes in the queue -- at startup time -- is depleted
		$encode_episode_id = array_shift($encode_episodes);

	}

}
