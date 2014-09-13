<?php
	/**
	 * --queue
	 *
	 * Get episode list in the queue
	 *
	 */

	$queue_episodes = $queue_model->get_episodes();

	if($opt_queue) {

		$counter = 1;

		$display_iso = "";

		$d_series = array();

		foreach($queue_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$series_id = $episodes_model->get_series_id();
			$series_model = new Series_Model($series_id);
			$series_title = $series_model->title;

			$episode_iso = $episodes_model->get_iso();

			if($episode_iso != $display_iso) {
				$display_iso = $episode_iso;
			}

			$d_series[$series_title][$episode_iso][] = $episodes_model->title;

			$counter++;
		}

		if($queue_episodes) {

			foreach($d_series as $series_title => $arr_isos) {

				echo "[$series_title]\n";

				foreach($arr_isos as $iso => $arr_episodes) {

					echo "* $iso - ".count($arr_episodes)." episodes\n";
					echo "* Titles: ".implode(", ", $arr_episodes)."\n";
					echo "\n";

				}

			}


		}

		echo "[DVD Queue: ".count($queue_episodes)." episodes]\n";


	}
