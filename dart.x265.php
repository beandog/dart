<?php

if(($opt_encode_info || $opt_rip_info) && $episode_id && $video_encoder == 'x265') {

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
	 * audio codec copy
	 * x265 defaults for preset and profile
	 * NTSC color
	 */

	$deinterlace = false;
	$decomb = false;
	$detelecine = false;
	$subs_support = true;
	$chapters_support = true;
	$optimize_support = true;
	$force_preset = false;

	$video_quality = $series_model->get_crf();
	$x265_preset = $series_model->get_x264_preset();
	$x265_opts = 'colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m';
	if($video_quality <= 14)
		$preset_opts = 'level-idc=52';
	elseif($video_quality <= 16)
		$preset_opts = 'level-idc=51';
	elseif($video_quality <= 18)
		$preset_opts = 'level-idc=50';
	elseif($video_quality <= 24)
		$preset_opts = 'level-idc=41';
	elseif($video_quality <= 26)
		$preset_opts = 'level-idc=40';
	else
		$preset_opts = '';

	if($preset_opts)
		$x265_opts = "$preset_opts:$x265_opts";

	$fps = $series_model->get_preset_fps();

	// HandBrake for --encode-info

	if($opt_encode_info) {

		$handbrake = new Handbrake;
		$handbrake->set_binary($handbrake_bin);
		$handbrake->verbose($verbose);
		$handbrake->debug($debug);
		$handbrake->set_dry_run($dry_run);

		/** Files **/

		$handbrake->input_filename($device);
		$handbrake->input_track($tracks_model->ix);

		/** Encoding **/

		if($opt_no_dvdnav || $series_model->dvdnav == 0)
			$handbrake->dvdnav(false);

		/** Video **/

		$handbrake->set_video_encoder('x265');

		if($video_quality)
			$handbrake->set_video_quality($video_quality);

		/** x265 **/

		$handbrake->set_x264_preset($x265_preset);

		$handbrake->set_x264opts($x265_opts);

		$handbrake->deinterlace($series_model->get_preset_deinterlace());
		$handbrake->decomb($series_model->get_preset_decomb());
		$handbrake->detelecine($series_model->get_preset_detelecine());
		if($fps)
			$handbrake->set_video_framerate($fps);
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

	}

}
