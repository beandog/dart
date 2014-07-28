<?php
	/**
	 * --queue
	 *
	 * Get episode list in the queue
	 *
	 */

	$queue_model = new Queue_Model;

	$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);

	if($queue) {

		echo "[DVD Queue: ".count($queue_episodes)." episodes]\n";

		$counter = 1;

		$display_iso = "";

		foreach($queue_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$series_id = $episodes_model->get_series_id();
			$series_model = new Series_Model($series_id);
			$series_title = $series_model->title;

			$series = array(
				'id' => $series_id,
				'title' => $series_title,
				'queue_dir' => $export_dir."queue/".formatTitle($series_title),
				'volume' => $episodes_model->get_volume(),
			);

			$episode = array(
				'id' => $episode_id,
				'title' => $episodes_model->title,
				'season' => $episodes_model->get_season(),
				'part' => $episodes_model->part,
				'number' => $episodes_model->get_number(),
				'ix' => $episodes_model->ix,
				'starting_chapter' => $episodes_model->starting_chapter,
				'ending_chapter' => $episodes_model->ending_chapter,
				'filename' => basename(get_episode_filename($episode_id)),
				'src_iso' => $series['queue_dir']."/".$episodes_model->get_iso(),
				'queue_dir' => $export_dir."queue/".formatTitle($series_title)."/$episode_id.".formatTitle($episodes_model->title),
				'dest_dir' => $export_dir."episodes/".formatTitle($series_title),
				'dest_mkv' => $export_dir."episodes/".formatTitle($series_title)."/".basename(get_episode_filename($episode_id)).".mkv",
			);

			$episode_iso = $episodes_model->get_iso();

			if($episode_iso != $display_iso) {
				echo "* $episode_iso\n";
				$display_iso = $episode_iso;
			}

			echo "- $series_title: ".$episode['title']."\n";

			$counter++;
		}

	}
