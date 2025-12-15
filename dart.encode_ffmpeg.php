<?php

/** Encode DVDs **/
/*
 * Experimental encoding with ffmpeg
 * - no chapter support
 * - does not support closed captioning
 */

if($dvd_encoder == 'ffmpeg' || $dvd_encoder == 'ffpipe') {

	$ffmpeg = new FFMpeg();
	$ffmpeg->set_encoder('ffmpeg');

	if($debug)
		$ffmpeg->debug();

	if($verbose)
		$ffmpeg->verbose();

	if($quiet || $opt_encode)
		$ffmpeg->quiet();

	$arr_metadata = array();

}

if($disc_type == 'dvd' && ($opt_encode_info || $opt_encode) && ($dvd_encoder == 'ffmpeg' || $dvd_encoder == 'ffpipe')) {

	if($dvd_encoder == 'ffmpeg') {

		$ffmpeg->input_filename($input_filename);
		$ffmpeg->input_track($tracks_model->ix);

	} elseif($dvd_encoder == 'ffpipe') {

		require 'dart.dvd_copy.php';

		$dvd_copy->input_filename($input_filename);
		$dvd_copy->output_filename('-');
		$dvd_copy_command = $dvd_copy->get_executable_string();

		$ffmpeg->disc_type = 'dvdcopy';
		$ffmpeg->generate_pts();
		$ffmpeg->input_filename('-');

	}

	if($opt_test_existing)
		$ffmpeg->overwrite(false);
	else
		$ffmpeg->overwrite(true);

	if($opt_qa)
		$ffmpeg->set_duration($qa_max);

	/** Video **/

	if($vcodec == 'gpu') {

		$cq = $series_model->get_cq();
		$qmin = $series_model->get_qmin();
		$qmax = $series_model->get_qmax();

		if($arg_cq)
			$cq = $arg_cq;
		if($arg_qmin)
			$qmin = $arg_qmin;
		if($arg_qmax)
			$qmax = $arg_qmax;

		if($hardware == 'nvidia') {

			$ffmpeg->set_vcodec('h264_nvenc');

			$ffmpeg->set_rc_lookahead(32);
			$ffmpeg->add_argument('rc', 'vbr');
			$ffmpeg->add_argument('tune', 'hq');
			$ffmpeg->add_argument('preset', 'p7');

			$arr_metadata[] = "hw=nvidia";

			if($cq)
				$arr_metadata[] = "cq=$cq";
			if($qmin)
				$arr_metadata[] = "qmin=$qmin";
			if($qmax)
				$arr_metadata[] = "qmax=$qmax";

		}

		if($hardware == 'amd') {

			$ffmpeg->set_vcodec('h264_vaapi');

			$ffmpeg->add_argument('vaapi_device', '/dev/dri/renderD129');
			$ffmpeg->add_argument('rc_mode', '1');
			$ffmpeg->set_rc_lookahead(0);
			$ffmpeg->add_video_filter('format=nv12,hwupload');

			$arr_metadata[] = "hw=amd";

			if($cq)
				$arr_metadata[] = "cq=$cq";
			if($qmin)
				$arr_metadata[] = "qmin=$qmin";
			if($qmax)
				$arr_metadata[] = "qmax=$qmax";

		}

		$ffmpeg->set_crf(null);

		$ffmpeg->set_cq($cq);
		$ffmpeg->set_qmin($qmin);
		$ffmpeg->set_qmax($qmax);

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
		$ffmpeg->set_qmin(null);
		$ffmpeg->set_qmax(null);

		$video_quality = intval($series_model->get_crf());

		if($arg_crf)
			$video_quality = intval($arg_crf);

		$ffmpeg->set_crf($video_quality);

	}

	if($opt_fast)
		$ffmpeg->set_preset('ultrafast');

	// Set video filters based on frame info
	$crop = $episodes_model->crop;
	if($crop != null && $opt_crop && $crop != '720:480:0:0')
		$ffmpeg->add_video_filter("crop=$crop");

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

	if($arg_acodec && ($arg_acodec == 'aac' || $arg_acodec == 'flac'))
		$acodec = $arg_acodec;

	if($acodec == 'aac')
		$acodec = 'libfdk_aac';

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
	if($dvd_encoder == 'ffpipe')
		$arr_metadata[] = "ffipe=$ffmpeg_version";
	else
		$arr_metadata[] = "ffmpeg=$ffmpeg_version";

	if(count($arr_metadata)) {
		$str_metadata = implode(',', $arr_metadata);
		$ffmpeg->add_metadata('encoder_settings', $str_metadata);
	}

	$ffmpeg->output_filename($filename);

	$ffmpeg_command = $ffmpeg->get_executable_string();

	if($opt_log_progress)
		$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

	if($dvd_encoder == 'ffpipe') {
		$ffmpeg_command = "$dvd_copy_command 2> /dev/null | $ffmpeg_command";
	}

	if($opt_time)
		$ffmpeg_command = "tout $ffmpeg_command";

	if($opt_encode) {
		$encode_command = $ffmpeg_command;
		require 'dart.encode_episode.php';
	} else {
		echo "$ffmpeg_command\n";
	}

}
