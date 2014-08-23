<?php

	/** Chapters **/

	$dvd_chapters = $dvd_track->getChapters();

	foreach($dvd_chapters as $chapter_number => $chapter_data) {

		// Lookup the database chapters.id
		$chapters_model = new Chapters_Model;
		$chapters_ix = $chapter_data['ix'];
		$chapters_model_id = $chapters_model->find_chapters_id($tracks_model_id, $chapters_ix);

		// Create a new record
		if(!$chapters_model_id) {

			$chapters_model_id = $chapters_model->create_new();

			if($debug)
				echo "! Created new chapters id: $chapters_model_id\n";

			$chapters_model->track_id = $tracks_model_id;
			$chapters_model->ix = $chapters_ix;

		} else {

			$chapters_model->load($chapters_model_id);

		}

		if(is_null($chapters_model->length))
			$chapters_model->length = $chapter_data['length'];

		if(is_null($chapters_model->startcell))
			$chapters_model->startcell = $chapter_data['startcell'];

	}


