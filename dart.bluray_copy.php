<?php

if(($opt_copy_info || $opt_encode_info) && $episode_id) {

	$bluray_copy = new BlurayCopy();
	$bluray_copy->set_binary('bluray_copy');

	if($debug)
		$bluray_copy->debug();

	if($verbose)
		$bluray_copy->verbose();

	if($dry_run)
		$bluray_copy->dry_run();

	$bluray_copy->input_filename($device);
	$bluray_copy->input_track($tracks_model->ix);
	$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

	$bluray_chapters = new BlurayChapters();
	$bluray_chapters->input_filename($device);
	$bluray_chapters->input_track($tracks_model->ix);
	$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

}
