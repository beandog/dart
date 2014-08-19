<?php

	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */

	if($rip && $disc_indexed && !$broken_dvd) {

		$queue_model = new Queue_Model;

		echo "[Rip]\n";

		$dvd_episodes = $dvds_model->get_episodes();

		$num_episodes = count($dvd_episodes);

		$num_queued = 0;

		// Passed the argument to rip it, but there are no
		// episodes ... so cancel ejecting it since access
		// is probably likely.
		if(!$num_episodes) {
			echo "The disc is archived, but there are no episodes to rip.\n";
			echo "Check the frontend to see if titles need to be added.\n";
		} else {

			if($max) {
				$max_episodes = min($max, $num_episodes);
				echo "* $max_episodes episodes max.\n";
			} else
				$max_episodes = $num_episodes;

			$bar = new Console_ProgressBar("[%bar%] %percent%", ":", " :D ", 80, $max_episodes);
			$i = 0;

			foreach($dvd_episodes as $episode_id) {

				$episodes_model = new Episodes_Model($episode_id);
				$queue_episode_status = $queue_model->get_episode_status($episode_id);

				if((!$max || ($max && $num_queued < $max)) && is_null($queue_episode_status)) {

					clearstatcache();

					$episode = new MediaEpisode($episode_id, $export_dir);

					if(!file_exists($episode->episode_mkv)) {

						$queue_model->add_episode($episode_id, php_uname('n'));
						$num_queued++;

						$episode->create_queue_iso_symlink($device_realpath);

						$i++;

						$bar->update($i);

					}

					// Bump up the queue if we are accessing the drive directly
					if($device_is_hardware)
						$queue_model->prioritize();

				}

			}

			echo "\n[Queue]\n";
			echo "* Total episodes in queue: ".count($queue_model->get_episodes($hostname));

		}

		echo "\n";

	}
