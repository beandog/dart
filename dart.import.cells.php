<?php

	/** Cells **/

	for($cell = 1; $cell < $dvd->title_track_cells + 1; $cell++) {

		$cell_loaded = $dvd->load_cell($title_track, $cell);

		if(!$cell_loaded) {
			echo "! Could not load cell $cell on title track $title_track.  Skipping\n";
			break;
		}

		$cells_model = new Cells_Model;
		$cells_model_id = $cells_model->find_cells_id($tracks_model_id, $cell);

		// Create a new record
		if(!$cells_model_id) {

			$cells_model_id = $cells_model->create_new();

			if($debug)
				echo "! Created new cells id: $cells_model_id\n";

			$new_cells++;

			$cells_model->track_id = $tracks_model_id;
			$cells_model->ix = $cell;
			$cells_model->length = $dvd->cell_msecs;
			$cells_model->first_sector = $dvd->cell_first_sector;
			$cells_model->last_sector = $dvd->cell_last_sector;

		} else {

			$cells_model->load($cells_model_id);

		}

	}

