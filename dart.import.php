<?

	// Import the DVD

	// Conditions where a disc is imported into the database
	// Access to the device is given AND one of:
	// a. The disc is indexed, but it is missing some metadata
	// b. The disc is not indexed and an import command is given
	if($access_device && (($disc_indexed && !$disc_archived) || ($import && !$disc_indexed))) {

		if($verbose)
			shell::msg("[Import]");
		if(!$disc_archived && $disc_indexed && $verbose)
			shell::msg("* Updating metadata");

		$uniq_id = $dvd->getID();

		echo "* Title: ".$dvd->getTitle()."\n";

		// Create a new database record for the DVD
		if(!$disc_indexed) {

			$dvds_model_id = $dvds_model->create_new();
			if($debug)
				shell::stdout("! Created new DVD id: $dvds_model_id");

			$dvds_model->uniq_id = $uniq_id;
		}

		// Update the disc as needed
		if(empty($dvds_model->title))
			$dvds_model->title = $dvd->getTitle();
		if($dvd->getVMGID() && !$dvds_model->vmg_id)
			$dvds_model->vmg_id = $dvd->getVMGID();
		if($dvd->getProviderID() && !$dvds_model->provider_id)
			$dvds_model->provider_id = $dvd->getProviderID();
		if(is_null($dvds_model->longest_track))
			$dvds_model->longest_track = $dvd->getLongestTrack();
		if(is_null($dvds_model->filesize))
			$dvds_model->filesize = $dvd->getSize();
		if(empty($dvds_model->serial_id))
			$dvds_model->serial_id = $dvd->getSerialID();

		// Flag it as indexed and archived
		$disc_indexed = true;

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
			if(!$tracks_model_id) {
				$tracks_model_id = $tracks_model->create_new();
				$tracks_model->dvd_id = $dvds_model_id;
				$tracks_model->ix = $track_number;
				if($debug)
					shell::stdout("! Created new track id: $tracks_model_id");
			}

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

			// Get lsdvd XML to pass to sub-classes
			$xml = $dvd_track->getXML();

			/** Palettes **/
			$track_palette_colors = $dvd_track->getPaletteColors();

			if(count($track_palette_colors)) {

				foreach($track_palette_colors as $color) {

					// Lookup the database tracks.id
					$palettes_model = new Palettes_Model;
					$palettes_model_id = $palettes_model->find_palettes_id($tracks_model_id, $color);
					if(!$palettes_model_id) {
						$palettes_model_id = $palettes_model->create_new();
						$palettes_model->track_id = $tracks_model_id;
						$palettes_model->color = $color;
						if($debug)
							shell::stdout("! Created new palettes id: $palettes_model_id");
					}

				}

				// Unset variables that are unused after this
				unset($track_palette_colors);
				unset($color);
				unset($palettes_model);
				unset($palettes_model_id);

			}

			/** Audio Streams **/

			$audio_streams = $dvd_track->getAudioStreams();

			foreach($audio_streams as $streamid) {

				$dvd_audio = new DVDAudio($xml, $streamid);

				$arr = array(
					'track_id' => $track->id,
					'ix' => $dvd_audio->getXMLIX(),
				);

				$audio = audio::first(array('conditions' => $arr));

				if(is_null($audio))
					$audio = audio::create($arr);

				$arr = array(
					'langcode' => $dvd_audio->getLangcode(),
					'language' => $dvd_audio->getLanguage(),
					'format' => $dvd_audio->getFormat(),
					'frequency' => $dvd_audio->getFrequency(),
					'quantization' => $dvd_audio->getQuantization(),
					'channels' => $dvd_audio->getChannels(),
					'ap_mode' => $dvd_audio->getAPMode(),
					'content' => $dvd_audio->getContent(),
					'streamid' => $streamid,
				);

				$audio->set_attributes($arr);
				$audio->save();

			}


			/** Subtitles **/

			$subtitle_streams = $dvd_track->getSubtitleStreams();

			foreach($subtitle_streams as $streamid) {

				$dvd_subp = new DVDSubs($xml, $streamid);

				$arr = array(
					'track_id' => $track->id,
					'ix' => $dvd_subp->getXMLIX(),
				);

				$subp = subp::first(array('conditions' => $arr));

				if(is_null($subp))
					$subp = subp::create($arr);

				$arr = array(
					'langcode' => $dvd_subp->getLangcode(),
					'language' => $dvd_subp->getLanguage(),
					'content' => $dvd_subp->getContent(),
					'streamid' => $streamid,
				);

				$subp->set_attributes($arr);
				$subp->save();

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
