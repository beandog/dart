#!/usr/bin/php
<?php

	require_once 'config.local.php';

	require_once 'inc.mdb2.php';

	require_once 'class.dvd.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/tracks.php';

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode Query Tool";
	$parser->addArgument('filenames', array('required' => true, 'multiple' => true));
	$parser->addOption('opt_dirname', array(
		'long_name' => '--dirname',
		'description' => 'Display directory name',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_filename', array(
		'long_name' => '--filename',
		'description' => 'Display filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_full', array(
		'long_name' => '--full',
		'description' => 'Display directory and filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_verbose', array(
		'long_name' => '--verbose',
		'short_name' => '-v',
		'description' => 'Display episode title as well',
		'action' => 'StoreTrue',
		'default' => false,
	));


	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	/** Start everything **/

	start:

	$filename = array_shift($filenames);


	$realpath = realpath($filename);
	$dirname = dirname($realpath);
	$query_filename = str_replace($dirname.'/', '', $realpath);
	$str_elements = explode('.', $query_filename);

	if(count($str_elements) < 3 || !file_exists($realpath) || substr($query_filename, strlen($extension) * -1) != $extension) {
		print_r($realpath);
		echo "Invalid filename\n";
		exit(1);
	}

	$episode_query = array();
	$episode_id = intval($str_elements[3]);
	$episodes_model = new Episodes_Model($episode_id);

	if(!$episodes_model) {
		echo "Couldn't find episode for filename $filename\n";
		exit(1);
	}

	// If no options passed, simply pass the filename
	if($opt_full) {
		$opt_dirname = $opt_filename = true;
	} elseif(!$opt_dirname && !$opt_filename)
		$opt_filename = true;

	$episode_metadata = $episodes_model->get_metadata();

	// An episode can override series title if it is in format (Series Title)
	if(substr($episode_metadata['title'], 0, 1) == "(") {


		$episode_metadata['series_title'] = substr($episode_metadata['title'], 1, strpos($episode_metadata['title'], ")"));

	}

	$series_dirname = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['series_title']);

	$filename = $series_dirname;

	$series_dirname .= " ";
	$series_dirname .= "(".$episode_metadata['production_year'].")";

	$season_dirname = "Season ";
	$season_dirname .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);

	$filename .= " - ";
	$filename .= "s";
	$filename .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);
	$filename .= "e";
	$episode_number = $episodes_model->get_number();

	while($episode_number > 100)
		$episode_number -= 100;

	// An episode number also being over 100 is an indicator that it's another series
	$filename .= str_pad($episode_number, 2, 0, STR_PAD_LEFT);

	if($opt_verbose) {
		$filename .= " - ";
		$filename .= preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['title']);
	}

	$filename .= ".".$container;

	if($opt_dirname)
		echo $series_dirname."/".$season_dirname."/";
	
	if($opt_filename)
		echo $filename;
	
	echo "\n";

	// The ghosts of monolithic code haunt me. :)
	if(count($filenames))
		goto start;
