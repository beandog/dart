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

			$new_audio_tracks++;

			$audio_model->track_id = $tracks_model_id;
			$audio_model->ix = $audio_track;

		} else {

			$audio_model->load($audio_model_id);

		}

		if($audio_model->langcode != $dvd->audio_track_lang_code) {
			$audio_model->langcode = $dvd->audio_track_lang_code;
			if($this->debug)
				echo "* Updating audio lang code: ".$audio_model->langcode." -> ".$dvd->audio_track_lang_code."\n";
		}

		if($audio_model->format != $dvd->audio_track_codec) {
			$audio_model->format = $dvd->audio_track_codec;
			if($this->debug)
				echo "* Updating audio codec: ".$audio_model->format." -> ".$dvd->audio_track_codec."\n";
		}

		if($audio_model->channels != $dvd->audio_track_channels) {
			$audio_model->channels = $dvd->audio_track_channels;
			if($this->debug)
				echo "* Updating audio channels: ".$audio_model->channels." -> ".$dvd->audio_track_channels."\n";
		}

		if($audio_model->streamid != $dvd->audio_track_stream_id) {
			$audio_model->streamid = $dvd->audio_track_stream_id;
			if($this->debug)
				echo "* Updating audio channels: ".$audio_model->channels." -> ".$dvd->audio_track_channels."\n";
		}

	}
