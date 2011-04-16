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
			$display_name = $episodes_model->get_display_name();
			echo("$display_name\n");
		}
	
	}