<?
	
	// Display info about disc
	if($info && $disc_archived) {
	
		if($verbose)
			shell::msg("[Info]");
		
		$dvd_episodes = $dvds_model->get_episodes();
		
		// Display the episode names
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$display_name = $episodes_model->get_display_name();
			echo("$display_name\n");
		}
		
	} elseif($info && !$disc_archived){
		shell::msg("Disc is not archived");
	}