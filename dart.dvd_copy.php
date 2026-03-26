<?php

if($disc_type == 'dvd' && ($dvd_encoder == 'dvd_copy' || $dvd_encoder == 'dvd_remux')) {

	$dvd_copy = new DVDCopy();

	if($debug)
		$dvd_copy->debug();

	if($verbose)
		$dvd_copy->verbose();

	$dvd_copy->input_filename($input_filename);
	$dvd_copy->output_filename($filename);
	$dvd_copy->input_track($tracks_model->ix);
	$dvd_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

}
