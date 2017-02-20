#!/usr/bin/php
<?php

	// Overrides to defaults
	require_once 'config.local.php';

	require_once 'inc.mdb2.php';

	require_once 'class.dvd.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/tracks.php';

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "DVD Query Tool";
	$parser->addArgument('devices', array('optional' => true, 'default' => '/dev/sr0', 'multiple' => true));
	$parser->addOption('opt_json', array(
		'long_name' => '--json',
		'short_name' => '-j',
		'description' => 'JSON output',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_num_episodes', array(
		'long_name' => '--num-episodes',
		'short_name' => '-n',
		'description' => 'Display # of episodes',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_num_encoded', array(
		'long_name' => '--num-encoded',
		'short_name' => '-e',
		'description' => 'Display # of episodes encoded',
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

	$device = array_shift($devices);

	$dvd_query = array();

	$dvds_model = new Dvds_Model;

	$dvd = new DVD($device);
			
	$dvd_query['dvd']['dvdread_id'] = $dvd->dvdread_id();
	$dvds_model_id = $dvds_model->find_dvdread_id($dvd_query['dvd']['dvdread_id']);

	if(!$dvds_model_id) {
		echo "DVD not found\n";
		die(1);
	}

	$dvds_model = new Dvds_Model($dvds_model_id);
	$dvd_query['dvd']['volname'] = $dvds_model->title;
	$dvd_query['titles'] = array();
	
	$dvd_episodes = $dvds_model->get_episodes();

	if($opt_num_episodes) {
		echo count($dvd_episodes);
		echo "\n";
		exit(0);
	}

	if($opt_num_encoded)
		$num_encoded = 0;

	// Display the episode names
	foreach($dvd_episodes as $episode_id) {

		$episodes_model = new Episodes_Model($episode_id);
		$episode_metadata = $episodes_model->get_metadata();
		$tracks_model = new Tracks_Model($episode_metadata['track_id']);

		// Setting the episode number from the model, which does the correct
		// job of getting the ultimate one
		$episode_metadata['episode_number'] = $episodes_model->get_number();

		// Get the long title, including the part number
		$episode_metadata['long_title'] = $episodes_model->get_long_title();

		// Set the epix
		$episode_metadata['epix'] = $episode_metadata['nsix'].".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);

		$dvd_query['metadata']['series_title'] = $episode_metadata['series_title'];
		$dvd_query['metadata']['nsix'] = $episode_metadata['nsix'];

		$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
		$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
		$filename .= ".".$episode_metadata['nsix'];
		$filename .= ".$container";

		$episode_metadata['filename'] = $filename;

		if($opt_num_encoded && file_exists($episode_metadata['filename']))
			$num_encoded++;

		$dvd_query['titles'][] = array(

			'dvd' => array(
				'track' => $episode_metadata['track_ix'],
				'starting_chapter' => $episode_metadata['starting_chapter'],
				'ending_chapter' => $episode_metadata['ending_chapter'],
			),

			'video' => array(
				'standard' => $episode_metadata['format'],
				'aspect_ratio' => $episode_metadata['aspect'],
				'grayscale' => $episode_metadata['grayscale'],
				'closed_captioning' => $episode_metadata['closed_captioning'],
			),

			'audio' => array(
				'stream_id' => $tracks_model->get_first_english_streamid(),
			),

			'subtitles' => array(
				'track' => $tracks_model->get_first_english_subp(),
			),

			'metadata' => array(
				'name' => $episode_metadata['long_title'],
				'volume' => $episode_metadata['volume'],
				'season' => $episode_metadata['season'],
				'part_number' => $episode_metadata['part'],
				'index' => $episode_metadata['ix'],
				'number' => $episode_metadata['episode_number'],
				'epix' => $episode_metadata['epix'],
			),

			'database' => array(
				'dvd' => $episode_metadata['dvd_id'],
				'track' => $episode_metadata['track_id'],
				'episode' => $episode_id,
				'filename' => $episode_metadata['filename'],
			),

		);

	}

	if(!count($dvd_query['titles'])) {
		echo "No episodes listed for $device\n";
		die;
	}

	if($opt_json)
		echo json_encode($dvd_query, JSON_PRETTY_PRINT)."\n";
	elseif($opt_num_encoded) {

		echo $num_encoded;
		echo "\n";
		exit(0);
		
	} else {

		echo "Disc Title: ".$dvd_query['dvd']['volname']."\n";
		foreach($dvd_query['titles'] as $arr_title) {
			echo $arr_title['database']['filename'];
			echo " ";
			echo "Track: ".str_pad($arr_title['dvd']['track'], 2, 0, STR_PAD_LEFT);
			echo " ";
			echo "Chapters: ".str_pad($arr_title['dvd']['starting_chapter'], 2, 0, STR_PAD_LEFT)."-".str_pad($arr_title['dvd']['ending_chapter'], 2, 0, STR_PAD_LEFT);
			echo " ";
			echo "Season: ".str_pad($arr_title['metadata']['season'], 2, 0, STR_PAD_LEFT)."x".str_pad($arr_title['metadata']['number'], 2, 0, STR_PAD_LEFT);
			echo " ";
			echo "Episode: ".$arr_title['metadata']['name'];
			echo "\n";
		}

	}

	// The ghosts of monolithic code haunt me. :)
	if(count($devices))
		goto start;
