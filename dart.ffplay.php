<?php

if($disc_indexed && ($opt_ffplay || $opt_ffprobe)) {

	if(isset($config_qa_max))
		$qa_max = $config_qa_max;
	else
		$qa_max = 90;

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

		$ffmpeg = new FFMpeg();

		if($debug)
			$ffmpeg->debug();

		if($verbose)
			$ffmpeg->verbose();

		$episodes_model = new Episodes_Model($episode_id);
		if($episodes_model->skip)
			continue;

		$series_model = new Series_Model($episodes_model->get_series_id());

		$tracks_model = new Tracks_Model($episodes_model->track_id);

		if($opt_ffplay) {

			$video_deint = $series_model->bwdif;
			$dvd_deint = $dvds_model->get_deint();
			if($dvd_deint)
				$video_deint = $dvd_deint;

			$deint_filter = "bwdif=deint=$video_deint";

			$ffmpeg->add_video_filter($deint_filter);

			if($arg_vf)
				$ffmpeg->add_video_filter($arg_vf);

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			$ffmpeg->fullscreen();

		}

		if($disc_type == 'dvd' && $dvd_encoder == 'ffpipe') {

			$ffmpeg->set_encoder('ffpipe');

			$ffmpeg->input_filename('-');

			$dvd_copy = new DVDCopy();

			$dvd_copy->input_filename($input_filename);
			$dvd_copy->output_filename('-');
			$dvd_copy->input_track($tracks_model->ix);
			$dvd_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

			$dvd_copy_command = $dvd_copy->get_executable_string();

			$dvd_copy_command .= ' 2> /dev/null';

			$ffmpeg_command = $ffmpeg->get_executable_string();

			// Note that ffplay will display VOBSUB subtitles by default, which is nice to see if they have them
			if($opt_no_subtitles)
				$ffmpeg_command .= ' -sn';

			$ffmpeg_command = "$dvd_copy_command | $ffmpeg_command";

		}

		if($disc_type == 'dvd' && $dvd_encoder != 'ffpipe') {

			$ffmpeg->set_encoder('ffplay');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
			}

			$ffmpeg_command = $ffmpeg->get_executable_string();

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
			$ffmpeg_command = $ffmpeg->ffprobe();

		}

		if($opt_encode_info)
			echo "$ffmpeg_command\n";
		else
			passthru($ffmpeg_command);

	}

}
