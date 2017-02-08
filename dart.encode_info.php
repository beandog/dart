<?php

	// Display encode instructions about a disc
	if($opt_encode_info) {

		$dvd_source_iso = $device;

		$opt_encode = true;

		$dvd_episodes = $dvds_model->get_episodes();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$episode_metadata = $episodes_model->get_metadata();
			$episode_metadata['epix'] = $episode_metadata['nsix'].".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$episode['track_ix'] = $tracks_model->ix;
			$display_name = $episode_metadata['title'];
			$queue_files['handbrake_output_filename'] = "$display_name.$container";
			$series_model = new Series_Model($episodes_model->get_series_id());
			$dvd_episode_iso = $device;
			$target_files['episode_mkv'] = safe_filename_title($display_name).".$container";

			// placeholders
			$episode_title = $display_name;
			$episode['starting_chapter'] = $episodes_model->starting_chapter;
			$episode['ending_chapter'] = $episodes_model->ending_chapter;
			$collection_title = $series_model->get_collection_title();

			require 'dart.x264.php';

			// Override the HandBrake output filename
			$filename = $episode_metadata['epix'].".$container";
			$handbrake->output_filename($filename);

			$handbrake_command  = $handbrake->get_executable_string();

			echo "\n$handbrake_command\n";

		}

		$opt_encode = false;

		exit;

	}
