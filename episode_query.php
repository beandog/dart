#!/usr/bin/php
<?php

	require_once 'config.local.php';

	require_once 'dart.functions.php';
	require_once 'class.dvd.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/tracks.php';
	require_once 'models/series.php';

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode Query Tool";
	$parser->addArgument('filenames', array('required' => true, 'multiple' => true));
	$parser->addOption('opt_dirname', array(
		'long_name' => '--dirname',
		'description' => '\'Series Name (Year)/Season XX/\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_filename', array(
		'long_name' => '--filename',
		'description' => '\'Series Name - sXXeXX\' (default)',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_verbose', array(
		'long_name' => '--verbose',
		'short_name' => '-v',
		'description' => '\'Series Name - sXXeXX - Episode Title\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_jfin', array(
		'long_name' => '--jfin',
		'description' => '\'Series Name (Year)/\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	/*
	$parser->addOption('opt_vfat', array(
		'long_name' => '--vfat',
		'description' => 'Filenames for removable media (PSP, Sansa)',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/

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

	if(!file_exists($filename)) {
		echo "File doesn't exist '$filename'\n";
		exit;
	}

	$realpath = realpath($filename);
	$pathinfo = pathinfo($realpath);
	$movie = false;
	if(substr($pathinfo['basename'], 0, 1) == '3' || substr($pathinfo['basename'], 0, 1) == '4' || substr($pathinfo['basename'], 0, 1) == '5' || substr($pathinfo['basename'], 0, 1) == '8' || substr($pathinfo['basename'], 0, 1) == '9')
		$movie = true;
	$str_elements = explode('.', $pathinfo['basename']);

	// If giving an ISO, just want the series title name
	if($pathinfo['extension'] == 'iso') {
		$series_id = intval($str_elements[1]);
		$series_model = new Series_Model($series_id);
		$series_title = $series_model->title;
		if(!strlen($series_title)) {
			echo "DELETED-SERIES-".$pathinfo['filename']."\n";
			exit;
		}
		$safe_title = safe_filename_title($series_title);
		$safe_title = preg_replace('/^The /', '', $safe_title);
		$safe_title = str_replace(' ', '-', $safe_title);
		$safe_title = str_replace(':', '', $safe_title);
		$safe_title = str_replace('!', '', $safe_title);
		$safe_title = str_replace('?', '', $safe_title);
		$safe_title = str_replace(',', '', $safe_title);
		echo "$safe_title\n";
		exit;
	}

	if(count($str_elements) < 3 || !file_exists($realpath) || !(in_array($pathinfo['extension'], array('mkv')))) {
		goto next_episode;
	}

	$episode_query = array();
	$episode_id = intval($str_elements[3]);
	$episodes_model = new Episodes_Model($episode_id);

	if(!$episodes_model) {
		goto next_episode;
	}

	// If no options passed, simply pass the filename
	if(!$opt_dirname && !$opt_filename)
		$opt_filename = true;

	$episode_metadata = $episodes_model->get_metadata();

	// If season is set as '100' in database, it's flagged as as 'Special' for Plex to pick up
	if($episode_metadata['season'] == 100)
		$episode_metadata['season'] = 0;

	// An episode can override series title if it is in format (Series Title)
	if(substr($episode_metadata['title'], 0, 1) == "(") {


		$episode_metadata['series_title'] = substr($episode_metadata['title'], 1, strpos($episode_metadata['title'], ")"));

	}

	$series_dirname = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['series_title']);

	$filename = $series_dirname;

	if($episode_metadata['production_year']) {
		$series_dirname .= " ";
		$series_dirname .= "(".$episode_metadata['production_year'].")";
	}

	$season_dirname = "Season ";
	$season_dirname .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);

	$season_filename = " - ";
	$season_filename .= "s";
	$season_filename .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);
	$season_filename .= "e";
	$episode_number = $episodes_model->get_number();
	$season_filename .= str_pad($episode_number, 2, 0, STR_PAD_LEFT);

	$episode_title = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['title']);

	$series_jfin = $episode_metadata['jfin'];

	if($movie) {

		$filename = $episode_title;

	} else {

		$filename .= $season_filename;
		if(($opt_verbose)) {
			$filename .= " - ";
			$filename .= $episode_title;
		}
	}

	$filename .= ".".$pathinfo['extension'];
	$episode_title .= ".".$pathinfo['extension'];

	if($opt_jfin) {

		if($opt_dirname) {
			if($series_jfin)
				echo $series_dirname." [tvdbid-$series_jfin]/";
			else
				echo "$series_dirname/";
		}
		if(!$movie && $opt_dirname)
			echo $season_dirname."/";
		if($opt_filename)
			echo $filename;

	} else {

		if($opt_dirname)
			echo $series_dirname."/";
		if(!$movie && $opt_dirname)
			echo $season_dirname."/";
		if($opt_filename)
			echo $filename;

	}

	echo "\n";

	// The ghosts of monolithic code haunt me. :)
	next_episode:
	if(count($filenames))
		goto start;
