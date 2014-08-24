<?php

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
if($encode) {

	$queue_model = new Queue_Model;

	echo "[Encode]\n";

	$queue_episodes = $queue_model->get_episodes($hostname, $skip, $max);

	if($skip)
		echo "* Skipping $skip episodes\n";
	if($max)
		echo "* Limiting encoding to $max episodes\n";

	do {

		$num_queued_episodes = count($queue_episodes);
		if($num_queued_episodes > 1)
			echo "* $num_queued_episodes episodes queued up!\n";

		foreach($queue_episodes as $episode_id) {

			if($num_queued_episodes > 1) {
				echo "\n";
				echo "[Encode ".($num_encoded + 1)."/$num_queued_episodes]\n";
			}

			$episode = new MediaEpisode($episode_id, $export_dir);

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

			$str = $handbrake->get_executable_string();
			$tmpfile = tempnam(sys_get_temp_dir(), "encode");
			file_put_contents($tmpfile, "$str\n");
			echo "Command:\t$tmpfile\n";

			if($dry_run) {

				echo "\n$str\n";

				goto goto_encode_next_episode;

			}

			// Cartoons!
			if($animation) {
				echo "Cartoons!! :D\n";
			}

			// Check for existing x264 encoded file, and go straight to creating the XML
			// file and muxing if possible.
			if($episode->x264_passed()) {

				echo "* x264 queue encoded file exists\n";
				echo "* Jumping to Matroska muxing\n";
				goto goto_matroska_encode;

			}

			// If an episode is in the queue, either failed or running, skip it and go to the next one,
			// but do *not* remove it from the queue.  This means that if an encode failed, it
			// will always loop over it, skipping it for now, until manually reset or removed
			// from the queue.
			if($episode->x264_running() || $episode->x264_failed()) {

				goto goto_encode_next_episode;

			}

			// Begin the encode if everything is good to go
			if($episode->x264_ready()) {

				$episode->create_queue_dir();
				$episode->create_queue_iso_symlink();

				// Flag episode encoding as "in progress"
				$queue_model->set_episode_status($episode_id, 'x264', 1);

				file_put_contents($episode->queue_handbrake_script, $handbrake_command." $*\n");
				chmod($episode->queue_handbrake_script, 0755);

				if($debug) {
					$exec = escapeshellcmd($handbrake_command);
					echo "Executing: $exec\n";
				} else {
					$exec = escapeshellcmd($handbrake_command)." 2> ".escapeshellarg($episode->queue_handbrake_output);
				}

				$exit_code = null;
				passthru($exec, $exit_code);

				// Update queue status
				if($exit_code === 0) {

					// Encode succeeded
					$queue_model->set_episode_status($episode_id, 'x264', 2);
					echo "Handbrake:\tpassed\n";

				} else {

					// Encode failed
					$queue_model->set_episode_status($episode_id, 'x264', 3);
					echo "Handbrake:\tfailed\n";
					echo "See ".$episode->queue_dir." for temporary files.\n";
					goto goto_encode_next_episode;

				}

			}

			// Goto point for dry runs: Matroska functionality
			goto_matroska_encode:

			// Run through the Matroska functionality *if the x264 file exists, but not the target MKV files*,
			// allowing resume-encoding
			require 'dart.encode.xml.php';

			if($dry_run)
				goto goto_encode_next_episode;

			if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_ready())
				require 'dart.encode.mkv.php';

			/** Final Checks **/
			// This is where if everything passed, the episode is completely removed
			// from the queue, the temporary files are removed
			if($episode->x264_passed() && $episode->xml_passed() && $episode->mkv_passed()) {

				assert(file_exists($episode->queue_matroska_mkv));
				assert(filesize($episode->queue_matroska_mkv) > 0);
				$episode->create_episodes_dir();
				assert(copy($episode->queue_matroska_mkv, $episode->episode_mkv));
				$num_encoded++;
				$queue_model->remove_episode($episode_id);

				// Cleanup
				if(!$debug && file_exists($episode->episode_mkv))
					$episode->remove_queue_dir();

			}

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

	} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));

}
