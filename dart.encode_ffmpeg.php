<?php

/** Encode DVDs with ffmpeg **/

if($disc_type == 'dvd' && $dvd_encoder == 'ffmpeg') {

	$ffmpeg = new FFMpeg();

	if($debug)
		$ffmpeg->debug();

	if($verbose)
		$ffmpeg->verbose();

	if($quiet || $opt_encode)
		$ffmpeg->quiet();

	$ffmpeg->input_filename($input_filename);
	$ffmpeg->input_track($tracks_model->ix);
	$starting_chapter = $episodes_model->starting_chapter;
	if($starting_chapter)
		$ffmpeg->set_chapters($starting_chapter, null);

	$arr_metadata = array();

	if($arg_vcodec == 'x264' || $arg_vcodec == 'libx264')
		$vcodec = 'x264';
	elseif($arg_vcodec == 'avc' || $arg_vcodec == 'h264')
		$vcodec = 'avc';
	elseif($arg_vcodec == 'hevc' || $arg_vcodec == 'h265')
		$vcodec = 'hevc';

	$video_deint = $series_model->bwdif;
	$dvd_deint = $dvds_model->get_deint();
	if($dvd_deint)
		$video_deint = $dvd_deint;

	if($opt_test_existing)
		$ffmpeg->overwrite(false);
	else
		$ffmpeg->overwrite(true);

	if($opt_qa)
		$ffmpeg->set_duration($qa_max);

	/** Video **/

	$rate_control = '';

	if($vcodec == 'avc' || $vcodec == 'hevc') {

		$cq = $series_model->get_crf();

		if($arg_crf)
			$cq = $arg_crf;

		if($vcodec == 'avc')
			$ffmpeg->set_vcodec('h264_nvenc');
		elseif($vcodec == 'hevc')
			$ffmpeg->set_vcodec('hevc_nvenc');

		$ffmpeg->set_rc_lookahead(32);
		$ffmpeg->add_argument('preset', 'p7');

		$arr_metadata[] = "cq=$cq";

		$ffmpeg->set_crf(null);

		$ffmpeg->set_cq($cq);

	}

	if($vcodec == 'x264') {

		$ffmpeg->set_vcodec('libx264');
		$ffmpeg->set_tune($series_model->get_x264_tune());
		$x264_preset = $series_model->x264_preset;
		if($x264_preset)
			$ffmpeg->set_preset($x264_preset);

		if($opt_slow)
			$ffmpeg->set_preset('veryslow');

		$ffmpeg->set_cq(null);

		$video_quality = intval($series_model->get_crf());

		if($arg_crf)
			$video_quality = intval($arg_crf);

		$ffmpeg->set_crf($video_quality);

	}

	if($opt_fast)
		$ffmpeg->set_preset('ultrafast');

	// Set video filters based on frame info

	$deint_filter = "bwdif=deint=$video_deint";
	$ffmpeg->add_video_filter($deint_filter);

	if($video_format == 'pal')
		$fps = 50;
	else
		$fps = 59.94;

	$ffmpeg->add_video_filter("fps=$fps");

	if($arg_vf)
		$ffmpeg->add_video_filter($arg_vf);

	if($denoise)
		$ffmpeg->add_video_filter('hqdn3d');

	/** Audio **/
	$audio_streamid = $tracks_model->get_first_english_streamid();
	if(!$audio_streamid)
		$audio_streamid = '0x80';
	$ffmpeg->add_audio_stream($audio_streamid);

	$acodec = $series_model->get_acodec();

	if($arg_acodec && ($arg_acodec == 'aac' || $arg_acodec == 'mp3'))
		$acodec = $arg_acodec;

	if($acodec == 'aac')
		$acodec = 'aac';

	if($acodec == 'mp3')
		$acodec = 'libmp3lame';

	$ffmpeg->set_acodec($acodec);

	/** Chapters **/
	$starting_chapter = $episodes_model->starting_chapter;
	$ending_chapter = $episodes_model->ending_chapter;
	if($starting_chapter || $ending_chapter) {
		$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
	}

	/** Subtitles **/
	if($encode_subtitles) {

		$ffmpeg->enable_subtitles();

		$subp_ix = $tracks_model->get_first_english_subp();
		if(!$subp_ix && ($tracks_model->get_num_active_subp_tracks() == 1))
			$subp_ix = '0x20';

		if($subp_ix) {
			// Not sure if I need this now that I'm pulling straight from dvdvideo format
			// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
			$ffmpeg->add_subtitle_stream($subp_ix);
		}

		// Remove closed captioning. There are only 367 cartoon episodes that have CC and *not* vobsub
		// TMNT '87 (208), TMNT 2012 (119), Droopy, and Scooby-Doo Show
		// It adds an extra step to encoding because they have to be extracted first.
		// Another reason they are being removed is that ffmpeg garbles them, they do not play
		// at the correct index time.
		// See 'view_episode_eng_subs' database view
		if($tracks_model->has_closed_captioning())
			$ffmpeg->remove_closed_captioning();

	}

	if($prefix)
		$filename = $prefix.$filename;

	if($denoise)
		$arr_metadata[] = "hqdn3d";
	if($opt_ffpipe)
		$arr_metadata[] = "ffpipe=$ffmpeg_version";
	else
		$arr_metadata[] = "ffmpeg=$ffmpeg_version";

	if(count($arr_metadata)) {
		$str_metadata = implode(',', $arr_metadata);
		$ffmpeg->add_metadata('encoder_settings', $str_metadata);
	}

	$ffmpeg->output_filename($filename);

	$ffmpeg_pipe = false;
	$ffmpeg_remux = false;
	$ffmpeg_program = 'ffmpeg';

	if($opt_ffpipe) {

		$ffmpeg_pipe = true;

		$ffmpeg->input_filename('-');

		$dvd_copy = new DVDCopy();

		$dvd_copy->input_filename($input_filename);
		$dvd_copy->output_filename('-');
		$dvd_copy->input_track($tracks_model->ix);

		$dvd_copy_command = $dvd_copy->get_executable_string();

		$dvd_copy_command .= ' 2> /dev/null';

	}

	if($opt_remux)
		$ffmpeg_remux = true;

	if($opt_ffprobe)
		$ffmpeg_program = 'ffprobe';

	$ffmpeg_command = $ffmpeg->get_ffmpeg_command($ffmpeg_pipe, $ffmpeg_remux, $ffmpeg_program);

	if($opt_ffpipe)
		$ffmpeg_command = "$dvd_copy_command | $ffmpeg_command";

	if($opt_log_progress)
		$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

	if($opt_time)
		$ffmpeg_command = "tout $ffmpeg_command";

	if($opt_encode_info)
		echo "$ffmpeg_command\n";

	if($opt_encode) {
		$encode_command = $ffmpeg_command;
		require 'dart.encode_episode.php';
	}

}
