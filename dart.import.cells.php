<?php

	/** Cells **/

	$dvd_cells = $dvd_track->getCells();

	foreach($dvd_cells as $cells_ix => $cells_length) {

		// Lookup the database chapters.id
		$cells_model = new Cells_Model;
		$cells_model_id = $cells_model->find_cells_id($tracks_model_id, $cells_ix);

		// Create a new record
		if(!$cells_model_id) {

			$cells_model_id = $cells_model->create_new();

			if($debug)
				echo "! Created new cells id: $cells_model_id\n";

			$cells_model->track_id = $tracks_model_id;
			$cells_model->ix = $cells_ix;

		} else {

			$cells_model->load($cells_model_id);

		}

		if(is_null($cells_model->length))
			$cells_model->length = $cells_length;

	}

