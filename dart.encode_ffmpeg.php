<?php

/** Encode DVDs **/
/*
 * Experimental encoding with ffmpeg
 * - no chapter support
 * - does not support closed captioning
 */

if($disc_type == 'dvd' && $opt_encode_info && $dvd_encoder == 'ffmpeg') {

	$ffmpeg = new FFMpeg();
	$ffmpeg->set_encoder('ffmpeg');

	if($debug)
		$ffmpeg->debug();

	if($verbose)
		$ffmpeg->verbose();

	$ffmpeg->input_filename($input_filename);
	$ffmpeg->input_track($tracks_model->ix);

	if($opt_qa)
		$ffmpeg->set_duration($qa_max);

	/** Video **/

	if($vcodec == 'x264') {
		$ffmpeg->set_vcodec('libx264');
		$ffmpeg->set_tune($series_model->get_x264_tune());
	} elseif($vcodec == 'x265') {
		$ffmpeg->set_vcodec('libx265');
	} elseif($vcodec == 'h264_nvenc') {
		$ffmpeg->set_vcodec('h264_nvenc');
	} elseif($vcodec == 'hevc_nvenc') {
		$ffmpeg->set_vcodec('hevc_nvenc');
	} else {
		$vcodec = 'hevc_nvenc';
		$ffmpeg->set_vcodec('hevc_nvenc');
	}

	// Override default CRF for now while testing, which is slightly less than the default
	if($vcodec == 'h264_nvenc' || $vcodec == 'hevc_nvenc')
		$video_quality = 20;
	elseif($vcodec == 'hevc_nvenc')
		$video_quality = 26;
	else
		$video_quality = intval($series_model->get_crf());

	if($arg_crf)
		$video_quality = intval($arg_crf);

	if($video_quality && !$opt_no_crf) {
		if($vcodec == 'h264_nvenc' || $vcodec == 'hevc_nvenc')
			$ffmpeg->set_cq($video_quality);
		else
			$ffmpeg->set_crf($video_quality);
	}

	if($opt_fast)
		$ffmpeg->set_preset('ultrafast');
	elseif($opt_slow)
		$ffmpeg->set_preset('slow');

	// Set video filters based on frame info
	$crop = $episodes_model->crop;
	if($crop != null && $opt_crop && $crop != '720:480:0:0')
		$ffmpeg->add_video_filter("crop=$crop");

	$deint_filter = "bwdif=deint=$video_deint";
	if(!$opt_no_filters)
		$ffmpeg->add_video_filter($deint_filter);

	// If no arguments or options are passed, then use bwdif by default
	if(!$opt_no_fps && !$opt_no_filters) {
		// FIXME overriding setting it earlier
		if($video_format == 'pal')
			$fps = 50;
		else
			$fps = 59.94;
	}

	if($fps && !$opt_no_fps)
		$ffmpeg->add_video_filter("fps=$fps");

	$ffmpeg->set_rc_lookahead(32);

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

		// When copying closed captioning with ffmpeg, the time indexes are always off, so
		// drop it completely.
		if($tracks_model->has_closed_captioning())
			$ffmpeg->remove_closed_captioning();

		// Use an external SSA file instead of existing closed captioning
		if($dvd_encode_ssa)
			$ffmpeg->add_ssa_filename($ssa_filename);

	}

	if($prefix)
		$filename = $prefix.$filename;

	$ffmpeg->output_filename($filename);

	$ffmpeg_command = $ffmpeg->get_executable_string();

	if($opt_time)
		$ffmpeg_command = "tout $ffmpeg_command";

	if($opt_log_progress)
		$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

	if($opt_test_existing)
		$ffmpeg_command = "test ! -e $filename && $ffmpeg_command";

	echo "$ffmpeg_command\n";

}
