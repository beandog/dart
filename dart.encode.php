<?php

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
if($encode) {

	$queue_model = new Queue_Model;

	$num_encoded = 0;

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

			$episode = new MediaEpisode($episode_id, $export_dir);

			// If episode already exists, remove it from the queue, and move
			// onto the next.  Change the num_encoded value so that it doesn't
			// include a false positive.
			if($episode->encoded()) {

				$queue_model->remove_episode($episode_id);
				$num_encoded--;
				goto goto_encode_next_episode;

			}

			// Check for existing x264 encoded file, and go straight to creating the XML
			// file and muxing if possible.
			if($episode->x264_passed()) {

				echo "* x264 queue encoded file exists\n";
				echo "* Jumping to Matroska muxing\n";
				goto goto_matroska_encode;

			}

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episode->metadata['track_id']);
			$series_model = new Series_Model($episode->metadata['series_id']);
			$dvds_model = new Dvds_Model($episode->metadata['dvd_id']);

			require 'dart.encode.handbrake.php';

			if($num_queued_episodes > 1) {
				echo "\n";
				echo "[Encode ".($num_encoded + 1)."/$num_queued_episodes]\n";
			}

			$episode->create_queue_dir();
			$episode->create_queue_iso_symlink();

			$arr_video = array();
			$arr_h264 = array();
			$arr_x264 = array();
			$arr_audio = array();

			if($autocrop)
				$arr_video[] = "autocrop";
			if($deinterlace)
				$arr_video[] = "deinterlace";
			if($decomb)
				$arr_video[] = "decomb";
			if($detelecine)
				$arr_video[] = "detelecine";
			$arr_h264[] = "profile $h264_profile";
			$arr_h264[] = "level $h264_level";
			if($video_quality)
				$arr_x264[] = "crf $video_quality";
			if($video_bitrate) {
				$str = "${video_bitrate}k";
				if($video_two_pass)
					$str .= " two pass";
				$arr_x264[] = $str;
			}
			$arr_x264[] = "$x264_preset preset";
			$arr_x264[] = "$x264_tune";
			if($grayscale)
				$arr_x264[] = "grayscale";
			if($audio_encoder == "copy")
				$arr_audio[] = "passthrough";
			else
				$arr_audio[] = strtoupper($audio_encoder)." ${audio_bitrate}k";

			$d_video = implode(", ", $arr_video);
			$d_h264 = implode(", ", $arr_h264);
			$d_x264 = implode(", ", $arr_x264);
			$d_audio = implode(", ", $arr_audio);

			echo "Collection:\t".$episode->metadata['collection_title']."\n";
			echo "Series:\t\t".$episode->metadata['series_title']."\n";
			echo "Episode:\t".$episode->metadata['episode_title']."\n";
			echo "Source:\t\t".$episode->dvd_iso."\n";
			echo "Target:\t\t".basename($episode->episode_mkv)."\n";
			if($debug || $dry_run) {
				echo "Episode ID:\t".$episode_id."\n";
			}
			echo "Handbrake:\t$d_video\n";
			echo "Video:\t\t$d_x264\n";
			echo "Audio:\t\t$d_audio\n";
			echo "Subtitles:\t$d_subtitles\n";

			$handbrake_command = $handbrake->get_executable_string();

			if($dry_run) {

				if($verbose) {
					echo "* Handbrake command: ".$handbrake->get_executable_string()."\n";
					echo "* Jumping to Matroska muxing\n";
				}
				goto goto_encode_next_episode;

			}

			/*
			if($dumpvob) {

				$vob = "$episode_filename.vob";

				if(!file_exists($vob)) {

					$tmpfname = tempnam(dirname($episode_filename), "vob.$episode_id.");
					$dvdtrack = new DvdTrack($track_number, $iso);
					$dvdtrack->getNumAudioTracks();
					$dvdtrack->setVerbose($verbose);
					$dvdtrack->setDebug($debug);
					$dvdtrack->setBasename($tmpfname);
					$dvdtrack->setStartingChapter($episode_starting_chapter);
					$dvdtrack->setEndingChapter($episode_ending_chapter);
					$dvdtrack->setAudioStreamID($default_audio_streamid);
					unlink($tmpfname);
					$dvdtrack->dumpStream();

					rename("$tmpfname.vob", $vob);

				}

				$src = $vob;

			} else {
				$src = $episode['src_iso'];
			}
			*/

			// Cartoons!
			if($animation) {
				echo "Cartoons!! :D\n";
			}

			$exit_code = null;

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

			passthru($exec, $exit_code);

			// One line break to clear out the encoding line from handbrake
			echo "\n";

			// Update queue status
			if($exit_code === 0) {

				// Encode succeeded
				$queue_model->set_episode_status($episode_id, 'x264', 2);

			} else {

				// Encode failed
				$queue_model->set_episode_status($episode_id, 'x264', 3);
				echo "HandBrake failed for some reason.  See ".$episode->queue_dir." for temporary files.\n";
				goto goto_encode_next_episode;

			}

			// Goto point for dry runs: Matroska functionality
			goto_matroska_encode:

			// Run through the Matroska functionality *if the x264 file exists, but not the target MKV files*,
			// allowing resume-encoding
			require 'dart.encode.matroska.php';

			if($dry_run)
				goto goto_encode_next_episode;

			// Mark creating Matroska file as in progress
			$queue_model->set_episode_status($episode_id, 'mkv', 1);

			$matroska->addFile($episode->queue_handbrake_x264);
			$matroska->addGlobalTags($episode->queue_matroska_xml);
			$matroska->setFilename($episode->queue_matroska_mkv);

			file_put_contents($episode->queue_mkvmerge_script, $matroska->getCommandString()." $*\n");
			chmod($episode->queue_mkvmerge_script, 0755);

			exec($matroska->getCommandString()." 2>&1", $mkvmerge_output_arr, $mkvmerge_exit_code);

			$queue_mkvmerge_output = implode("\n", $mkvmerge_output_arr);

			file_put_contents($episode->queue_mkvmerge_output, $queue_mkvmerge_output."\n");
			assert(filesize($episode->queue_mkvmerge_output) > 0);

			if($mkvmerge_exit_code == 0 || $mkvmerge_exit_code == 1) {

				// Mark episode as successfully muxed
				$queue_model->set_episode_status($episode_id, 'mkv', 2);
				echo "* Matroska remux passed\n";


			} else {

				// Mark episode as muxing failed
				$queue_model->set_episode_status($episode_id, 'mkv', 3);

			}

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
				echo "! ISO not found (".$episode->queue_iso_symlink."), MKV not found (".$episode->episode_mkv."), force removing episode from queue\n";
				$queue_model->remove_episode($episode_id);

			}

			// Goto point: jump to the next episode
			goto_encode_next_episode:

			$skip++;
			$num_encoded++;

			// Refresh the queue
			$queue_episodes = $queue_model->get_episodes($hostname, $skip);

			$count = count($queue_episodes);

		}

	} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));

}
