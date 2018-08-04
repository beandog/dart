<?php

	/** Tracks **/

	$dvd_title_tracks = $dvd->title_tracks;

	// If it comes to this point, there's probably an issue reading the DVD
	// directly.  Either way, the import will still work, so it's debatable
	// whether this should die here now and kill the progress of the script
	// or not. This is something where the dvd_debug program could come into play.
	// Ideally, that would run first and flag anomalies for me directly.
	if(!$dvd_title_tracks) {

		$broken_dvd = true;
		echo "? No tracks? No good!!!!\n";

		// BEEP!
		beep_error();

		goto broken_dvd;

	}

	if($missing_dvd_tracks_metadata && !$new_dvd)
		echo "* Updating DVD tracks metadata: ";
	elseif($opt_archive && !$new_dvd)
		echo "* Checking tracks for full archival: ";
	elseif($opt_import && $new_dvd)
		echo "* Importing $dvd_title_tracks tracks: ";

	for($title_track = 1; $title_track < $dvd_title_tracks + 1; $title_track++) {

		echo "$title_track ";

		$title_track_loaded = $dvd->load_title_track($title_track);

		// Lookup the database tracks.id
		$tracks_model = new Tracks_Model;
		$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $title_track);

		// Create new database entry
		if(!$tracks_model_id) {

			$tracks_model_id = $tracks_model->create_new();

			if($debug)
				echo "* Created new track id: $tracks_model_id\n";

			$new_title_tracks++;

			$tracks_model->dvd_id = $dvds_model_id;
			$tracks_model->ix = $title_track;

		} else {

			$tracks_model->load($tracks_model_id);

		}

		// Handle broken tracks! :D
		if(!$title_track_loaded) {
			echo "\n";
			echo "* Opening $device track number $title_track FAILED\n";

			// Set some of the data so it won't trigger false positives on
			// missing metadata.
			$tracks_model->length = 0;
			$tracks_model->closed_captioning = 0;

			// BOOP!
			beep_error();

			continue;
		}

		// Database model returns a string
		$tracks_model_length = floatval($tracks_model->length);

		if($tracks_model_length != $dvd->title_track_seconds) {
			$tracks_model->length = $dvd->title_track_seconds;
			if($debug)
				echo "* Updating track length (msecs): $tracks_model_length -> ".$dvd->title_track_seconds."\n";
		}

		if($tracks_model->format != $dvd->video_format) {
			$tracks_model->format = $dvd->video_format;
			if($debug)
				echo "* Updating track format: ".$dvd->video_format."\n";
		}

		if($tracks_model->aspect != $dvd->video_aspect_ratio) {
			$tracks_model->aspect = $dvd->video_aspect_ratio;
			if($debug)
				echo "* Updating aspect ratio: ".$dvd->video_aspect_ratio."\n";
		}

		if($tracks_model->vts != $dvd->title_track_vts) {
			$tracks_model->vts = $dvd->title_track_vts;
			if($debug)
				echo "* Updating VTS: ".$dvd->title_track_vts."\n";
		}

		if($tracks_model->ttn != $dvd->title_track_ttn) {
			$tracks_model->ttn = $dvd->title_track_ttn;
			if($debug)
				echo "* Updating TTN: ".$dvd->title_track_ttn."\n";
		}

		require 'dart.import.audio.php';
		require 'dart.import.subtitles.php';
		require 'dart.import.chapters.php';

	}

	// Close off the newline that the track count was displaying
	echo "\n";

	// Moving right along ...
	broken_dvd:
