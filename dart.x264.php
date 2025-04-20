<?php

if($opt_encode_info && $dvd_encoder == 'handbrake' && $episode_id && ($vcodec == 'x264' || $vcodec == 'x265')) {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the episode metadata and the source itself to
	 * and builds a new HandBrake object.
	 */

	$subs_support = true;
	$chapters_support = true;
	$force_preset = false;

	$handbrake = new HandBrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);

	/** Files **/

	$handbrake->input_filename($device);
	if($disc_type == 'dvd')
		$handbrake->input_track($tracks_model->ix);

	/** Video **/

	$handbrake->set_vcodec($vcodec);
	$video_quality = $series_model->get_crf();

	if($arg_crf)
		$video_quality = abs(intval($arg_crf));

	$handbrake->set_video_quality($video_quality);


	$x264_preset = 'medium';
	if($force_preset)
		$x264_preset = $force_preset;
	if($x264_preset != 'medium')
		$handbrake->set_x264_preset($x264_preset);

	$x264_tune = $series_model->get_x264_tune();

	if($vcodec == 'x264' && $x264_tune && $video_quality)
		$handbrake->set_x264_tune($x264_tune);

	/** Frame and fields **/

	// Set framerate
	if($fps)
		$handbrake->set_video_framerate($fps);

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
