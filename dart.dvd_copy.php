<?php

if(($opt_copy || $opt_remux || $dvd_encoder == 'ffpipe') && $episode_id) {

	$dvd_copy = new DVDCopy();

	if($debug)
		$dvd_copy->debug();

	if($verbose)
		$dvd_copy->verbose();

	$dvd_copy->input_filename($input_filename);
	$dvd_copy->output_filename($filename);
	$dvd_copy->input_track($tracks_model->ix);
	$dvd_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

	$dvd_copy_command = $dvd_copy->get_executable_string();

	$encode_command = $dvd_copy_command;

}
