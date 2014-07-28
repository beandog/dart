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

					$episode = array();
					$series = array();
					$collection = array();

					$series = array(
						'id' => $series_id,
						'title' => $series_title,
						'queue_dir' => $export_dir."queue/".formatTitle($series_title),
					);

					$episode = array(
						'id' => $episode_id,
						'title' => $episodes_model->title,
						'season' => $episodes_model->get_season(),
						'part' => $episodes_model->part,
						'filename' => basename(get_episode_filename($episode_id)),
						'src_iso' => $series['queue_dir']."/".$episodes_model->get_iso(),
						'dest_dir' => $export_dir."episodes/".formatTitle($series_title),
						'dest_mkv' => $export_dir."episodes/".formatTitle($series_title)."/".basename(get_episode_filename($episode_id)).".mkv",
					);

					$collection = array(
						'title' => $series_model->get_collection_title(),
					);


					if(!file_exists($episode['dest_mkv'])) {

						$queue_model->add_episode($episode_id, php_uname('n'));
						$num_queued++;

						/** Create directory to dump files to */
						if(!is_dir($series['queue_dir']))
							mkdir($series['queue_dir'], 0755, true);

						// Create a symlink to the ISO in the queue directory
						if(!file_exists($episode['src_iso']))
							symlink($device_realpath, $episode['src_iso']);

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
