<?php

	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */

	if($rip && $disc_indexed) {

		$queue_model = new Queue_Model;

		if($verbose)
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

					$series_id = $episodes_model->get_series_id();
					$series_model = new Series_Model($series_id);
					$series_title = $series_model->title;
					$tracks_model = new Tracks_Model($episodes_model->track_id);
					$track_number = $tracks_model->ix;

					$episode = new MediaEpisode($export_dir, $device_realpath, $series_model->get_collection_title(), $series_model->title, $episodes_model->title, $episode_id);

					if(!file_exists($episode->episode_mkv)) {

						$queue_model->add_episode($episode_id, php_uname('n'));
						$num_queued++;

						$episode->create_queue_dir();
						$episode->create_queue_iso_symlink();

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
