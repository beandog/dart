<?php

	/** Audio Tracks **/

	$audio_tracks = $dvd->title_track_audio_tracks;

	if($debug)
		echo "! Title track $title_track has ".$dvd->title_track_audio_tracks." audio tracks\n";

	for($audio_track = 1; $audio_track < $audio_tracks + 1; $audio_track++) {

		$dvd->load_audio_track($title_track, $audio_track);

		// Lookup the database audio.id
		$audio_model = new Audio_Model;
		$audio_model_id = $audio_model->find_audio_id($tracks_model_id, $audio_track);

		// Create a new record
		if(!$audio_model_id) {

			$audio_model_id = $audio_model->create_new();

			if($debug)
				echo "! Created new audio id: $audio_model_id\n";
			$audio_model->track_id = $tracks_model_id;

		} else {

			$audio_model->load($audio_model_id);

		}

		if(is_null($audio_model->ix))
			$audio_model->ix = $audio_track;

		if($dvd->audio_track_lang_code && !$audio_model->langcode)
			$audio_model->langcode = $dvd->audio_track_lang_code;

		if($dvd->audio_track_format && !$audio_model->format)
			$audio_model->format = $dvd->audio_track_format;

		if($dvd->audio_track_channels && is_null($audio_model->channels))
			$audio_model->channels = $dvd->audio_track_channels;

		if($dvd->audio_track_stream_id && !$audio_model->streamid)
			$audio_model->streamid = $dvd->audio_track_stream_id;

	}
