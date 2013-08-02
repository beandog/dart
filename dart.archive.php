<?php

	// Update DVD metadata in the database

	/**
	 * The purpose of this file is to keep a running
	 * set of code to update the database with the
	 * latest schema.  This way, any changes will always
	 * compile in here, and be stored centrally.
	 */

	if($access_device && $disc_indexed) {

		if($verbose)
			shell::stdout("[Metadata]");

		// Use database checks to see if archiving needs to happen
		$missing_dvd_metadata = $dvds_model->missing_metadata();

		/** DVDS **/

		if($missing_dvd_metadata) {

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

	}
?>
