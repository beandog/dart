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
				$tracks_model->dvd_id = $dvds_model_id;
				$tracks_model->ix = $track_number;

				if($debug)
					shell::stdout("! Created new track id: $tracks_model_id");

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

				$palette_ix = 1;

				foreach($palette_colors as $color) {

					// Lookup the database palettes.id
					$palettes_model = new Palettes_Model;
					$palettes_model_id = $palettes_model->find_palettes_id($tracks_model_id, $palette_ix, $color);
					if(!$palettes_model_id) {
						$palettes_model_id = $palettes_model->create_new();
						$palettes_model->track_id = $tracks_model_id;
						$palettes_model->ix = $palette_ix;
						$palettes_model->color = $color;
						if($debug)
							shell::stdout("! Created new palettes id: $palettes_model_id");
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

				unset($dvd_audio);
				unset($audio_model);
				unset($audio_ix);
				unset($audio_model_id);
				unset($streamid);

			}

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
						shell::stdout("! Created new subp id: $subp_model_id");

					$subp_model->langcode = $dvd_subp->getLangcode();
					$subp_model->language = $dvd_subp->getLanguage();
					$subp_model->content = $dvd_subp->getContent();
					$subp_model->streamid = $streamid;

				}

				unset($dvd_subp);
				unset($subp_model);
				unset($subp_ix);
				unset($subp_model_id);
				unset($streamid):

			}

			/** Chapters **/

			$dvd_chapters = $dvd_track->getChapters();

			foreach($dvd_chapters as $chapter_number => $chapter_data) {

				$arr = array(
					'track_id' => $track->id,
					'ix' => $chapter_data['ix'],
				);

				$chapters = chapters::first(array('conditions' => $arr));

 				if(is_null($chapters))
 					$chapters = chapters::create($arr);

 				$chapters->set_attributes($chapter_data);
 				$chapters->save();

			}

			/** Cells **/

			$dvd_cells = $dvd_track->getCells();

			foreach($dvd_cells as $cell_ix => $cell_length) {

				$arr = array(
					'ix' => $cell_ix,
					'length' => $cell_length,
					'track_id' => $track->id,
				);

				$cells = cells::first(array('conditions' => $arr));

 				if(is_null($cells))
 					cells::create($arr);

			}

		}

		// Now flag it as archived. :D
		$disc_archived = true;

		// Load up data used later
		$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);
		$dvds_model->load($dvds_model_id);

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
