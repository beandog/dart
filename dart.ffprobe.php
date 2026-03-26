<?php

if($disc_indexed && $opt_ffprobe) {

	$dvd_encoder = $dvds_model->get_encoder();

	$dvd_episodes = $dvds_model->get_episodes();

	// On QA run, only encode the first one
	if($opt_qa || $opt_one) {
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			if($episodes_model->skip)
				continue;
			$dvd_episodes = array($episode_id);
			break;
		}
	}

	$input_filename = realpath($device);

	foreach($dvd_episodes as $episode_id) {

		$episodes_model = new Episodes_Model($episode_id);
		if($episodes_model->skip)
			continue;

		$ffmpeg = new FFMpeg();

		if($debug)
			$ffmpeg->debug();

		if($verbose)
			$ffmpeg->verbose();

		$series_model = new Series_Model($episodes_model->get_series_id());

		$tracks_model = new Tracks_Model($episodes_model->track_id);

		if($disc_type == 'dvd' && $dvd_encoder == 'ffpipe') {

			$ffmpeg->set_encoder('ffpipe');

			$ffmpeg->input_filename('-');

			$dvd_copy = new DVDCopy();

			$dvd_copy->input_filename($input_filename);
			$dvd_copy->output_filename('-');
			$dvd_copy->input_track($tracks_model->ix);

			$dvd_copy_command = $dvd_copy->get_executable_string();

			$dvd_copy_command .= ' 2> /dev/null';

			// $ffmpeg_command = $ffmpeg->get_executable_string();
			$ffmpeg_command = $ffprope();

			$ffmpeg_command = "$dvd_copy_command | $ffmpeg_command";

		}

		if($disc_type == 'dvd' && $dvd_encoder != 'ffpipe') {

			$ffmpeg->set_encoder('ffprobe');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			// $ffmpeg_command = $ffmpeg->get_executable_string();
			$ffmpeg_command = $ffmpeg->ffprobe();

		}

		if($disc_type == 'bluray' && $opt_ffprobe) {

			$dvd_bugs = $dvds_model->get_bugs();

			$ffmpeg->set_disc_type('bluray');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			$starting_chapter = $episodes_model->starting_chapter;
			if($starting_chapter)
				$ffmpeg->set_chapters($starting_chapter, null);

			$ffmpeg->set_encoder('ffprobe');
			// $ffmpeg_command = $ffmpeg->get_executable_string();
			$ffmpeg_command = $ffmpeg->ffprobe();

		}

		if($opt_encode_info) {
			echo "$ffmpeg_command\n";
			continue;
		}

		// If you ever feel like showing it in JSON
		$opt_json = false;
		if($opt_json) {
			$ffmpeg_command .= ' -show_format -of json -show_streams 2> /dev/null';
			passthru($ffmpeg_command);
			continue;
		}

		echo "[Probing]\n";
		$arg_device = escapeshellarg($device);
		echo "* Source: $arg_device\n";
		echo "* Track: ".$tracks_model->ix."\n";

		echo "[ffmpeg]\n";
		passthru($ffmpeg_command);

	}

}
