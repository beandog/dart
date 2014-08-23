<?php

	/** Chapters **/

	for($chapter = 1; $chapter < $dvd->title_track_chapters + 1; $chapter++) {

		$chapter_loaded = $dvd->load_chapter($title_track, $chapter);

		if(!$chapter_loaded) {
			echo "! Could not load chapter $chapter on title track $title_track.  Skipping\n";
			break;
		}

		$chapters_model = new Chapters_Model;
		$chapters_model_id = $chapters_model->find_chapters_id($tracks_model_id, $chapter);

		// Create a new record
		if(!$chapters_model_id) {

			$chapters_model_id = $chapters_model->create_new();

			if($debug)
				echo "! Created new chapters id: $chapters_model_id\n";

			$new_chapters++;

			$chapters_model->track_id = $tracks_model_id;
			$chapters_model->ix = $chapter;

		} else {

			$chapters_model->load($chapters_model_id);

		}

		// Database model returns a string
		$chapters_model_length = floatval($chapters_model->length);
		if($chapters_model_length != $dvd->chapter_seconds) {
			$chapters_model->length = $dvd->chapter_seconds;
			if($debug) {
				echo "* Updating chapter length: $chapters_model_length -> ".$dvd->chapter_seconds."\n";
			}
		}

		$chapters_model_startcell = intval($chapters_model->startcell);
		if($chapters_model_startcell != $dvd->chapter_startcell) {
			$chapters_model->startcell = $dvd->chapter_startcell;
			if($debug) {
				echo "* Updating starting cell: $chapters_model_startcell -> ".$dvd->chapter_startcell."\n";
			}
		}

	}

