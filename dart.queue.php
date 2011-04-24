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
				
				$str = get_episode_filename($episode_id);
				
			}
			
			if($verbose)
				$str .= " (".$episodes_model->get_iso().")";
			
			echo("$str\n");
		}
	
	}