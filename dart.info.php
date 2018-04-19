<?php

	// Display info about disc
	if($opt_info) {

		echo "[Info]\n";

		$dvd_episodes = $dvds_model->get_episodes();
		$skip_episodes = array();

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$display_name = $episodes_model->get_display_name();
			if($episodes_model->skip)
				$skip_episodes[$episode_id] = $display_name;
			else
				echo "* $display_name\n";
		}

		if(count($skip_episodes)) {
			echo "[Skips]\n";
			foreach($skip_episodes as $episode_id => $display_name) {
				echo "* $display_name\n";
			}
		}

	}

