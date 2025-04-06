<?php

if($opt_encode_info && $episode_id && $video_encoder == 'x265') {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the episode metadata and the source itself to
	 * and builds a new HandBrake object.
	 */

	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;


	// HandBrake for --encode-info

	$handbrake = new HandBrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);

	/** Files **/

	$handbrake->input_filename($device);
	$handbrake->input_track($tracks_model->ix);

	/** Encoding **/

	if(($opt_no_dvdnav || $series_model->dvdnav == 0) && $disc_type == 'dvd')
		$handbrake->dvdnav(false);

	/** Video **/

	$fps = $series_model->get_preset_fps();
	$handbrake->set_video_encoder('x265');
	$video_quality = $series_model->get_crf();

	if(strlen($arg_crf))
		$video_quality = abs(intval($arg_crf));

	if($video_quality === 0)
		$handbrake->set_x264opts("lossless=1");

	// Handbrake sets default CRF to 22 for x265, override here
	if(is_null($video_quality))
		$handbrake->set_video_quality(28);
	elseif($video_quality > 0)
		$handbrake->set_video_quality($video_quality);

	/** x265 **/

	if($video_quality) {
		$x264_preset = $series_model->get_x264_preset();
		if(!$x264_preset)
			$x264_preset = 'medium';
		$handbrake->set_x264_preset($x264_preset);
	}

	// x265 supports animation tune, even though --fullhelp does not document it
	// See https://x265.readthedocs.io/en/stable/presets.html for details
	if($series_model->collection_id == 1)
		$handbrake->set_x264_tune('animation');

	/** frameinfo **/

	// Set framerate
	if($fps)
		$handbrake->set_video_framerate($fps);

	/*
	if($container == 'mp4' && $optimize_support)
		$handbrake->set_http_optimize();
	*/

	/** Audio **/

	$handbrake->add_audio_track($tracks_model->audio_ix);

	$audio_encoder = $series_model->get_audio_encoder();
	if($audio_encoder == 'fdk_aac' || $audio_encoder == 'ac3' || $audio_encoder == 'flac' || $audio_encoder == 'mp3') {
		$handbrake->add_audio_encoder($audio_encoder);
	} elseif($audio_encoder == 'fdk_aac,copy') {
		$handbrake->add_audio_encoder('fdk_aac');
		$handbrake->add_audio_encoder('copy');
	} else {
		$handbrake->add_audio_encoder('copy');
	}

	/** Subtitles **/

	$scan_subp_tracks = false;

	$has_closed_captioning = $tracks_model->has_closed_captioning();
	$num_subp_tracks = $tracks_model->get_num_subp_tracks();
	$num_active_subp_tracks = $tracks_model->get_num_active_subp_tracks();
	$num_active_en_subp_tracks = $tracks_model->get_num_active_subp_tracks('en');

	// Check for a subtitle track
	if($subs_support) {

		$subp_ix = $tracks_model->get_first_english_subp();

		// If we have a VobSub one, add it
		// Otherwise, check for a CC stream, and add that
		if($subp_ix) {
			$handbrake->add_subtitle_track($subp_ix);
			$d_subtitles = "VOBSUB";
		} elseif($has_closed_captioning) {
			$closed_captioning_ix = $num_active_subp_tracks + 1;
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
