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
	$h264_profile = 'high';
	$h264_level = '';
	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;
	if($tracks_model->format == 'NTSC')
		$x264opts = 'colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m';
	elseif($tracks_model->format == 'PAL')
		$x264opts = 'colorprim=bt470bg:transfer=gamma28:colormatrix=bt470bg';

	$handbrake = new Handbrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);
	$handbrake->set_dry_run($dry_run);
	$handbrake->set_x264opts($x264opts);

	switch($arg_hardware) {

		case 'psp':
			$h264_profile = 'main';
			$h264_level = '2.1';
			$subs_support = false;
			$chapters_support = false;
			$optimize_support = false;
			$force_preset = 'medium';
			$handbrake->set_x264opts($x264opts.':bframes=1');
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
			$handbrake->set_video_framerate(15);
			$handbrake->set_max_width(176);
			$handbrake->set_max_height(144);
			break;

	}

	/** Files **/

	$handbrake->input_filename($device);
	$handbrake->input_track($tracks_model->ix);

	// If using MakeMKV, don't pass a track number
	if($opt_makemkv)
		$handbrake->input_track(0);

	/** Encoding **/

	if($opt_no_dvdnav || $series_model->dvdnav == 0)
		$handbrake->dvdnav(false);

	/** Video **/

	$handbrake->set_video_encoder('x264');
	$video_quality = $series_model->get_crf();
	$grayscale = $series_model->grayscale;
	$handbrake->grayscale($grayscale);

	if($video_quality)
		$handbrake->set_video_quality($video_quality);

	/** H.264 **/

	$handbrake->set_h264_profile($h264_profile);
	$handbrake->set_h264_level($h264_level);

	/** x264 **/

	$x264_preset = $series_model->get_x264_preset();
	if(!$x264_preset)
		$x264_preset = 'medium';
	if($force_preset)
		$x264_preset = $force_preset;
	$x264_tune = $series_model->get_x264_tune();
	$animation = ($x264_tune == 'animation');
	$handbrake->set_x264_preset($x264_preset);
	$handbrake->set_x264_tune($x264_tune);

	$deinterlace = $series_model->get_preset_deinterlace();
	$decomb = $series_model->get_preset_decomb();
	$detelecine = $series_model->get_preset_detelecine();

	$progressive = $episodes_model->progressive;
	$top_field = $episodes_model->top_field;
	$bottom_field = $episodes_model->bottom_field;

	// Detelecine by default if PTS hasn't been scanned
	if($progressive == null && $top_field == null && $bottom_field == null)
		$detelecine = true;

	// If all progressive, disable and override decomb, detelecine, and deinterlace
	if($progressive > 0 && $top_field == 0 && $bottom_field == 0) {
		$decomb = false;
		$detelecine = false;
		$deinterlace = false;
	}

	// Default to 24 FPS
	$fps = $series_model->get_preset_fps();
	if(!$fps || $fps == 24)
		$handbrake->set_video_framerate("24000/1001");
	else
		$handbrake->set_video_framerate($fps);

	// Simplifed version of dart ffmpeg checks
	while($top_field || $bottom_field) {

		// Top Field only
		if($progressive == 0 && $bottom_field == 0) {
			$detelecine = true;
			break;
		}

		// Bottom Field only
		if($progressive == 0 && $top_field == 0) {
			$detelecine = true;
			break;
		}

		// Top Field only, but under 1 second
		/*
		if($top_field <= 30 && $bottom_field == 0) {
			$detelecine = false;
			break;
		}
		*/

		// Bottom Field only, but under 1 second
		/*
		if($top_field == 0 && $bottom_field <= 30) {
			$detelecine = false;
			break;
		}
		*/

		// Top Field and Bottom Field, each under one second
		/*
		if($top_field <= 30 && $bottom_field <= 30) {
			$detelecine = false;
			break;
		}
		*/

		// All other cases
		$detelecine = true;

		break;

	}

	$handbrake->deinterlace($deinterlace);
	$handbrake->decomb($decomb);
	$handbrake->detelecine($detelecine);

	if($container == 'mp4' && $optimize_support)
		$handbrake->set_http_optimize();

	/** Audio **/

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

	// Lossless video support
	if($video_quality === 0) {
		$handbrake->set_video_quality(0);
		$handbrake->set_x264opts('');
		$handbrake->set_h264_profile('high444');
		$handbrake->set_x264_preset('');
		$handbrake->enable_audio(false);
		$handbrake->subtitle_tracks = array();
		$handbrake->add_chapters(false);
	}

	$handbrake_command = $handbrake->get_executable_string();

}
