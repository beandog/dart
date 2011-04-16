<?
	
	// Display info about disc
	if($info)
		if($disc_archived) {
			display_info($uniq_id);
			
			foreach($dvd_episodes as $episode_id) {
				$episodes_model = new Episodes_Model($episode_id);
				$display_name = $episodes_model->get_display_name();
				echo("$display_name\n");
			}
			
		} else
			shell::msg("Disc is not archived");