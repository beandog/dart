<?php

	// Run a HandBrake scan on each track and import into the database

	if($opt_scan && $arg_scan == 'tracks') {

		$dvd_title_tracks = $dvd->title_tracks;
		$tracks_model = new Tracks_Model;

		if($dvds_model->dvd_scanned_tracks())
			goto tracks_scanned;

		echo "* HandBrake:\t";

		for($title_track = 1; $title_track < $dvd_title_tracks + 1; $title_track++) {


			echo "$title_track ";

			// Lookup the database tracks.id
			$title_track_loaded = $dvd->load_title_track($title_track);
			$tracks_model_id = $tracks_model->find_track_id($dvds_model_id, $title_track);
			$tracks_model->load($tracks_model_id);

			if($tracks_model->has_handbrake_scan()) {
				if($debug)
					echo "[skip] ";
				continue;
			}

			if($debug)
				echo "[scan] ";
			
			$output = array();
			exec("HandBrakeCLI --version 2> /dev/null", $output);
			$hb_scan_version = current($output);

			$output = array();
			exec("HandBrakeCLI -i $device --scan -t $title_track 2>&1", $output, $retval); 
			$hb_output = trim(implode("\n", ($output)));
			$hb_output = utf8_encode($hb_output);
			$tracks_model->set_handbrake_scan($hb_scan_version, $hb_output);

		}

		echo "\n";

	}

	tracks_scanned:
