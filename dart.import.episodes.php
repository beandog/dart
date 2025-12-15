<?php

	// Update episodes with missing metadata

	// Unused right now, don't need crop values
	/*
	if($disc_indexed && $access_device && $disc_type == 'dvd' && $missing_episode_metadata) {

		echo "[Episodes Metadata]\n";

		$dvd_episodes = $dvds_model->get_episodes();

		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);

			if($episodes_model->crop == '') {

				$tracks_model = new Tracks_Model($episodes_model->track_id);

				$ffmpeg = new FFMpeg();

				$ffmpeg->input_filename($device);

				$ffmpeg->input_track($tracks_model->ix);

				if($debug)
					$ffmpeg->debug();

				if($verbose)
					$ffmpeg->verbose();

				$starting_chapter = $episodes_model->starting_chapter;
				$ending_chapter = $episodes_model->ending_chapter;
				if($starting_chapter || $ending_chapter) {
					$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
				}

				$episode_crop = $ffmpeg->cropdetect();

				$episode_title = $episodes_model->title;
				echo "* Crop '$episode_crop' $episode_title\n";

				$episodes_model->crop = $episode_crop;

			}

		}

	}
	*/
