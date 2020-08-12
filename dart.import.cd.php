<?php

	// CD

	$dvdread_id = $dvd->dvdread_id;

	// Create a new database record for the DVD
	if($new_dvd) {

		echo "[Import]\n";

		$dvds_model_id = $dvds_model->create_new();

		if($debug)
			echo "* Created new CD id: $dvds_model_id\n";

		$dvds_model->dvdread_id = $dvd->dvdread_id;

	}

	if($new_dvd || ($disc_indexed && $missing_cd_metadata)) {

		if(!$new_dvd) {
			echo "[Metadata]\n";
			echo "* Updating legacy CD metadata\n";
		}

	}

	// Flag it as indexed
	$disc_indexed = true;

?>
