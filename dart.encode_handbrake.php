<?php

/** Encode DVDs **/
/*
 * Classic ripping using HandBrake
 */
if($disc_type == 'dvd' && $dvd_encoder == 'handbrake' && ($opt_encode_info || $opt_encode)) {

	$handbrake = new HandBrake;
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);

	if($opt_qa)
		$handbrake->set_duration($qa_max);

	/** Files **/

	$handbrake->input_filename($device);
	$handbrake->input_track($tracks_model->ix);

	/** Video **/

	$handbrake->set_vcodec('nvenc_h264');
	$handbrake->set_preset('slowest');
	$cq = $series_model->get_crf();
	$handbrake->set_video_quality($cq);

	$handbrake->enable_bwdif();

	/** Audio **/

	$handbrake->add_audio_track($tracks_model->audio_ix);

	$handbrake->add_acodec('fdk_aac');
	$handbrake->set_audio_vbr(5);

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
		$arr_prefix[] = "cq-$cq";
		if($opt_slow)
			$arr_prefix[] = 'vslow';
		$arr_prefix[] = "hb";
		$prefix = implode("-", $arr_prefix);
		$filename = basename($filename, ".$container")."-$prefix.$container";
	}

	if($arg_export_dir) {
		$export_dir = $arg_export_dir;
		$filename = "$export_dir/$filename";
	}

	$handbrake->output_filename($filename);

	$handbrake_command = $handbrake->get_executable_string();

	if($opt_time)
		$handbrake_command = "tout $handbrake_command";

	if($opt_test_existing)
		$handbrake_command = "test ! -e $filename && $handbrake_command";

	if($opt_encode) {
		$encode_command = $handbrake_command;
		require 'dart.encode_episode.php';
	} else {
		echo "$handbrake_command\n";
	}

}
