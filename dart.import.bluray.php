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

	if($new_dvd || ($disc_indexed && ($missing_dvd_metadata || $missing_bluray_metadata))) {

		if(!$new_dvd) {
			echo "[Metadata]\n";
			echo "* Updating legacy BD metadata\n";
		}

		if(!$dvds_model->dvdread_id) {
			echo "* dvdread id = $dvdread_id\n";
			$dvds_model->dvdread_id = $dvdread_id;
		}

		if($access_device && $dvds_model->title != $dvd_title) {
			echo "* title: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}

		if($access_device && $blurays_model->disc_title !== $dvd->disc_name) {
			echo "* Disc title:\t".$dvd->disc_name."\n";
			$blurays_model->disc_title = $dvd->disc_name;
		}

		if($access_device && $blurays_model->disc_id != $dvd->disc_id) {
			echo "* AACs ID: ".$dvd->disc_id."\n";
			$blurays_model->disc_id = $dvd->disc_id;
		}

		$dvd_filesize = $dvd->size;
		if($access_device && $dvds_model->filesize != $dvd_filesize) {
			echo "* Filesize: $dvd_filesize\n";
			$dvds_model->filesize = $dvd_filesize;
		}

		if($dvds_model->bluray == 0) {
			echo "* Flagging as Blu-ray\n";
			$dvds_model->bluray = 1;
		}

	}

	// Flag it as indexed
	$disc_indexed = true;

?>
