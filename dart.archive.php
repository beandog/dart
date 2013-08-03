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
		$num_tracks = count($tracks);

		if($verbose)
			shell::stdout("* Querying track records: ", false);

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

			// Only access the device if we need to
			if($missing_track_metadata) {

				if($debug)
					shell::stdout("! Track $track_number is missing metadata");

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

			}

		}

		// Close out dangling output
		shell::stdout("done");

		// Mark disc as archived
		// This is a legacy variable, but may come in
		// useful sometime.
		$disc_archived = true;

		shell::stdout("* Disc archived");

	}
?>
