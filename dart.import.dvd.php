<?php

	// DVD

	$dvdread_id = $dvd->dvdread_id;
	$dvd_title = $dvd->title;

	// Create a new database record for the DVD
	if($new_dvd) {

		echo "[Import]\n";

		$dvds_model_id = $dvds_model->create_new();

		if($debug)
			echo "* Created new DVD id: $dvds_model_id\n";
	}

	// Hand off control of whether a DVD is missing metadata to the dvds model,
	// but if it is flagged as missing metadata, run *all* the checks in the
	// import process, regardless of what the model says.

	if($new_dvd || ($disc_indexed && $missing_dvd_metadata)) {

		if(!$new_dvd) {
			echo "[Metadata]\n";
			echo "* Updating legacy DVD metadata\n";
		}

		if(!$dvds_model->dvdread_id) {
			echo "* dvdread id = $dvdread_id\n";
			$dvds_model->dvdread_id = $dvdread_id;
		}

		if(!$dvds_model->title) {
			echo "* title: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}

		$dvd_filesize = $dvd->size;
		if($dvds_model->filesize != $dvd_filesize) {
			echo "* filesize: $dvd_filesize\n";
			$dvds_model->filesize = $dvd_filesize;
		}

		if(!$dvds_model->side) {
			echo "* DVD side:\t".$dvd->side."\n";
			$dvds_model->side = $dvd->side;
		}

	}

	// Flag it as indexed
	$disc_indexed = true;

?>
