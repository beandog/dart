<?php

	/** Audio Tracks **/

	$audio_tracks = $dvd->title_track_audio_tracks;

	for($audio_track = 1; $audio_track < $audio_tracks + 1; $audio_track++) {

		$dvd->load_audio_track($title_track, $audio_track);

		// Lookup the database audio.id
		$audio_model = new Audio_Model;
		$audio_model_id = $audio_model->find_audio_id($tracks_model_id, $audio_track);

		// Create a new record
		if(!$audio_model_id) {

			$audio_model_id = $audio_model->create_new();

			if($debug)
				echo "* Created new audio id: $audio_model_id\n";

			$new_audio_tracks++;

			$audio_model->track_id = $tracks_model_id;
			$audio_model->ix = $audio_track;

		} else {

			$audio_model->load($audio_model_id);

		}

		if($audio_model->langcode != $dvd->audio_track_lang_code) {
			if($debug)
				echo "* Updating audio lang code: ".$audio_model->langcode." -> ".$dvd->audio_track_lang_code."\n";
			$audio_model->langcode = $dvd->audio_track_lang_code;
		}

		if($audio_model->format != $dvd->audio_track_codec) {
			if($debug)
				echo "* Updating audio codec: ".$audio_model->format." -> ".$dvd->audio_track_codec."\n";
			$audio_model->format = $dvd->audio_track_codec;
		}

		if($audio_model->channels != $dvd->audio_track_channels) {
			if($debug)
				echo "* Updating audio channels: ".$audio_model->channels." -> ".$dvd->audio_track_channels."\n";
			$audio_model->channels = $dvd->audio_track_channels;
		}

		if($audio_model->streamid != $dvd->audio_track_stream_id) {
			if($debug)
				echo "* Updating audio stream id: ".$audio_model->streamid." -> ".$dvd->audio_track_stream_id."\n";
			$audio_model->streamid = $dvd->audio_track_stream_id;
		}

		if(is_null($audio_model->active) || $audio_model->active != $dvd->audio_track_active) {
			if($debug)
				echo "* Updating audio track active: ".(is_null($audio_model->active) ? "unset" : "false")." -> ".($dvd->audio_track_active ? "true" : "false")."\n";
			$audio_model->active = $dvd->audio_track_active;
		}

	}

	// Set the default audio track.
	if(is_null($tracks_model->audio_ix)) {
		if($audio_tracks) {
			$audio_ix = $tracks_model->get_best_quality_audio_ix();
			if($audio_ix)
				$tracks_model->audio_ix = $audio_ix;
			else
				$tracks_model->audio_ix = 0;
		} else {
			$tracks_model->audio_ix = 0;
		}
	}
