<?php

	/** Subtitles **/

	$subtitle_streams = $dvd_track->getSubtitleStreams();

	foreach($subtitle_streams as $streamid) {

		$dvd_subp = new DVDSubs($xml, $streamid);

		// Lookup the database subp.id
		$subp_model = new Subp_Model;
		$subp_ix = $dvd_subp->getXMLIX();
		$subp_model_id = $subp_model->find_subp_id($tracks_model_id, $subp_ix);

		// Create a new record
		if(!$subp_model_id) {

			$subp_model_id = $subp_model->create_new();

			if($debug)
				echo "! Created new subp id: $subp_model_id\n";

			$subp_model->track_id = $tracks_model_id;
			$subp_model->ix = $subp_ix;

		} else {

			$subp_model->load($subp_model_id);

		}

		if(!$subp_model->langcode)
			$subp_model->langcode = $dvd_subp->getLangcode();

		if(!$subp_model->language)
			$subp_model->language = $dvd_subp->getLanguage();

		if(!$subp_model->streamid)
			$subp_model->streamid = $streamid;

	}
