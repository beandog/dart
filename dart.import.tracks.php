<?php

	/** Tracks **/

	if($disc_type == 'bluray')
		goto bluray_disc;
	elseif($disc_type == 'cd')
		goto cd_rom;

	$dvd_title_tracks = $dvd->title_tracks;

	// If it comes to this point, there's probably an issue reading the DVD
	// directly. Either way, the import will still work, so it's debatable
	// whether this should die here now and kill the progress of the script
	// or not. This is something where the dvd_debug program could come into play.
	// Ideally, that would run first and flag anomalies for me directly.
	if(!$dvd_title_tracks) {

		$broken_dvd = true;
		echo "? No tracks? No good!!!!\n";

		// BEEP!
		beep_error();

		goto next_disc;

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

		if($tracks_model->filesize != $dvd->title_track_filesize) {
			$tracks_model->filesize = $dvd->title_track_filesize;
			if($debug)
				echo "* Updating track filesize: ".ceil($dvd->title_track_filesize / 1048576)." MBs\n";
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
		require 'dart.import.cells.php';

	}

	if($disc_type == 'dvd')
		goto next_disc;

	bluray_disc:

	$bd_playlists = $dvd->bd_playlists;
	$num_playlists = count($bd_playlists);

	// If it comes to this point, there's probably an issue reading the DVD
	// directly. Either way, the import will still work, so it's debatable
	// whether this should die here now and kill the progress of the script
	// or not. This is something where the dvd_debug program could come into play.
	// Ideally, that would run first and flag anomalies for me directly.
	if(!$num_playlists) {

		$broken_dvd = true;
		echo "? No playlists? No good!!!!\n";

		// BEEP!
		beep_error();

		goto next_disc;

	}

	if($missing_dvd_tracks_metadata && !$new_dvd)
		echo "* Updating BD playlists metadata: ";
	elseif($opt_archive && !$new_dvd)
		echo "* Checking playlists for full archival: ";
	elseif($opt_import && $new_dvd)
		echo "* Importing $num_playlists tracks: ";

	foreach($bd_playlists as $playlist) {

		// reference
		$title_track = $playlist;

		echo "$playlist ";

		$playlist_loaded = $dvd->load_playlist($playlist);

		// Lookup the database tracks.id
		$tracks_model = new Tracks_Model;
		$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $playlist);

		// Create new database entry
		if(!$tracks_model_id) {

			$tracks_model_id = $tracks_model->create_new();

			if($debug)
				echo "* Created new track id: $tracks_model_id\n";

			$new_title_tracks++;

			$tracks_model->dvd_id = $dvds_model_id;
			$tracks_model->ix = $playlist;
			$tracks_model->closed_captioning = 0;

		} else {

			$tracks_model->load($tracks_model_id);

		}

		// Handle broken tracks! :D
		if(!$playlist_loaded) {
			echo "\n";
			echo "* Opening $device playlist $playlist FAILED\n";

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

		if($tracks_model_length != $dvd->playlist_seconds) {
			$tracks_model->length = $dvd->playlist_seconds;
			if($debug)
				echo "* Updating playlist length (msecs): $tracks_model_length -> ".$dvd->playlist_seconds."\n";
		}

		if($tracks_model->resolution != $dvd->video_resolution) {
			$tracks_model->resolution = $dvd->video_resolution;
			if($debug)
				echo "* Updating video resolution: ".$dvd->video_resolution."\n";
		}

		if($tracks_model->aspect != $dvd->video_aspect_ratio) {
			$tracks_model->aspect = $dvd->video_aspect_ratio;
			if($debug)
				echo "* Updating aspect ratio: ".$dvd->video_aspect_ratio."\n";
		}

		if($tracks_model->codec != $dvd->video_codec) {
			$tracks_model->codec = $dvd->video_codec;
			if($debug)
				echo "* Updating video codec: ".$dvd->video_codec."\n";
		}

		if($tracks_model->fps != $dvd->video_fps) {
			$tracks_model->fps = $dvd->video_fps;
			if($debug)
				echo "* Updating video FPS: ".$dvd->video_fps."\n";
		}

		$filesize = $dvd->playlist_filesize;
		if($tracks_model->filesize != $filesize && $filesize) {
			$tracks_model->filesize = $filesize;
			if($debug)
				echo "* Updating playlist filesize: $filesize\n";
		}

		require 'dart.import.audio.php';
		require 'dart.import.subtitles.php';
		require 'dart.import.chapters.php';

	}

	goto next_disc;

	cd_rom:

	if($disc_type == 'cd') {

		$cue_filename = "$dvdread_id.cue";

		if(!file_exists($cue_filename)) {
			echo "* Need $cue_filename to import CD\n";
			goto next_disc;
		}

		$tracks_model = new Tracks_Model;

		$cmd = "cueconvert $cue_filename";

		exec($cmd, $output, $retval);

		$arr_cue_tracks = preg_grep('/^FILE/', $output);

		$ix = 1;

		// length in database is duration in msecs relative to starting index
		// rational is that using ffmpeg to seek to track, would start with -ss
		// to seek point, then use -t relative to the next track
		foreach($arr_cue_tracks as $cue_track) {

			$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $ix);

			if(!$tracks_model_id) {
				$tracks_model_id = $tracks_model->create_new();
				$tracks_model->ix = $ix;
				$tracks_model->dvd_id = $dvds_model_id;
			}

			$tracks_model->load($tracks_model_id);

			$arr = explode(' ', $cue_track);
			$start_point = $arr[2];
			$stop_point = null;
			if(count($arr) == 4)
				$stop_point = $arr[3];

			// Starting format is MM:SS:MS, need to convert MS from :XX to .XX
			$arr = explode(':', $start_point);
			$start_ms = 0;
			if(count($arr) == 1)
				$start_ms = $arr[0];
			else {
				$start_ms = $arr[0] * 60;
				$start_ms += $arr[1];
				$start_ms += $arr[2] / 100;
			}

			$tracks_model->length = $start_ms;

			$ix++;

		}

	}

	// Moving right along ...
	next_disc:

	// Close off the newline that the track count was displaying
	echo "\n";
