<?php

	// Update DVD metadata in the database

	/**
	 * The purpose of this file is to keep a running
	 * set of code to update the database with the
	 * latest schema.  This way, any changes will always
	 * compile in here, and be stored centrally.
	 */

	if($access_device && $disc_indexed && !$fast) {

		$missing_dvd_metadata = $dvds_model->missing_metadata();

		/** Tracks **/

		$tracks = $dvds_model->get_tracks();

		// Default to empty, will be counted later
		$num_db_audio_streams = 0;

		foreach($tracks as $tracks_model_id) {

			// Check to see if there are tracks in the database that don't have any audio streams.
			// Fixes legacy import
			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);
			$num_db_audio_streams += count($tracks_model->get_audio_streams());

			if(!$num_db_audio_streams) {
				$missing_import_data = true;
				$missing_audio_streams = true;
			}

			// Check for missing metadata
			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);

			// ** Notice ** //
			// The query that runs the check for the missing metadata is customized to
			// be a cut-off for when the last time the schema was checked and properly
			// input.  If this flag is triggered, update *all* the metadata that is in
			// there, regardless of whether it is in the database or not.
			// In other words, no touchy the code!


			$track_number = $tracks_model->ix;
			$dvd_track = new DVDTrack($track_number, $device);

			echo "* Updating legacy metadata for track $track_number\n";

			$tracks_model->ix = $track_number;
			$tracks_model->length = $dvd_track->getLength();
			$tracks_model->vts_id = $dvd_track->getVTSID();
			$tracks_model->vts = $dvd_track->getVTS();
			$tracks_model->ttn = $dvd_track->getTTN();
			$tracks_model->fps = $dvd_track->getFPS();
			$tracks_model->format = $dvd_track->getVideoFormat();
			$tracks_model->aspect = $dvd_track->getAspectRatio();
			$tracks_model->width = $dvd_track->getWidth();
			$tracks_model->height = $dvd_track->getHeight();
			$tracks_model->df = $dvd_track->getDF();
			$tracks_model->angles = $dvd_track->getAngles();

			// Check for closed captioning
			if(is_null($tracks_model->cc)) {

				echo "* Updating closed captioning\n";

				$handbrake = new Handbrake;
				$handbrake->input_filename($device);
				$handbrake->input_track($track_number);

				if($handbrake->has_cc())
					$tracks_model->cc = 't';
				else
					$tracks_model->cc = 'f';

			}


		}

		// Mark disc as archived
		// This is a legacy variable, but may come in
		// useful sometime.
		$disc_archived = true;
		echo "* Disc archived\n";

		/** Sanity Checks **/

		// Run a sanity check to see if we are missing some
		// database content that can only be input by the
		// import sequence.

		/** DVDs **/

		// There are some cases where early imports
		// didn't include all the tracks.  Make sure
		// the amount matches up.
		$num_dvd_tracks = $dvd->getNumTracks();
		$tracks = $dvds_model->get_tracks();
		$num_db_tracks = count($tracks);

		if($num_dvd_tracks != $num_db_tracks) {

			$missing_import_data = true;

			echo "* DVD tracks ($num_dvd_tracks) and DB tracks ($num_db_tracks) do not match\n";
		}

		if($missing_audio_streams)
			echo "* No audio streams found in database for tracks\n";
		if($missing_import_data)
			echo "* Forcing import\n";

	}

?>
