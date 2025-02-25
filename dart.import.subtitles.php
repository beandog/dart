<?php

	/** Subtitles **/

	for($subtitle_track = 1; $subtitle_track < $dvd->title_track_subtitle_tracks + 1; $subtitle_track++) {

		$dvd->load_subtitle_track($title_track, $subtitle_track);

		// Lookup the database subp.id
		$subp_model = new Subp_Model;
		$subp_model_id = $subp_model->find_subp_id($tracks_model_id, $subtitle_track);

		// Create a new record
		if(!$subp_model_id) {

			$subp_model_id = $subp_model->create_new();

			if($debug)
				echo "* Created new subp id: $subp_model_id\n";

			$new_subtitle_tracks++;

			$subp_model->track_id = $tracks_model_id;
			$subp_model->ix = $subtitle_track;

		} else {

			$subp_model->load($subp_model_id);

		}

		if($subp_model->langcode != $dvd->subtitle_track_lang_code) {
			$subp_model->langcode = $dvd->subtitle_track_lang_code;
			if($debug)
				echo "* Updating subtitle lang code: ".$dvd->subtitle_track_lang_code."\n";
		}

		if(!$subp_model->streamid) {
			$subp_model->streamid = $dvd->subtitle_track_stream_id;
			if($debug)
				echo "* Updating subtitle stream id: ".$dvd->subtitle_track_stream_id."\n";
		}

		if(is_null($subp_model->active) || $subp_model->active != $dvd->subtitle_track_active) {
			if($debug)
				echo "* Updating subtitle track active: ".(is_null($subp_model->active) ? "unset" : "false")." -> ".($dvd->subtitle_track_active ? "true" : "false")."\n";
			$subp_model->active = $dvd->subtitle_track_active;
		}

	}

	if(is_null($tracks_model->closed_captioning) && $disc_type == 'dvd') {

		$handbrake = new HandBrake;
		$handbrake->set_binary($handbrake_bin);
		$handbrake->input_filename($device);
		$handbrake->input_track($title_track);

		$scan = $handbrake->scan();

		if(!$scan) {
			if($debug) {
				echo "* Handbrake scan failed\n";
			}
		} else {
			if($debug) {
				echo "* Closed captioning: ".($handbrake->closed_captioning? 'yes' : 'no')."\n";

			}
			$tracks_model->closed_captioning = intval($handbrake->closed_captioning);
		}

	}
