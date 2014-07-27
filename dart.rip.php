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

			$bar = new Console_ProgressBar('[%bar%] %percent%'." ($num_episodes episodes)", ':', ' :D ', 80, $num_episodes);
			$i = 0;

			foreach($dvd_episodes as $episode_id) {

				clearstatcache();

				// New instance of a DB episode
				$episodes_model = new Episodes_Model($episode_id);
				$episode_season = $episodes_model->get_season();
				$episode_title = $episodes_model->title;
				$episode_part = $episodes_model->part;
				$episode_filename = get_episode_filename($episode_id);

				$tracks_model = new Tracks_Model($episodes_model->track_id);
 				$track_number = $tracks_model->ix;

				// Get the series ID
				$series_id = $episodes_model->get_series_id();

				// New instance of a DB series
				$series_model = new Series_Model($series_id);
				$collection_title = $series_model->get_collection_title();
				$series_title = $series_model->title;

				$series_queue_dir = $export_dir."queue/".formatTitle($series_title);

				$episode_filename = $export_dir."episodes/".$episode_filename;
				$mkv = "$episode_filename.mkv";

				// Check to see if file exists, if not, rip it
				if(!$max || ($max && $num_queued < $max)) {

					if(!file_exists($mkv)) {

						$queue_model->add_episode($episode_id, php_uname('n'));
						$num_queued++;

						/** Create directory to dump files to */
						if(!is_dir($series_queue_dir))
							mkdir($series_queue_dir, 0755, true);

						// Create a symlink to the ISO in the queue directory
						$series_queue_dir_iso = $series_queue_dir."/".$episodes_model->get_iso();
						if(!file_exists($series_queue_dir_iso))
							symlink($device_realpath, $series_queue_dir_iso);

					}

					// Bump up the queue if we are accessing the drive directly
					if($device_is_hardware)
						$queue_model->prioritize();
				}

				$i++;

				$bar->update($i);

				if(($i + 1) == $max)
					break(2);

			}

		}

		echo "\n";

	}
