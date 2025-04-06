<?php

if($opt_encode_info && $dvd_encoder == 'handbrake' && $episode_id && $vcodec == 'x264') {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the episode metadata and the source itself to
	 * and builds a new HandBrake object.
	 */

	$h264_profile = '';
	$h264_level = '';
	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;

	if($disc_type == 'bluray')
		$x264opts = '';

	$handbrake = new HandBrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);
	if(isset($x264opts))
		$handbrake->set_x264opts($x264opts);

	$fps = $series_model->get_preset_fps();

	if($vcodec == 'x264') {

		switch($arg_hardware) {

			case 'psp':
				$h264_profile = 'main';
				$h264_level = '2.1';
				$subs_support = false;
				$chapters_support = false;
				$optimize_support = false;
				$force_preset = 'medium';
				$handbrake->set_x264opts('bframes=1');
				$handbrake->set_max_width(480);
				$handbrake->set_max_height(272);
				$handbrake->set_audio_downmix('stereo');
				break;

		}

	}

	/** Files **/

	$handbrake->input_filename($device);
	if($disc_type == 'dvd')
		$handbrake->input_track($tracks_model->ix);

	/** Encoding **/

	if(($opt_no_dvdnav || $series_model->dvdnav == 0) && $disc_type == 'dvd')
		$handbrake->dvdnav(false);

	/** Video **/

	$handbrake->set_vcodec($vcodec);
	$video_quality = $series_model->get_crf();

	if($arg_crf)
		$video_quality = abs(intval($arg_crf));

	$handbrake->set_video_quality($video_quality);

	$format = $tracks_model->format;

	/** H.264 **/

	if($h264_profile)
		$handbrake->set_h264_profile($h264_profile);
	if($h264_level)
		$handbrake->set_h264_level($h264_level);

	/** x264 **/

	$x264_preset = $series_model->get_x264_preset();
	if(!$x264_preset)
		$x264_preset = 'medium';
	if($force_preset)
		$x264_preset = $force_preset;
	if($x264_preset !== 'medium')
	$handbrake->set_x264_preset($x264_preset);

	$x264_tune = $series_model->get_x264_tune();

	if($x264_tune && $video_quality && $arg_hardware != 'psp')
		$handbrake->set_x264_tune($x264_tune);

	/** Frame and fields **/

	// Set framerate
	if($fps)
		$handbrake->set_video_framerate($fps);

	/*
	if($container == 'mp4' && $optimize_support)
		$handbrake->set_http_optimize();
	*/

	/** Audio **/

	if($disc_type == 'dvd')
		$handbrake->add_audio_track($tracks_model->audio_ix);

	$acodec = $series_model->get_acodec();
	if($acodec == 'fdk_aac' || $acodec == 'ac3' || $acodec == 'flac' || $acodec == 'mp3') {
		$handbrake->add_acodec($acodec);
		if($acodec == 'mp3')
			$handbrake->set_audio_bitrate('320k');
	} elseif($acodec == 'fdk_aac,copy') {
		$handbrake->add_acodec('fdk_aac');
		$handbrake->add_acodec('copy');
	} else {
		$handbrake->add_acodec('copy');
	}

	/** Subtitles **/

	$scan_subp_tracks = false;

	// Check for a subtitle track
	if($subs_support) {

		$subp_ix = $tracks_model->get_first_english_subp();
		$has_closed_captioning = $tracks_model->has_closed_captioning();

		// If we have a VobSub one, add it
		// Otherwise, check for a CC stream, and add that
		if($subp_ix) {
			$handbrake->add_subtitle_track($subp_ix);
			$d_subtitles = "VOBSUB";
		} elseif($has_closed_captioning) {
			// In older versions of HB, it would count empty subp tracks
			// $num_subp_tracks = $tracks_model->get_num_subp_tracks();
			$num_subp_tracks = $tracks_model->get_num_active_subp_tracks();
			$closed_captioning_ix = $num_subp_tracks + 1;
			$handbrake->add_subtitle_track($closed_captioning_ix);
			$d_subtitles = "Closed Captioning";
		} else {
			$d_subtitles = "None :(";
		}


	}

	/** Chapters **/

	if($chapters_support) {
		$handbrake->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
		$handbrake->add_chapters();
	}

	$handbrake_command = $handbrake->get_executable_string();

}
