<?php

	// BD

	$dvdread_id = $dvd->dvdread_id;
	$dvd_title = strtoupper($dvd->title);

	// Create a new database record for the BD
	if($new_dvd) {

		echo "[Import]\n";

		$dvds_model_id = $dvds_model->create_new();
		$dvds_model->bluray = 1;

		if($debug)
			echo "* Created new BD id: $dvds_model_id\n";
	}

	// Load or create the Blurays model
	$blurays_model = new Blurays_Model;
	$blurays_model_id = $blurays_model->load_dvd_id($dvds_model_id);
	if(!$blurays_model_id) {
		$blurays_model_id = $blurays_model->create_new();
		$blurays_model->dvd_id = $dvds_model_id;
	}

	// Hand off control of whether a DVD is missing metadata to the dvds model,
	// but if it is flagged as missing metadata, run *all* the checks in the
	// import process, regardless of what the model says.

	if($new_dvd || ($disc_indexed && $missing_dvd_metadata)) {

		if(!$new_dvd) {
			echo "[Metadata]\n";
			echo "* Updating legacy BD metadata\n";
		}

		if(!$dvds_model->dvdread_id) {
			echo "* dvdread id = $dvdread_id\n";
			$dvds_model->dvdread_id = $dvdread_id;
		}

		if(!$dvds_model->title) {
			echo "* title: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}

		// Old title could be either disc title or volume name
		if($device_is_hardware && $dvds_model->title != $dvd_title) {
			echo "* Volume: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}

		$dvd_filesize = $dvd->size;
		if($dvds_model->filesize != $dvd_filesize) {
			echo "* Filesize: $dvd_filesize\n";
			$dvds_model->filesize = $dvd_filesize;
		}

		if($dvds_model->bluray == 0) {
			echo "* Flagging as Blu-ray\n";
			$dvds_model->bluray = 1;
		}

		if($blurays_model->disc_id != $dvd->disc_id)
			$blurays_model->disc_id = $dvd->disc_id;

	}

	// Flag it as indexed
	$disc_indexed = true;

?>
