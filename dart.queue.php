<?
	/**
	 * --queue
	 *
	 * Get episode list in the queue
	 *
	 */

	$queue_model = new Queue_Model;

	$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);

	if($queue) {

		if(count($queue_episodes))
			echo "[Total: ".count($queue_episodes)."]\n";

		foreach($queue_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$str = $episodes_model->get_display_name();

			if($verbose) {
				$str = get_episode_filename($episode_id);
				$str = $export_dir.$str;
			}

			if($verbose)
				$str .= " (".$episodes_model->get_iso().")";

			echo("$str\n");
		}

	}
