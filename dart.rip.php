<?

	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */

	if($rip && $disc_archived) {

		$queue_model = new Queue_Model;

		if($verbose)
			shell::msg("[Rip]");

		$dvd_episodes = $dvds_model->get_episodes();

		$num_episodes = count($dvd_episodes);

		// Passed the argument to rip it, but there are no
		// episodes ... so cancel ejecting it since access
		// is probably likely.
		if(!$num_episodes) {
			shell::msg("The disc is archived, but there are no episodes to rip.");
			shell::msg("Check the frontend to see if titles need to be added.");
		} else {

			/** Create directory to dump files to */
 			if(!is_dir($export_dir))
 				mkdir($export_dir, 0755);

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
				$episode_filename = $export_dir.$episode_filename;

				$tracks_model = new Tracks_Model($episodes_model->track_id);
 				$track_number = $tracks_model->ix;

				// Get the series ID
				$series_id = $episodes_model->get_series_id();

				// New instance of a DB series
				$series_model = new Series_Model($series_id);
				$series_title = $series_model->title;

				$mkv = "$episode_filename.mkv";

				// Check to see if file exists, if not, rip it
				if(!file_exists($mkv))
					$queue_model->add_episode($episode_id, php_uname('n'));

				// Bump up the queue if we are accessing the drive directly
				if($is_symlink)
					$queue_model->prioritize();

				$i++;

				$bar->update($i);

				if(($i + 1) == $max)
					break(2);

			}

		}

		echo "\n";

	}
