<?php

/** Encode DVDs **/
/*
 * Classic ripping using HandBrake
 */
if($disc_type == 'dvd' && $opt_encode_info && $dvd_encoder == 'handbrake') {

	$handbrake = new HandBrake;
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);

	/** Files **/

	$handbrake->input_filename($input_filename);
	$handbrake->input_track($tracks_model->ix);

	/** Video **/

	$handbrake->set_vcodec($vcodec);

	$video_quality = intval($series_model->get_crf());
	if(isset($arg_crf))
		$video_quality = intval($arg_crf);
	$handbrake->set_video_quality($video_quality);

	if($opt_fast)
		$handbrake->set_x264_preset('ultrafast');
	elseif($opt_slow)
		$handbrake->set_x264_preset('slow');

	$x264_tune = $series_model->get_x264_tune();
	if($vcodec == 'x264' && $x264_tune)
		$handbrake->set_x264_tune($x264_tune);

	if($video_deint == 'bwdif' && !$opt_no_filters) {
		$handbrake->set_video_framerate($fps);
		$handbrake->enable_bwdif();
		$handbrake->enable_cfr();
	}
	if($opt_no_filters)
		$prefix .= 'no-filters-';

	// If no arguments or options are passed, then use bwdif by default
	if(!$opt_no_fps && !$opt_no_filters) {
		$handbrake->set_video_framerate($fps);
		$handbrake->enable_bwdif();
		$handbrake->enable_cfr();
		// FIXME overriding setting it earlier
		if($video_format == 'pal')
			$fps = 50;
		else
			$fps = 59.94;
	}

	/** Frame and fields **/

	// Set framerate
	if($fps && !$opt_no_fps) {
		$handbrake->set_video_framerate($fps);
		$handbrake->enable_cfr();
	}

	/** Audio **/

	$handbrake->add_audio_track($tracks_model->audio_ix);

	$acodec = $series_model->get_acodec();

	if($arg_acodec && ($arg_acodec == 'aac' || $arg_acodec == 'flac'))
		$acodec = $arg_acodec;

	if($acodec == 'aac') {
		$acodec = 'fdk_aac';
		$handbrake->set_audio_vbr(5);
	} elseif($acodec == 'flac') {
		$acodec = 'flac16';
	}

	$handbrake->add_acodec($acodec);

	/** Subtitles **/

	// Check for a subtitle track

	if($encode_subtitles) {

		$handbrake->enable_subtitles();

		$subp_ix = $tracks_model->get_first_english_subp();
		$has_closed_captioning = $tracks_model->has_closed_captioning();

		// If we have a VobSub one, add it
		// Otherwise, check for a CC stream, and add that
		if($subp_ix) {
			$handbrake->add_subtitle_track($subp_ix);
		} elseif($has_closed_captioning) {
			$num_subp_tracks = $tracks_model->get_num_active_subp_tracks();
			$closed_captioning_ix = $num_subp_tracks + 1;
			$handbrake->add_subtitle_track($closed_captioning_ix);
		}

	}

	/** Chapters **/

	$handbrake->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
	$handbrake->add_chapters();

	$handbrake->set_video_format($tracks_model->format);

	$handbrake->output_filename($filename);

	if($opt_qa)
		$handbrake->set_duration($qa_max);

	if($prefix)
		$filename = $prefix.$filename;

	$handbrake->output_filename($filename);

	$handbrake_command = $handbrake->get_executable_string();

	if($opt_time)
		$handbrake_command = "tout $handbrake_command";

	if($opt_test_existing)
		$handbrake_command = "test ! -e $filename && $handbrake_command";

	echo "$handbrake_command\n";

}
