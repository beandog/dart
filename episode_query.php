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
	$parser->addArgument('filename', array('required' => true));
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

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	/** Start everything **/

	$str_elements = explode('.', $filename);

	if(count($str_elements) < 3 || !file_exists($filename) || substr($filename, strlen($extension) * -1) != $extension) {
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
	$filename .= str_pad($episodes_model->get_number(), 2, 0, STR_PAD_LEFT);
	$filename .= ".".$container;


	if($opt_dirname)
		echo $series_dirname."/".$season_dirname."/";
	
	if($opt_filename)
		echo $filename;
	
	echo "\n";
