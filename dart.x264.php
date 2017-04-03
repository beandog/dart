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
	 */

	$deinterlace = false;
	$decomb = false;
	$detelecine = false;
	$h264_profile = "high";
	$h264_level = "4.1";

	$handbrake = new Handbrake;
	$handbrake->set_binary($handbrake_bin);
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);
	$handbrake->set_dry_run($dry_run);

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
	$x264_tune = $series_model->get_x264_tune();
	$animation = ($x264_tune == 'animation');
	$handbrake->set_x264_preset($x264_preset);
	$handbrake->set_x264_tune($x264_tune);
	$handbrake->set_http_optimize();
	$handbrake->deinterlace($series_model->get_preset_deinterlace());
	$handbrake->decomb($series_model->get_preset_decomb());
	$handbrake->detelecine($series_model->get_preset_detelecine());

	/** Audio **/

	// Find the audio track to use
	$best_quality_audio_streamid = $tracks_model->get_best_quality_audio_streamid();
	$first_english_streamid = $tracks_model->get_first_english_streamid();

	// Major FIXME -- The audio stream should never be guessed at this point in
	// the encode.  A *proper* call to the database should fetch it, and then
	// set the *track number*.

	$audio_stream_id = "0x80";

	// Do a a check for a dry run here, because HandBrake scans the source directly
	// which can take some time.
	if(!$dry_run) {

		if($handbrake->dvd_has_audio_stream_id($best_quality_audio_streamid)) {
			$handbrake->add_audio_stream($best_quality_audio_streamid);
			$audio_stream_id = $best_quality_audio_streamid;
		} elseif($handbrake->dvd_has_audio_stream_id($first_english_streamid)) {
			$handbrake->add_audio_stream($first_english_streamid);
			$audio_stream_id = $first_english_streamid;
		} else {
			$handbrake->add_audio_stream("0x80");
		}

	}

	$audio_details = $tracks_model->get_audio_details($audio_stream_id);
	$display_audio_passthrough = display_audio($audio_details['format'], $audio_details['channels']);

	$audio_encoder = $series_model->get_audio_encoder();
	$audio_bitrate = $series_model->get_audio_bitrate();
	if($audio_encoder == 'fdk_aac') {
		$handbrake->add_audio_encoder('fdk_aac');
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
	} elseif($audio_encoder == 'copy') {
		$handbrake->add_audio_encoder('copy');
	} else {
		$handbrake->add_audio_encoder('copy');
	}

	/** Subtitles **/

	// Check for a subtitle track
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

	/** Chapters **/

	$handbrake->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
	$handbrake->add_chapters();

	$handbrake_command = $handbrake->get_executable_string();

}
