<?php

if($opt_encode_info && $episode_id && $video_encoder == 'x264') {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the episode metadata and the source itself to
	 * and builds a new HandBrake object.
	 */

	/**
	 * Encoding specification up to 1080p60
	 * use dvdnav over dvdread
	 * chapters
	 * no fixed video, audio codec bitrate
	 * audio codec fdk_aac
	 * fallback audio ac3,dts copy
	 * x264 preset medium
	 * x264 tune animation, film or grain
	 * x264 optional grayscale
	 * H.264 profile high
	 * H.264 level 4.1 default (5.0 for 720p and higher)
	 * NTSC color
	 */

	$deinterlace = false;
	$decomb = false;
	$detelecine = false;
	$h264_profile = '';
	$h264_level = '';
	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;

	if($disc_type == 'bluray')
		$x264opts = '';

	$handbrake = new Handbrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);
	$handbrake->set_dry_run($dry_run);
	if(isset($x264opts))
		$handbrake->set_x264opts($x264opts);

	$fps = $series_model->get_preset_fps();

	if($video_encoder == 'x264') {

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

			case 'gravity2':
				$h264_profile = 'baseline';
				$h264_level = '1b';
				$subs_support = false;
				$chapters_support = false;
				$optimize_support = false;
				$force_preset = 'medium';
				$fps = 15;
				$handbrake->set_max_width(176);
				$handbrake->set_max_height(144);
				break;

		}

	}

	/** Files **/

	$handbrake->input_filename($device);
	if($disc_type == 'dvd')
		$handbrake->input_track($tracks_model->ix);

	/** Encoding **/

	if($opt_no_dvdnav || $series_model->dvdnav == 0)
		$handbrake->dvdnav(false);

	/** Video **/

	$handbrake->set_video_encoder($video_encoder);
	$video_quality = $series_model->get_crf();
	$handbrake->grayscale($series_model->grayscale);

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
	if($arg_preset)
		$x264_preset = $arg_preset;
	$handbrake->set_x264_preset($x264_preset);

	// Set to animation tune if collection is Cartoons
	if($series_model->collection_id == 1)
		$x264_tune = 'animation';
	else
		$x264_tune = $series_model->get_x264_tune();

	if($x264_tune && $video_quality)
		$handbrake->set_x264_tune($x264_tune);

	/** Frame and fields **/

	$deinterlace = $series_model->get_preset_deinterlace();
	if($series_model->get_preset_decomb() || $series_model->decomb)
		$decomb = true;
	if($series_model->get_preset_detelecine() || $series_model->detelecine)
		$detelecine = true;
	if($series_model->get_preset_decomb() == 2 || $series_model->decomb == 2)
		$comb_detect = true;
	else
		$comb_detect = false;

	$progressive = $episodes_model->progressive;
	$top_field = $episodes_model->top_field;
	$bottom_field = $episodes_model->bottom_field;

	// Detelecine by default if PTS hasn't been scanned
	if($progressive == null && $top_field == null && $bottom_field == null) {
		$detelecine = true;
	}

	// If PAL format, detelecining is not needed
	if($tracks_model->format == 'PAL') {
		$detelecine = false;
		$fps = 25;
	}

	// Set framerate
	$handbrake->set_video_framerate($fps);

	$handbrake->detelecine($detelecine);
	$handbrake->decomb($decomb);
	$handbrake->comb_detect($comb_detect);

	if($container == 'mp4' && $optimize_support)
		$handbrake->set_http_optimize();

	/** Audio **/

	if($disc_type == 'dvd')
		$handbrake->add_audio_track($tracks_model->audio_ix);

	$audio_encoder = $series_model->get_audio_encoder();
	if($audio_encoder == 'fdk_aac' || $audio_encoder == 'mp3' || $audio_encoder == 'ac3' || $audio_encoder == 'eac3') {
		$handbrake->add_audio_encoder($audio_encoder);
	} elseif($audio_encoder == 'fdk_aac,copy') {
		$handbrake->add_audio_encoder('fdk_aac');
		$handbrake->add_audio_encoder('copy');
	} else {
		$handbrake->add_audio_encoder('copy');
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
			$num_subp_tracks = $tracks_model->get_num_subp_tracks();
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
