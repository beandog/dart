<?php

if($disc_type == 'bluray') {

	$num_pgs_tracks = $tracks_model->get_num_subp_tracks();
	$num_active_pgs_tracks = $tracks_model->get_num_active_subp_tracks();
	$num_active_en_pgs_tracks = $tracks_model->get_num_active_subp_tracks('eng');

	$audio_ix = $tracks_model->get_best_quality_audio_ix('bluray');

	// mkvmerge indexes all tracks starting at 0 being first video stream
	if($num_pgs_tracks) {
		$pgs_ix = 0;
		$pgs_ix += count($tracks_model->get_audio_streams());
		$pgs_ix += $tracks_model->get_first_english_subp();
		$bluray_copy->add_pgs_track($pgs_ix);
	}

	$mkvmerge = new Mkvmerge();
	$mkvmerge->add_video_track(0);
	$mkvmerge->add_audio_track($audio_ix);
	$mkvmerge->add_subtitle_track($pgs_ix);

}
