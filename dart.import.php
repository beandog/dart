<?php

	/**
	 * --import
	 *
	 * Import a new DVD into the database
	 */

	$missing_dvd_metadata = $dvds_model->missing_metadata();

	if($archive && !$missing_dvd_metadata) {
		echo "* Archive:\tNo legacy metadata! :D\n";
	}

	// Some conditions apply where importing may be skipped.

	// Set this to say if we *can* import it, if requested
	$allow_import = false;

	if($import || !$disc_indexed || $missing_dvd_metadata)
		$allow_import = true;

	// If only creating the ISO is requested, then skip import.  This is
	// common when there are problems accessing the DVD, and import is
	// expected to fail.
	if($dump_iso && (!$import && !$archive))
		$allow_import = false;

	// Start import
	if($access_device && $allow_import) {

		$dvdread_id = $dvd->dvdread_id;
		$dvd_title = $dvd->title;

		echo "* Title: $dvd_title\n";

		// Create a new database record for the DVD
		if(!$disc_indexed) {

			echo "[Import]\n";

			$dvds_model_id = $dvds_model->create_new();

			if($debug)
				echo "! Created new DVD id: $dvds_model_id\n";

		} elseif($disc_indexed && $missing_dvd_metadata) {

			echo "[Metadata]\n";
			echo "* Updating legacy metadata\n";
		}

		if(!$dvds_model->dvdread_id) {
			echo "* dvdread id = $dvdread_id\n";
			$dvds_model->dvdread_id = $dvdread_id;
		}
		if(!$dvds_model->title) {
			echo "* title: $dvd_title\n";
			$dvds_model->title = $dvd_title;
		}
		if($missing_dvd_metadata || !$disc_indexed) {
			$dvd_filesize = $dvd->size();
			$d_dvd_filesize = number_format($dvd_filesize);
			echo "* DVD filesize: $d_dvd_filesize MB\n";
			$dvds_model->filesize = $dvd_filesize;
		}

		// Flag it as indexed
		$disc_indexed = true;

		/** Tracks **/

		$dvd_num_tracks = $dvd->getNumTracks();

		// If it comes to this point, there's probably an issue reading the DVD
		// directly.  Either way, the import will still work, so it's debatable
		// whether this should die here now and kill the progress of the script
		// or not. This is something where the dvd_debug program could come into play.
		// Ideally, that would run first and flag anomalies for me directly.
		if(!$dvd_num_tracks) {

			$broken_dvd = true;
			echo "? No tracks? No good!!!!\n";

			echo "! Flagging DVD as broken in database\n";
			$dvds_model->tag_dvd('dvd_no_tracks');

			// BEEP!
			beep_error();

			goto broken_dvd;

		}

		if($missing_dvd_metadata && !$import)
			echo "* Updating DVD track metadata: ";
		elseif($archive)
			echo "* Checking tracks for full archival: ";
		elseif($import)
			echo "* Importing $dvd_num_tracks tracks: ";

		next_track:

		for($track_number = 1; $track_number <= $dvd_num_tracks; $track_number++) {

			echo "$track_number ";

			$dvd_track = new DVDTrack($device, $track_number);

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

			} else {

				$tracks_model->load($tracks_model_id);

			}

			// Handle broken tracks! :D
			if(!$dvd_track->opened) {
				echo "\n";
				echo "! Opening $device track number $track_number FAILED\n";
				$track_number++;

				// Tag the track as broken in the database
				$tracks_model->tag_track('track_open_fail');

				// BOOP!
				beep_error();

				goto next_track;
			}

			// Check the database to see if any tags / anomalies are reported
			$arr_tags = $tracks_model->get_tags();

			// Track length has been through a lot of revisions, always update
			// it if the missing DVD metadata flag is set.
			if($missing_dvd_metadata || $import || is_null($tracks_model->length))
				$tracks_model->length = $dvd_track->getLength();

			if(!$tracks_model->format)
				$tracks_model->format = $dvd_track->getVideoFormat();

			if(!$tracks_model->aspect)
				$tracks_model->aspect = $dvd_track->getAspectRatio();

			// Handbrake (0.9.9) sometimes fails to scan DVDs with certain tracks.
			// If that's the case, skip over them.
			if(in_array('track_no_handbrake_scan', $arr_tags)) {
				// Default to false, so we don't depend on it.
				$tracks_model->closed_captioning = 'f';
			} else {
				if(is_null($tracks_model->closed_captioning)) {

					$handbrake = new Handbrake;
					$handbrake->input_filename($device);
					$handbrake->input_track($track_number);

					if($handbrake->has_closed_captioning())
						$tracks_model->closed_captioning = 't';
					else
						$tracks_model->closed_captioning = 'f';

				}
			}

			// Get lsdvd XML to pass to sub-classes
			$xml = $dvd_track->getXML();

			/** Audio Streams **/

			$audio_streams = $dvd_track->getAudioStreams();

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

				} else {

					$audio_model->load($audio_model_id);

				}

				if(is_null($audio_model->ix))
					$audio_model->ix = $audio_ix;

				if(!$audio_model->langcode)
					$audio_model->langcode = $dvd_audio->getLangcode();

				if(!$audio_model->format)
					$audio_model->format = $dvd_audio->getFormat();

				if(is_null($audio_model->channels))
					$audio_model->channels = $dvd_audio->getChannels();

				if(!$audio_model->streamid)
					$audio_model->streamid = $streamid;

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
						echo "! Created new subp id: $subp_model_id\n";

					$subp_model->track_id = $tracks_model_id;
					$subp_model->ix = $subp_ix;

				} else {

					$subp_model->load($subp_model_id);

				}

				if(!$subp_model->langcode)
					$subp_model->langcode = $dvd_subp->getLangcode();

				if(!$subp_model->language)
					$subp_model->language = $dvd_subp->getLanguage();

				if(!$subp_model->streamid)
					$subp_model->streamid = $streamid;

			}

			unset($dvd_subp);
			unset($subp_model);
			unset($subp_ix);
			unset($subp_model_id);
			unset($streamid);
			unset($subtitle_streams);

			/** Chapters **/

			$dvd_chapters = $dvd_track->getChapters();

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

				} else {

					$chapters_model->load($chapters_model_id);

				}

				if(is_null($chapters_model->length))
					$chapters_model->length = $chapter_data['length'];

				if(is_null($chapters_model->startcell))
					$chapters_model->startcell = $chapter_data['startcell'];

			}

			unset($chapters_model);
			unset($chapters_ix);
			unset($chapters_model_id);
			unset($dvd_chapters);

			/** Cells **/

			$dvd_cells = $dvd_track->getCells();

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

				} else {

					$cells_model->load($cells_model_id);

				}

				if(is_null($cells_model->length))
					$cells_model->length = $cells_length;

			}

		}

		// Close off the newline that the track count was displaying
		echo "\n";

		if($import) {
			echo "* New DVD imported! Yay! :D\n";
		}

	}

	// FIXME needs a function
	if($missing_dvd_metadata) {
		$missing_dvd_metadata = false;
		$dvds_model->metadata_spec = 1;
	}

	// Moving right along ...
	broken_dvd:
