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

		/** Tracks **/

		$tracks = $dvds_model->get_tracks();
		foreach($tracks as $tracks_model_id) {

			$tracks_model = new Tracks_Model;
			$tracks_model->load($tracks_model_id);

			// Check for missing metadata
			$missing_track_metadata = $tracks_model->missing_metadata();
			$missing_track_palettes = $tracks_model->missing_palettes();

			if($missing_track_metadata || $missing_track_palettes)
				$dvd_track = new DVDTrack($track_number, $device);

			// Only access the device if we need to
			if($missing_track_metadata) {

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

			if($missing_track_palettes) {

				// FIXME duplicate code of dart.import.php :(

				/** Palettes **/
				$palette_colors = $dvd_track->getPaletteColors();

				if(count($palette_colors)) {

					if($debug)
						shell::stdout("! Track $track_number has ".count($palette_colors)." palette colors");

					$palette_ix = 1;

					foreach($palette_colors as $color) {

						// Lookup the database palettes.id
						$palettes_model = new Palettes_Model;
						$palettes_model_id = $palettes_model->find_palettes_id($tracks_model_id, $palette_ix, $color);

						// Create new database record
						if(!$palettes_model_id) {

							$palettes_model_id = $palettes_model->create_new();

							if($debug)
								shell::stdout("! Created new palettes id: $palettes_model_id");

							$palettes_model->track_id = $tracks_model_id;
							$palettes_model->ix = $palette_ix;
							$palettes_model->color = $color;

						}

						$palette_ix++;

					}

					unset($palette_colors);
					unset($color);
					unset($palettes_model);
					unset($palettes_model_id);
					unset($palette_ix);

				}

			}

		}

	}
?>
