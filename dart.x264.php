<?php

if($opt_encode_info && $episode_id) {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the episode metadata and the source itself to
	 * and builds a new HandBrake object.
	 */

	/**
	 * Encoding specification Blu-ray friendly
	 * use dvdnav over dvdread
	 * chapters
	 * no fixed video, audio codec bitrate
	 * audio codec fdk_aac
	 * fallback audio ac3,dts copy
	 * x264 preset medium
	 * x264 tune animation, film or grain
	 * x264 optional grayscale
	 * H.264 profile high
	 * H.264 level 4.1
	 * NTSC color
	 */

	$deinterlace = false;
	$decomb = false;
	$detelecine = false;
	$h264_profile = 'high';
	$h264_level = '4.1';
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

	// The HandBrake class will pass options to find the first English audio track if
	// no specific streams are given it. If there is only one stream, add that. If there
	// is more than one stream and only one English audio track, then default to letting
	// Handbrake select it. If there are more than two active English audio tracks,
	// however, a scan will be run to see which one is highest quality -- this is not
	// common on non-movie DVDs, so the scans should be quite infrequent.

	$scan_audio_tracks = false;
	$num_active_audio_tracks = $tracks_model->get_num_active_audio_tracks();
	$num_active_en_audio_tracks = $tracks_model->get_num_active_audio_tracks('en');

	// Specifically pick the first track. If none are selected at all, then HandBrake
	// will pick the first English one. However, there may be only one, which is undefined,
	// in which case it would be skipped. This also works around that.
	if($num_active_audio_tracks == 1)
		$handbrake->add_audio_track(1);

	if($num_active_en_audio_tracks > 1)
		$scan_audio_tracks = true;

	// Do a a check for a dry run here, because HandBrake scans the source directly
	// which can take some time.
	if(!$dry_run && $scan_audio_tracks) {

		$best_quality_audio_streamid = $tracks_model->get_best_quality_audio_streamid();

		echo basename($device)." - track: ".str_pad($tracks_model->ix, 2, 0, STR_PAD_LEFT)." audio tracks: $num_active_audio_tracks en tracks: $num_active_en_audio_tracks best: $best_quality_audio_streamid\n";

		$handbrake->add_audio_stream($best_quality_audio_streamid);

	}

	$audio_encoder = $series_model->get_audio_encoder();
	$audio_bitrate = $series_model->get_audio_bitrate();
	if($audio_encoder == 'fdk_aac' || $audio_encoder == 'mp3') {
		$handbrake->add_audio_encoder($audio_encoder);
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
	} elseif($audio_encoder == 'fdk_aac,copy') {
		$handbrake->add_audio_encoder('fdk_aac');
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
		$handbrake->add_audio_encoder('copy');
		$handbrake->add_audio_encoder('copy');
	} else {
		$handbrake->add_audio_encoder('copy');
	}

	/** Subtitles **/

	// Check for a subtitle track
	if($subs_support) {

		$subp_ix = $tracks_model->get_first_english_subp();

		// If we have a VobSub one, add it
		// Otherwise, check for a CC stream, and add that
		if($subp_ix) {
			$handbrake->add_subtitle_track($subp_ix);
			$d_subtitles = "VOBSUB";
		} elseif($handbrake->closed_captioning) {
			$handbrake->add_subtitle_track($handbrake->closed_captioning_ix);
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
