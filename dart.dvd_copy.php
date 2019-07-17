<?php

if(($opt_copy_info || $opt_rip_info || $opt_pts_info) && $episode_id) {

	$dvd_copy = new DVDCopy();
	$dvd_copy->set_binary('dvd_copy');

	if($debug)
		$dvd_copy->debug();

	if($verbose)
		$dvd_copy->verbose();

	if($dry_run)
		$dvd_copy->dry_run();

	$dvd_copy->input_filename($device);
	$dvd_copy->input_track($tracks_model->ix);
	$dvd_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

}
