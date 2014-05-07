<?php

	/**
	 * --import
	 *
	 * Import a new DVD into the database
	 */


	// Start import
	if($access_device && ($import || !$disc_indexed || $missing_import_data)) {

		if($verbose)
			echo "[Import]\n";

		$uniq_id = $dvd->getID();

		echo "* Title: ".$dvd->getTitle()."\n";

		// Create a new database record for the DVD
		if(!$disc_indexed) {

			$dvds_model_id = $dvds_model->create_new();

			if($debug)
				echo "! Created new DVD id: $dvds_model_id\n";

			$dvds_model->uniq_id = $uniq_id;
			$dvds_model->title = $dvd->getTitle();
			$dvds_model->provider_id = $dvd->getProviderID();
			$dvds_model->longest_track = $dvd->getLongestTrack();
			$dvds_model->filesize = $dvd->getSize();
			$dvds_model->serial_id = $dvd->getSerialID();

			// Flag it as indexed
			$disc_indexed = true;

		}

		/** Tracks **/

		$num_tracks = $dvd->getNumTracks();

		if(!count($num_tracks))
			die("? No tracks? No good. Exiting\n");

		if($verbose)
			echo "* Importing $num_tracks tracks: ";

		for($track_number = 1; $track_number <= $num_tracks; $track_number++) {

			if($verbose)
				echo "$track_number ";

			$dvd_track = new DVDTrack($track_number, $device);

			// Lookup the database tracks.id
			$tracks_model = new Tracks_Model;
			$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $track_number);

			// Create new database entry
			if(!$tracks_model_id) {

				$tracks_model_id = $tracks_model->create_new();

				if($debug)
					echo "! Created new track id: $tracks_model_id\n";

				$tracks_model->dvd_id = $dvds_model_id;
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

			}

			// Get lsdvd XML to pass to sub-classes
			$xml = $dvd_track->getXML();

			/** Audio Streams **/

			$audio_streams = $dvd_track->getAudioStreams();

			if(count($audio_streams)) {

				if($debug)
					echo "! Track $track_number has ".count($audio_streams)." audio streams\n";

				foreach($audio_streams as $streamid) {

					$dvd_audio = new DVDAudio($xml, $streamid);

					// Lookup the database audio.id
					$audio_model = new Audio_Model;
					$audio_ix = $dvd_audio->getXMLIX();
					$audio_model_id = $audio_model->find_audio_id($tracks_model_id, $audio_ix);

					// Create a new record
					if(!$audio_model_id) {

						$audio_model_id = $audio_model->create_new();

						if($debug)
							echo "! Created new audio id: $audio_model_id\n";

						$audio_model->track_id = $tracks_model_id;
						$audio_model->ix = $audio_ix;
						$audio_model->langcode = $dvd_audio->getLangcode();
						$audio_model->language = $dvd_audio->getLanguage();
						$audio_model->format = $dvd_audio->getFormat();
						$audio_model->frequency = $dvd_audio->getFrequency();
						$audio_model->quantization = $dvd_audio->getQuantization();
						$audio_model->channels = $dvd_audio->getChannels();
						$audio_model->ap_mode = $dvd_audio->getAPMode();
						$audio_model->content = $dvd_audio->getContent();
						$audio_model->streamid = $streamid;

					}

				}

				unset($dvd_audio);
				unset($audio_model);
				unset($audio_ix);
				unset($audio_model_id);
				unset($streamid);
				unset($audio_streams);

			}

			/** Subtitles **/

			$subtitle_streams = $dvd_track->getSubtitleStreams();

			if(count($subtitle_streams)) {

				if($debug)
					echo "! Track $track_number has ".count($subtitle_streams)." subtitle streams\n";

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
						$subp_model->langcode = $dvd_subp->getLangcode();
						$subp_model->language = $dvd_subp->getLanguage();
						$subp_model->content = $dvd_subp->getContent();
						$subp_model->streamid = $streamid;

					}

				}

				unset($dvd_subp);
				unset($subp_model);
				unset($subp_ix);
				unset($subp_model_id);
				unset($streamid);
				unset($subtitle_streams);

			}

			/** Chapters **/

			$dvd_chapters = $dvd_track->getChapters();

			if(count($dvd_chapters)) {

				if($debug)
					echo "! Track $track_number has ".count($dvd_chapters)." chapters\n";

				foreach($dvd_chapters as $chapter_number => $chapter_data) {

					// Lookup the database chapters.id
					$chapters_model = new Chapters_Model;
					$chapters_ix = $chapter_data['ix'];
					$chapters_model_id = $chapters_model->find_chapters_id($tracks_model_id, $chapters_ix);

					// Create a new record
					if(!$chapters_model_id) {

						$chapters_model_id = $chapters_model->create_new();

						if($debug)
							echo "! Created new chapters id: $chapters_model_id\n";

						$chapters_model->track_id = $tracks_model_id;
						$chapters_model->ix = $chapters_ix;
						$chapters_model->length = $chapter_data['length'];
						$chapters_model->startcell = $chapter_data['startcell'];

					}

				}

				unset($chapters_model);
				unset($chapters_ix);
				unset($chapters_model_id);
				unset($dvd_chapters);

			}

			/** Cells **/

			$dvd_cells = $dvd_track->getCells();

			if(count($dvd_cells)) {

				if($debug)
					echo "! Track $track_number has ".count($dvd_cells)." cells\n";

				foreach($dvd_cells as $cells_ix => $cells_length) {

					// Lookup the database chapters.id
					$cells_model = new Cells_Model;
					$cells_model_id = $cells_model->find_cells_id($tracks_model_id, $cells_ix);

					// Create a new record
					if(!$cells_model_id) {

						$cells_model_id = $cells_model->create_new();

						if($debug)
							echo "! Created new cells id: $cells_model_id\n";

						$cells_model->track_id = $tracks_model_id;
						$cells_model->ix = $cells_ix;
						$cells_model->length = $cells_length;

					}

				}

			}

		}

		// Close off the newline that the track count was displaying
		if($verbose)
			echo "\n";

		if($verbose) {
			echo "* New DVD imported! Yay! :D\n";
		}

	}


	// Eject the disc if we are polling, and nothing else
	if(($import || $archive) && $wait && !$rip) {
		$drive->open();
	}
