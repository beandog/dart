<?php

	// Display encode instructions about a disc
	if($opt_encode_info) {

		$dvd_episodes = $dvds_model->get_episodes();

		// On QA run, only encode the first one
		if($opt_qa)
			$dvd_episodes = array(current($dvd_episodes));

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$series_model = new Series_Model($episodes_model->get_series_id());

			$filename = get_episode_filename($episode_id, $container);

			$input_filename = realpath($device);

			// If using MakeMKV to manually extract the tracks, change the source to match its naming scheme
			if($opt_makemkv) {
				$input_filename = dirname(realpath($device))."/title".str_pad($tracks_model->ix - 1, 2, 0, STR_PAD_LEFT).".mkv";
			}

			if(!($opt_skip_existing && file_exists($filename))) {

				require 'dart.x264.php';
				$handbrake->input_filename($input_filename);
				$handbrake->output_filename($filename);
				$handbrake_command  = $handbrake->get_executable_string();
				echo "$handbrake_command\n";

			}

		}

	}
