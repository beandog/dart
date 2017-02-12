<?php

	// Display encode instructions about a disc
	if($opt_encode_info) {

		$dvd_episodes = $dvds_model->get_episodes();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$series_model = new Series_Model($episodes_model->get_series_id());

			$episode_metadata = $episodes_model->get_metadata();

			$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
			$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
			$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
			$filename .= ".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
			$filename .= ".".$episode_metadata['nsix'];
			$filename .= ".$container";

			if(!($opt_skip_existing && file_exists($filename))) {

				require 'dart.x264.php';

				$handbrake->output_filename($filename);

				$handbrake_command  = $handbrake->get_executable_string();

				echo "$handbrake_command\n";
			}

		}

	}
