<?php

	// Display encode instructions about a disc
	if($opt_encode_info) {

		$dvd_episodes = $dvds_model->get_episodes();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$display_name = $episodes_model->get_display_name();
			echo "* $display_name\n";
		}

	}

	// Display info about disc
	if($opt_info) {

		echo "[Info]\n";

		$dvd_episodes = $dvds_model->get_episodes();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$display_name = $episodes_model->get_display_name();
			echo "* $display_name\n";
		}

	}

