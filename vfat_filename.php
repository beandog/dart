#!/usr/bin/php
<?php

	require_once 'config.local.php';

	require_once 'dart.functions.php';
	require_once 'class.dvd.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/tracks.php';

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode Query Tool";
	$parser->addArgument('filenames', array('required' => true, 'multiple' => true));

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	/*
	$hardware = 'main';

	if($opt_vfat) {
		$hardware = 'vfat';
	}
	*/

	/** Start everything **/

	start:

	$filename = array_shift($filenames);

	$realpath = realpath($filename);
	$pathinfo = pathinfo($realpath);
	$movie = false;
	if(substr($pathinfo['basename'], 0, 1) == '4' || substr($pathinfo['basename'], 0, 1) == '8')
		$movie = true;
	$str_elements = explode('.', $pathinfo['basename']);
	if(count($str_elements) < 3 || !file_exists($realpath) || !($pathinfo['extension'] == 'mkv' || ($pathinfo['extension'] == 'mp4' || $pathinfo['extension'] == 'mpg' || $pathinfo['extension'] == 'vob'))) {
		goto next_episode;
	}

	$episode_query = array();
	$episode_id = intval($str_elements[3]);
	$episodes_model = new Episodes_Model($episode_id);

	if(!$episodes_model) {
		goto next_episode;
	}

	// If no options passed, simply pass the filename

	$episode_metadata = $episodes_model->get_metadata();

	// An episode can override series title if it is in format (Series Title)
	if(substr($episode_metadata['title'], 0, 1) == "(") {
		$episode_metadata['series_title'] = substr($episode_metadata['title'], 1, strpos($episode_metadata['title'], ")"));
	}

	$filename = '';

	$season_filename = str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);
	$season_filename .= ".";
	$episode_number = $episodes_model->get_number();
	$season_filename .= str_pad($episode_number, 2, 0, STR_PAD_LEFT);

	$episode_title = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['title']);

	if($movie) {
		$filename = $episode_title;
	} else {
		$filename .= $season_filename;
		$filename .= " - ";
		$filename .= $episode_title;
	}

	$filename .= ".".$pathinfo['extension'];
	$episode_title .= ".".$pathinfo['extension'];

	echo $filename;

	echo "\n";

	// The ghosts of monolithic code haunt me. :)
	next_episode:
	if(count($filenames))
		goto start;
