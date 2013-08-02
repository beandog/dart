<?

	// Import the DVD

	// Conditions where a disc is imported into the database
	// Access to the device is given AND one of:
	// a. The disc is indexed, but it is missing some metadata
	// b. The disc is not indexed and an import command is given
	if($access_device && (($disc_indexed && !$disc_archived) || ($import && !$disc_indexed))) {

		if($verbose)
			shell::msg("[Import]");

		$uniq_id = $dvd->getID();

		echo "* Title: ".$dvd->getTitle()."\n";

		// Create a new database record for the DVD
		if(!$disc_indexed) {

			$dvds_model_id = $dvds_model->create_new();

			if($debug)
				shell::stdout("! Created new DVD id: $dvds_model_id");

			$dvds_model->uniq_id = $uniq_id;
			$dvds_model->title = $dvd->getTitle();
			$dvds_model->vmg_id = $dvd->getVMGID();
			$dvds_model->provider_id = $dvd->getProviderID();
			$dvds_model->longest_track = $dvd->getLongestTrack();
			$dvds_model->filesize = $dvd->getSize();
			$dvds_model->serial_id = $dvd->getSerialID();

			// Flag it as indexed
			$disc_indexed = true;

		}

		// Check for missing metadata
		if(!$disc_archived && $disc_indexed && $verbose)
			shell::msg("* Updating metadata");
		if(is_null($dvds_model->longest_track)) {
			if($verbose)
				shell::stdout("* Updating longest track");
			$dvds_model->longest_track = $dvd->getLongestTrack();
		}
		if(is_null($dvds_model->filesize)) {
			if($verbose)
				shell::stdout("* Updating blocksize");
			$dvds_model->filesize = $dvd->getSize();
		}
		if(empty($dvds_model->serial_id)) {
			if($verbose)
				shell::stdout("* Updating serial ID");
			$dvds_model->serial_id = $dvd->getSerialID();
		}

		/** Tracks **/

		$num_tracks = $dvd->getNumTracks();

		if(!count($num_tracks))
			die("? No tracks? No good. Exiting\n");

		if($verbose)
			shell::stdout("* Importing $num_tracks tracks: ", false);

		for($track_number = 1; $track_number <= $num_tracks; $track_number++) {

			if($verbose)
				shell::stdout("$track_number ", false);

			$dvd_track = new DVDTrack($track_number, $device);

			// Lookup the database tracks.id
			$tracks_model = new Tracks_Model;
			$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $track_number);

			// Create new database entry
			if(!$tracks_model_id) {

				$tracks_model_id = $tracks_model->create_new();

				if($debug)
					shell::stdout("! Created new track id: $tracks_model_id");

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

			/** Audio Streams **/

			$audio_streams = $dvd_track->getAudioStreams();

			if(!count($audio_streams))
				shell::stdout("? No audio streams on track $track_number");

			if(count($audio_streams)) {

				if($debug)
					shell::stdout("! Track $track_number has ".count($audio_streams)." audio streams");

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
							shell::stdout("! Created new audio id: $audio_model_id");

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
					shell::stdout("! Track $track_number has ".count($subtitle_streams)." subtitle streams");

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
							shell::stdout("! Created new subp id: $subp_model_id");

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
					shell::stdout("! Track $track_number has ".count($dvd_chapters)." chapters");

				foreach($dvd_chapters as $chapter_number => $chapter_data) {

					// Lookup the database chapters.id
					$chapters_model = new Chapters_Model;
					$chapters_ix = $chapter_data['ix'];
					$chapters_model_id = $chapters_model->find_chapters_id($tracks_model_id, $chapters_ix);

					// Create a new record
					if(!$chapters_model_id) {

						$chapters_model_id = $chapters_model->create_new();

						if($debug)
							shell::stdout("! Created new chapters id: $chapters_model_id");

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
					shell::stdout("! Track $track_number has ".count($dvd_cells)." cells");

				foreach($dvd_cells as $cells_ix => $cells_length) {

					// Lookup the database chapters.id
					$cells_model = new Cells_Model;
					$cells_model_id = $cells_model->find_cells_id($tracks_model_id, $cells_ix);

					// Create a new record
					if(!$cells_model_id) {

						$cells_model_id = $cells_model->create_new();

						if($debug)
							shell::stdout("! Created new cells id: $cells_model_id");

						$cells_model->track_id = $tracks_model_id;
						$cells_model->ix = $cells_ix;
						$cells_model->length = $cells_length;

					}

				}

			}

		}

		// Now flag it as archived. :D
		$disc_archived = true;

		// Close off the newline that the track count was displaying
		if($verbose)
			shell::stdout('', true);

		if($verbose) {
			shell::stdout("* New DVD imported! Yay! :D");
		}

	}


	// Eject the disc if we are polling, and nothing else
	if($import && $wait && !$rip) {
		$drive->open();
	}
