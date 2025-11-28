<?php

/** Encode DVDs **/
/*
 * Classic ripping using HandBrake
 */
if($disc_type == 'dvd' && $opt_encode_info && $dvd_encoder == 'handbrake') {

	$handbrake = new HandBrake;
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);

	if($opt_qa)
		$handbrake->set_duration($qa_max);

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
		$handbrake->set_x264_preset('veryslow');

	$x264_tune = $series_model->get_x264_tune();
	if($vcodec == 'x264' && $x264_tune)
		$handbrake->set_x264_tune($x264_tune);

	$handbrake->enable_bwdif();

	if($denoise)
		$handbrake->denoise();

	if($sharpen)
		$handbrake->sharpen($sharpen);
	if($sharpen_tune)
		$handbrake->sharpen_tune($sharpen_tune);

	if($vcodec == 'nvenc_h264') {

		$cq = $series_model->get_cq();
		$qmin = $series_model->get_qmin();
		$qmax = $series_model->get_qmax();

		if($arg_cq)
			$cq = $arg_cq;
		if($arg_qmin)
			$qmin = $arg_qmin;
		if($arg_qmax)
			$qmax = $arg_qmax;

		$handbrake->set_video_quality($cq);

		// Specific encoding options
		$arr_encopts = array();
		// Maximum that nvidia encoder allows
		$arr_encopts[] = "rc-lookahead=32";
		// Set to --slowest
		$arr_encopts[] = "preset=p7";
		// Tune for high quality
		$arr_encopts[] = "tune=hq";
		// qmax, qmin
		if($qmin)
			$arr_encopts[] = "qmin=$qmin";
		if($qmax)
			$arr_encopts[] = "qmax=$qmax";

		$encopts = implode(":", $arr_encopts);

		$handbrake->set_encopts($encopts);

	}

	/** Frame and fields **/

	// Set framerate
	if($video_format == 'pal')
		$fps = 50;
	else
		$fps = 59.94;
	$handbrake->set_video_framerate($fps);
	$handbrake->enable_cfr();

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

	/** Final filename */

	if($prefix && !$opt_batch)
		$filename = $prefix.$filename;

	// Rewrite prefix if doing a batch encode to make it simpler to compare filesizes
	if($opt_batch) {
		$arr_prefix = array();
		if($vcodec == 'h264_nvenc') {
			$arr_prefix[] = "cq-$cq";
			if($qmin)
				$arr_prefix[] = "qmin-$qmin";
			// I'm okay with hardcoding qmax to 30 for now, I don't see myself changing it since it's so high
			if($qmax && $qmax != 30)
				$arr_prefix[] = "qmax-$qmax";
		}
		if($denoise) {
			if($denoise == 'medium')
				$arr_prefix[] = "denoise";
			else
				$arr_prefix[] = "denoise-$denoise";
		}
		if($sharpen) {
			if($sharpen == 'medium')
				$arr_prefix[] = "sharpen";
			else
				$arr_prefix[] = "sharpen-$sharpen";
		}
		if($sharpen_tune)
			$arr_prefix[] = $sharpen_tune;
		$arr_prefix[] = "hb";
		$prefix = implode("-", $arr_prefix);
		$filename = basename($filename, ".$container")."-$prefix.$container";
	}

	$handbrake->output_filename($filename);

	$handbrake_command = $handbrake->get_executable_string();

	if($opt_time)
		$handbrake_command = "tout $handbrake_command";

	if($opt_test_existing)
		$handbrake_command = "test ! -e $filename && $handbrake_command";

	echo "$handbrake_command\n";

}
