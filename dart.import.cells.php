<?php

	/** Cells **/

	for($cell = 1; $cell < $dvd->title_track_cells + 1; $cell++) {

		// Lookup the database chapters.id
		$cells_model = new Cells_Model;
		$cells_model_id = $cells_model->find_cells_id($tracks_model_id, $cell);

		$cell_loaded = $dvd->load_cell($title_track, $cell);

		if(!$cell_loaded) {
			echo "* Could not load cell $cell on track $title_track\n";
			break;
		}

		// Create a new record
		if(!$cells_model_id) {

			$cells_model_id = $cells_model->create_new();

			if($debug)
				echo "* Created new cells id: $cells_model_id\n";

			$new_cells++;

			$cells_model->track_id = $tracks_model_id;
			$cells_model->ix = $cell;

		} else {

			$cells_model->load($cells_model_id);

		}

		$cell_seconds = $dvd->cell_seconds;

		// Database model returns a string
		$cells_model_length = floatval($cells_model->length);

		if($cells_model_length != $cell_seconds) {

			$cells_model->length = $cell_seconds;
			if($debug)
				echo "* Updating cell length: $cells_model_length -> $cell_seconds\n";
		}

	}

