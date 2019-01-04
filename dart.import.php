<?php

	/**
	 * --import, --archive
	 *
	 * Import a new DVD into the database or check for missing metadata
	 */

	// Keep track of some numbers for debugging or displaying when metadata
	// is brought to spec
	$new_title_tracks = 0;
	$new_audio_tracks = 0;
	$new_subtitle_tracks = 0;
	$new_chapters = 0;
	$new_cells = 0;

	$missing_dvd_metadata = false;
	$missing_dvd_tracks_metadata = false;

	if($dvds_model->dvd_missing_metadata()) {
		$missing_dvd_metadata = true;
	}
	if($dvds_model->dvd_tracks_missing_metadata()) {
		$missing_dvd_tracks_metadata = true;
	}

	if($opt_archive && !$missing_dvd_metadata && !$missing_dvd_tracks_metadata) {
		echo "* Archive:\tNo legacy metadata! :D\n";
	}

	// Some conditions apply where importing may be skipped.

	// Set this to say if we *can* import it, if requested
	$allow_import = false;

	// Set a persistent value to see if it was a new DVD or not
	$new_dvd = false;
	if(!$disc_indexed)
		$new_dvd = true;

	if($opt_import || $opt_archive || $new_dvd || $missing_dvd_metadata || $missing_dvd_tracks_metadata)
		$allow_import = true;

	// If only creating the ISO is requested, then skip import. This is
	// common when there are problems accessing the DVD, and import is
	// expected to fail.
	if($opt_dump_iso && (!$opt_import && !$opt_archive))
		$allow_import = false;

	// Start import
	if($access_device && $allow_import) {

		if($new_dvd || $missing_dvd_metadata)
			require 'dart.import.dvd.php';

		if($new_dvd || $missing_dvd_tracks_metadata)
			require 'dart.import.tracks.php';

		if($opt_import && $new_dvd)
			echo "* New DVD imported! Yay! :D\n";

	}

	// Metadata meets spec v3.1: dvd_info database
	if($missing_dvd_metadata || $missing_dvd_tracks_metadata) {

		echo "[Import]\n";

		$missing_dvd_metadata = false;
		$missing_dvd_tracks_metadata = false;
		$dvds_model->metadata_spec = $dvds_model->max_metadata_spec();

		if($new_title_tracks)
			echo "* New title tracks: $new_title_tracks\n";
		if($new_audio_tracks)
			echo "* New audio tracks: $new_audio_tracks\n";
		if($new_subtitle_tracks)
			echo "* New subtitle tracks: $new_subtitle_tracks\n";
		if($new_chapters)
			echo "* New chapters: $new_chapters\n";
		if($new_cells)
			echo "* New cells: $new_cells\n";

	}

