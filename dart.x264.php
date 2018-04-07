<?php

if($opt_encode_info && $episode_id) {

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
	 * H.264 level 5.0
	 * NTSC color
	 */

	$deinterlace = false;
	$decomb = false;
	$detelecine = false;
	$h264_profile = 'high';
	$h264_level = '5.0';
	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;
	$x264opts = 'colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m';

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

	if($opt_no_dvdnav)
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

	/*
	$arr_x264_opts = array();
	$series_x264opts = $series_model->get_x264opts();
	if(strlen($series_x264opts))
		$arr_x264_opts[] = $series_x264opts();
	$x264_opts = implode(":", $arr_x264_opts);
	$handbrake->set_x264opts($x264_opts);
	*/
	$x264_preset = $series_model->get_x264_preset();
	if(!$x264_preset)
		$x264_preset = 'medium';
	if($force_preset)
		$x264_preset = $force_preset;
	$x264_tune = $series_model->get_x264_tune();
	$animation = ($x264_tune == 'animation');
	$handbrake->set_x264_preset($x264_preset);
	$handbrake->set_x264_tune($x264_tune);
	$handbrake->deinterlace($series_model->get_preset_deinterlace());
	$handbrake->decomb($series_model->get_preset_decomb());
	$handbrake->detelecine($series_model->get_preset_detelecine());
	switch($series_model->get_preset_upscale()) {
		case  '480p':
		$handbrake->width = 720;
		$handbrake->height = 480;
		$handbrake->auto_anamorphic = true;
		break;

		case '720p':
		$handbrake->width = 1280;
		$handbrake->height = 720;
		$handbrake->auto_anamorphic = true;
		break;

		case '1080p':
		$handbrake->width = 1920;
		$handbrake->height = 1080;
		$handbrake->auto_anamorphic = true;
		break;
	}
	$fps = $series_model->get_preset_fps();
	if($fps)
		$handbrake->set_video_framerate($fps);
	if($container == 'mp4' && $optimize_support)
		$handbrake->set_http_optimize();

	/** Audio **/

	$handbrake->add_audio_track($tracks_model->audio_ix);

	$audio_encoder = $series_model->get_audio_encoder();
	$audio_bitrate = $series_model->get_audio_bitrate();
	if($audio_encoder == 'fdk_aac' || $audio_encoder == 'mp3' || $audio_encoder == 'ac3' || $audio_encoder == 'eac3') {
		$handbrake->add_audio_encoder($audio_encoder);
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
	} elseif($audio_encoder == 'fdk_aac,copy') {
		$handbrake->add_audio_encoder('fdk_aac');
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
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

	$handbrake_command = $handbrake->get_executable_string();

}
