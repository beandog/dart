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

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$arr_episode_titles = $episodes_model->get_episode_titles();
			if($episodes_model->skip)
				$skip_episodes[$episode_id] = $arr_episode_titles['display_title'];
			else
				echo "* ".$arr_episode_titles['display_title']."\n";
		}

		if(count($skip_episodes)) {
			echo "[Skips]\n";
			foreach($skip_episodes as $episode_id => $display_name) {
				echo "* $display_name\n";
			}
		}

	}
