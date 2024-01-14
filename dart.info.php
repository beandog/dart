<?php

	// Display info about disc
	if($opt_info && $disc_indexed) {

		echo "[Info]\n";

		$dvd_episodes = $dvds_model->get_episodes();
		$skip_episodes = array();

		if(!count($dvd_episodes))
			echo "* No episodes\n";

		if($dvds_model->has_max_tracks())
			echo "* Bugs: has 99 tracks\n";

		/*
		$dvd_bugs = trim($dvds_model->get_bugs());

		if($dvd_bugs)
			echo "* Bugs: $dvd_bugs\n";
		*/

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
