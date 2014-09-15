<?php

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
if($opt_encode) {

	echo "[Encode]\n";

	$queue_episodes = $queue_model->get_episodes();

	if(count($queue_episodes) == 0)
		echo "* No episodes in queue to encode\n";

	if($skip)
		echo "* Skipping $skip episodes\n";
	if($max)
		echo "* Limiting encoding to $max episodes\n";

	if($dry_run && !$max)
		$max = count($queue_episodes);

	do {

		foreach($queue_episodes as $episode_id) {

			if($num_encoded) {
				echo "\n";
				echo "[Episode]\n";
			}

			$episode = new MediaEpisode($episode_id, $export_dir);
			$episode->debug = $debug;
			$episode->create_queue_dir();
			$episode->create_queue_iso_symlink();

			// If episode already exists, remove it from the queue, and move
			// onto the next.  Change the num_encoded value so that it doesn't
			// include a false positive.
			if($episode->encoded()) {

				$queue_model->remove_episode($episode_id);
				$num_encoded--;
				goto goto_encode_next_episode;

			}

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episode->metadata['track_id']);
			$series_model = new Series_Model($episode->metadata['series_id']);
			$dvds_model = new Dvds_Model($episode->metadata['dvd_id']);

			// Build the Handbrake object
			require 'dart.encode.x264.php';

			// Store encoder details in episode class
			$episode->encode_stage_command = $handbrake_command;

			$tmpfile = tmpfile_put_contents($episode->encode_stage_command."\n", 'encode');
			echo "Command:\t$tmpfile\n";

			if($dry_run) {

				$num_encoded++;

				echo "\n$str\n";

				goto goto_encode_next_episode;

			}

			// Track encoding session in the database
			// A little bit about the encodes table ... it is designed to keep track of
			// *attempts* to encode an episode, and is not meant to be a tracker for a
			// unique episode.  The valuable part is the uuid that will be stored in the
			// container metadata when everything is finished -- it will point to the
			// database entry where the encoding settings, commands, reuslts, etc. are
			// stored.
			// The database table is intended to be abused, so creating an entry as soon
			// as possible falls within that goal, but only occurs when a dry run is not
			// enabled.
			$encodes_model = new Encodes_Model();
			$encodes_model->create_new();
			$encodes_model->episode_id = $episode_id;
			$encodes_model->encode_cmd = $episode->encode_stage_command;
			$encodes_model->encoder_version = $handbrake_version;
			$uuid = $encodes_model->uniq_id;
			$encode_begin_time = time();

			// Cartoons!
			if($animation) {
				echo "Cartoons!! :D\n";
			}

			// Check for existing x264 encoded file, and go straight to creating the XML
			// file and muxing if possible.
			if($episode->x264_passed() && !$force_encode) {

				$encodes_model = new Encodes_Model();
				$encode_id = $encodes_model->find_episode_id($episode_id);
				if(is_null($encode_id))
					$encodes_model->create_new();
				else
					$encodes_model->load($encode_id);

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

				// Store encode stage output in database
				// Must be converted to UTF-8 from ISO 8859-1 because libdvdnav can output
				// garbage characters when displaying 'Menu Languages'
				$encode_stage_output = file_get_contents($episode->queue_handbrake_output);
				$encode_stage_output = mb_convert_encoding($encode_stage_output, 'UTF-8');
				$encodes_model->encode_output = $encode_stage_output;

				$encodes_model->encoder_exit_code = $episode->encode_stage_exit_code;

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

			if($dry_run) {
				$num_encoded--;
				goto goto_encode_next_episode;
			};

			if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_ready()) {
				require 'dart.encode.mkv.php';
			}

			/** Final Checks **/
			// This is where if everything passed, the episode is completely removed
			// from the queue, the temporary files are removed
			if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_passed()) {

				clearstatcache();

				assert(file_exists($episode->queue_matroska_mkv));
				assert(filesize($episode->queue_matroska_mkv) > 0);
				$episode->create_episodes_dir();
				assert(copy($episode->queue_matroska_mkv, $episode->episode_mkv));
				$num_encoded++;
				$queue_model->remove_episode($episode_id);

				// Cleanup
				if(!$debug && file_exists($episode->episode_mkv))
					$episode->remove_queue_dir();

				$encode_finish_time = time();
				$encodes_model->set_encode_finish($encode_finish_time);

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
			$skip++;

			// Refresh the queue
			$queue_episodes = $queue_model->get_episodes($hostname, $skip);

		}

	} while(count($queue_episodes) && $opt_encode && (!$max || ($num_encoded < $max)));

}
