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
		$handbrake->add_argument('stop-at', "duration:$qa_max");

	/** Files **/

	$handbrake->input_filename($device);
	$handbrake->input_track($tracks_model->ix);
	$handbrake->add_argument('title', $tracks_model->ix);

	/** Video **/

	$handbrake->add_argument('encoder', 'nvenc_h264');
	$handbrake->add_argument('quality', '18');
	$handbrake->add_argument('encoder-preset', 'slowest');
	$handbrake->add_option('bwdif=bob');
	$handbrake->add_option('markers');
	$handbrake->add_option('optimize');

	/** Audio **/

	$handbrake->add_audio_track($tracks_model->audio_ix);

	$handbrake->add_argument('aencoder', 'fdk_aac');
	$handbrake->add_argument('aq', '5');

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

	/** Final filename */

	if($prefix && !$opt_batch)
		$filename = $prefix.$filename;

	if($arg_export_dir != '.') {
		$export_dir = realpath($arg_export_dir);
		$filename = $export_dir.'/'.$filename;
	}

	$handbrake->add_argument('output', $filename);

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
