<?php

	// DVD

	$dvdread_id = $dvd->dvdread_id;
	$dvd_title = $dvd->title;

	echo "* Title: $dvd_title\n";

	// Create a new database record for the DVD
	if(!$disc_indexed) {

		echo "[Import]\n";

		$dvds_model_id = $dvds_model->create_new();

		if($debug)
			echo "* Created new DVD id: $dvds_model_id\n";

	} elseif($disc_indexed && $missing_dvd_metadata) {

		echo "[Metadata]\n";
		echo "* Updating legacy metadata\n";
	}

	if(!$dvds_model->dvdread_id) {
		echo "* dvdread id = $dvdread_id\n";
		$dvds_model->dvdread_id = $dvdread_id;
	}
	if(!$dvds_model->title) {
		echo "* title: $dvd_title\n";
		$dvds_model->title = $dvd_title;
	}
	if($missing_dvd_metadata || !$disc_indexed) {
		$dvd_filesize = $dvd->size();
		if($dvds_model->filesize != $dvd_filesize) {
			$dvds_model->filesize = $dvd_filesize;
		}
	}

	// Flag it as indexed
	$disc_indexed = true;

?>
