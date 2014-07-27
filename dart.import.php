<?php

	/**
	 * --import
	 *
	 * Import a new DVD into the database
	 */


	// Start import
	if($access_device && ($import || !$disc_indexed || $missing_dvd_data)) {


		$uniq_id = $dvd->getID();

		echo "* Title: ".$dvd->getTitle()."\n";

		// Create a new database record for the DVD
		if(!$disc_indexed) {

			if($verbose)
				echo "[Import]\n";

			$dvds_model_id = $dvds_model->create_new();

			if($debug)
				echo "! Created new DVD id: $dvds_model_id\n";

			$dvds_model->provider_id = $dvd->getProviderID();

		} elseif($disc_indexed && $missing_dvd_metadata) {

			if($verbose)
				echo "[Metadata]\n";
				echo "* Updating legacy metadata\n";
		}

		if(!$dvds_model->uniq_id) {
			echo "* dvdread id = $uniq_id\n";
			$dvds_model->uniq_id = $uniq_id;
		}
		if(!$dvds_model->title) {
			$dvd_title = $dvd-getTitle();
			echo "* title: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}
		if(is_null($dvds_model->longest_track)) {
			$dvd_longest_track = $dvd->getLongestTrack();
			echo "* longest track: $dvd_longest_track\n";
			$dvds_model->longest_track = $dvd_longest_track;
		}
		if($missing_dvd_metadata || !$disc_indexed) {
			$dvd_filesize = $dvd->getSize();
			echo "* DVD filesize: $dvd_filesize\n";
			$dvds_model->filesize = $dvd_filesize;
		}
		if(!$dvds->model->serial_id) {
			$dvd_serial_id = $dvd->getSerialID();
			echo "* serial id: $dvd_serial_id\n";
			$dvds_model->serial_id = $dvd->getSerialID();
		}

		// Flag it as indexed
		$disc_indexed = true;

		/** Tracks **/

		$dvd_num_tracks = $dvd->getNumTracks();

		// FIXME jump to the next DVD
		if(!count($dvd_num_tracks))
			die("? No tracks? No good. Exiting\n");

		if($verbose)
			echo "* Importing $dvd_num_tracks tracks: ";

		for($track_number = 1; $track_number <= $dvd_num_tracks; $track_number++) {

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

			}


			// Track length has been through a lot of revisions, always update
			// it if the missing DVD metadata flag is set.
			if($missing_dvd_metadata || $import || is_null($tracks_model->length))
				$tracks_model->length = $dvd_track->getLength();

			if(!$tracks_model->vts_id)
				$tracks_model->vts_id = $dvd_track->getVTSID();

			if(is_null($tracks_model->vts))
				$tracks_model->vts = $dvd_track->getVTS();

			if(is_null($tracks_model->ttn))
				$tracks_model->ttn = $dvd_track->getTTN();

			if(is_null($tracks_model->fps))
				$tracks_model->fps = $dvd_track->getFPS();

			if(!$tracks_model->format)
				$tracks_model->format = $dvd_track->getVideoFormat();

			if(!$tracks_model->aspect)
				$tracks_model->aspect = $dvd_track->getAspectRatio();

			if(is_null($tracks_model->width))
				$tracks_model->width = $dvd_track->getWidth();

			if(is_null($tracks_model->height)
				$tracks_model->height = $dvd_track->getHeight();

			if(!$tracks_model->df)
				$tracks_model->df = $dvd_track->getDF();

			if(is_null($tracks_model->angles))
				$tracks_model->angles = $dvd_track->getAngles();

			if(is_null($tracks_model->cc)) {
				$handbrake = new Handbrake;
				$handbrake->input_filename($device);
				$handbrake->input_track($track_number);

				if($handbrake->has_cc())
					$tracks_model->cc = 't';
				else
					$tracks_model->cc = 'f';

			}

			// Get lsdvd XML to pass to sub-classes
			$xml = $dvd_track->getXML();

			/** Audio Streams **/

			$audio_streams = $dvd_track->getAudioStreams();

			// FIXME do something if there are no audio streams
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
				}

				if(is_null($audio_model->ix))
					$audio_model->ix = $audio_ix;

				if(!$audio_model->langcode)
					$audio_model->langcode = $dvd_audio->getLangcode();

				if(!$audio_model->language)
					$audio_model->language = $dvd_audio->getLanguage();

				if(!$audio_model->format)
					$audio_model->format = $dvd_audio->getFormat();

				if(is_null($audio_model->frequency))
					$audio_model->frequency = $dvd_audio->getFrequency();

				if(!$audio_model->quantization)
					$audio_model->quantization = $dvd_audio->getQuantization();

				if(is_null($audio_model->channels))
					$audio_model->channels = $dvd_audio->getChannels();

				if(is_null($audio_model->ap_mode))
					$audio_model->ap_mode = $dvd_audio->getAPMode();

				if(!$audio_model->streamid)
					$audio_model->streamid = $streamid;

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
