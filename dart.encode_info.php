<?php

	// Display encode instructions about a disc
	if($opt_encode_info) {

		$dvd_source_iso = $device;

		$opt_encode = true;

		$dvd_episodes = $dvds_model->get_episodes();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$episode['track_ix'] = $tracks_model->ix;
			$display_name = $episodes_model->get_display_name();
			$queue_files['handbrake_output_filename'] = "$display_name.$container";
			$series_model = new Series_Model($episodes_model->get_series_id());
			$dvd_episode_iso = $device;
			$target_files['episode_mkv'] = safe_filename_title($display_name).".$container";

			// placeholders
			$episode_title = $display_name;
			$episode['starting_chapter'] = $episodes_model->starting_chapter;
			$episode['ending_chapter'] = $episodes_model->ending_chapter;
			$collection_title = 'Aquaman';

			echo "[Encode Info]\n";
			echo "* Name:\t\t$display_name\n";
			echo "* DVD Track:\t".$tracks_model->ix."\n";
			echo "* Episode ID:\t$episode_id\n";
			echo "* Track ID:\t".$episodes_model->track_id."\n";

			require 'dart.x264.php';

			// Override the HandBrake output filename
			$filename = $episode_title;
			$filename = str_replace(':', 'EECO', $filename);
			$filename = str_replace('\'', 'EESQ', $filename);
			$filename = str_replace('"', 'EEDQ', $filename);
			$filename = str_replace('!', 'EEEM', $filename);
			$filename = str_replace('$', 'EEDS', $filename);
			$filename = str_replace('?', 'EEQM', $filename);
			$filename = str_replace('/', 'EEBS', $filename);
			$filename = str_replace('\\', 'EEFS', $filename);
			$filename = str_replace('&', 'EEAM', $filename);
			$filename .= ".$container";
			$handbrake->output_filename($filename);

			$handbrake_command  = $handbrake->get_executable_string();

			echo "\n$handbrake_command\n";

			echo "*****************************************************\n";

		}

		$opt_encode = false;

		exit;

	}
