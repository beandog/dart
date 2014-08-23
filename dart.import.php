<?php

	/**
	 * --import
	 *
	 * Import a new DVD into the database
	 */

	// Keep track of some numbers for debugging or displaying when metadata
	// is brought to spec
	$new_title_tracks = 0;
	$new_audio_tracks = 0;
	$new_subtitle_tracks = 0;
	$new_chapters = 0;
	$new_cells = 0;

	$missing_dvd_metadata = $dvds_model->missing_metadata();

	if($archive && !$missing_dvd_metadata) {
		echo "* Archive:\tNo legacy metadata! :D\n";
	}

	// Some conditions apply where importing may be skipped.

	// Set this to say if we *can* import it, if requested
	$allow_import = false;

	if($import || !$disc_indexed || $missing_dvd_metadata)
		$allow_import = true;

	// If only creating the ISO is requested, then skip import.  This is
	// common when there are problems accessing the DVD, and import is
	// expected to fail.
	if($dump_iso && (!$import && !$archive))
		$allow_import = false;

	// Start import
	if($access_device && $allow_import) {

		require 'dart.import.dvd.php';
		require 'dart.import.tracks.php';

		if($import && !$missing_dvd_metadata) {
			echo "* New DVD imported! Yay! :D\n";
		}

	}

	// Metadata meets spec v3: dvd_info database
	if($missing_dvd_metadata) {
		$missing_dvd_metadata = false;
		$dvds_model->metadata_spec = 3;

		echo "* DVD model now meets latest database spec!!\n";
		echo "* New title tracks: $new_title_tracks\n";
		echo "* New audio tracks: $new_audio_tracks\n";
		echo "* New subtitle tracks: $new_subtitle_tracks\n";
		echo "* New chapters: $new_chapters\n";
		echo "* New cells: $new_cells\n";

	}

