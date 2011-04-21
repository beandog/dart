<?
	/**
	 * --queue
	 *
	 * Get episode list in the queue
	 *
	 */
	
	$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);
	
	if($queue) {
	
		foreach($queue_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$str = $episodes_model->get_display_name();
			
			if($verbose) {
				
				$episode_number = $episodes_model->get_number();
				
				if($episode_number)
					$str .= " (#$episode_number)";
				
			}
			
			echo("$str\n");
		}
	
	}