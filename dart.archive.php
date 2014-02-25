<?php

	// Update DVD metadata in the database

	/**
	 * The purpose of this file is to keep a running
	 * set of code to update the database with the
	 * latest schema.  This way, any changes will always
	 * compile in here, and be stored centrally.
	 */

	if($access_device && $disc_indexed) {

		if($verbose) {
			shell::stdout("[Archival]");
			shell::stdout("* Checking for missing metadata");
		}

		// Use database checks to see if archiving needs to happen
		$missing_dvd_metadata = $dvds_model->missing_metadata();

		/** DVDS **/

		if($missing_dvd_metadata) {

			if($debug)
				shell::stdout("! DVD ID $dvds_model_id is missing metadata");

			// DVD longest track
			if(is_null($dvds_model->longest_track)) {
				if($verbose)
					shell::stdout("* Updating longest track");
				$dvds_model->longest_track = $dvd->getLongestTrack();
			}

			// DVD filesize
			if(is_null($dvds_model->filesize)) {
				if($verbose)
					shell::stdout("* Updating filesize");
				$dvds_model->filesize = $dvd->getSize();
			}

			// DVD serial ID
			// Not using 'empty' because it's a constructor, and
			// not a function.
			if(!($dvds_model->serial_id)) {
				if($verbose)
					shell::stdout("* Updating serial ID");
				$dvds_model->serial_id = $dvd->getSerialID();
			}

		}

		/** Tracks **/

		$tracks = $dvds_model->get_tracks();

		foreach($tracks as $tracks_model_id) {

			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);

			// Check for missing metadata
			$missing_track_metadata = $tracks_model->missing_metadata();

			if($missing_track_metadata) {
				$track_number = $tracks_model->ix;
				$dvd_track = new DVDTrack($track_number, $device);
			}

			// Only access the device if we need to
			if($missing_track_metadata) {

				if($debug)
					shell::stdout("! Track $track_number is missing metadata");

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
				$has_cc = $tracks_model->cc;
				if(is_null($has_cc)) {

					$handbrake = new Handbrake;
					$handbrake->input_filename($device);
					$handbrake->input_track($track_number);

					$has_cc = $handbrake->has_cc();

					if($has_cc)
						$tracks_model->cc = 't';
					else
						$tracks_model->cc = 'f';

				}

			}

		}

		// Mark disc as archived
		// This is a legacy variable, but may come in
		// useful sometime.
		$disc_archived = true;

		shell::stdout("* Disc archived");


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

			if($verbose) {
				shell::stdout("* DVD tracks ($num_dvd_tracks) and DB tracks ($num_db_tracks) do not match");
			}
		}

		$tracks = $dvds_model->get_tracks();
		$num_db_audio_streams = 0;

		foreach($tracks as $tracks_model_id) {

			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);

			// Check for missing metadata
			$missing_track_metadata = $tracks_model->missing_metadata();

			if($missing_track_metadata) {
				$track_number = $tracks_model->ix;
				$dvd_track = new DVDTrack($track_number, $device);
				shell::stdout("$track_number ", false);
			}

			// Check to see if there are tracks in the
			// database that don't have any audio streams
			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);
			$num_db_audio_streams += count($tracks_model->get_audio_streams());

		}

		if(!$num_db_audio_streams) {
			$missing_import_data = true;
			$missing_audio_streams = true;
		}

		if($verbose && $missing_audio_streams)
			shell::stdout("* No audio streams found in database for tracks");
		if($verbose && $missing_import_data)
			shell::stdout("* Forcing import");

	}

?>
