<?php

	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */

	if($opt_rip && $disc_indexed && !$broken_dvd) {

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
				$episode_in_queue = $queue_model->episode_in_queue($episode_id);

				if((!$max || ($max && $num_queued < $max)) && !$episode_in_queue) {

					clearstatcache();

					$episodes_model = new Episodes_Model($episode_id);
					$episode = $episodes_model->get_metadata();
					$episode_title = $episodes_model->get_long_title();
					$episode_mkv = $export_dir."episodes/".safe_filename_title($episode['series_title'])."/".safe_filename_title($episode_title).".mkv";

					if(!file_exists($episode_mkv)) {

						$queue_model->add_episode($episode_id);
						$num_queued++;

						$episode->create_queue_iso_symlink($device_realpath);

						$i++;

						$bar->update($i);

					}

				}

			}

			echo "\n[Queue]\n";
			echo "* Total episodes in queue: $num_episodes\n";

		}

		echo "\n";

	}
