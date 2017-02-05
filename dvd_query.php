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
	$parser->addArgument('device', array('optional' => true, 'default' => '/dev/sr0'));
	$parser->addOption('opt_json', array(
		'long_name' => '--json',
		'short_name' => '-j',
		'description' => 'JSON output',
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
	
	$dvd_episodes = $dvds_model->get_episodes();

	// Display the episode names
	foreach($dvd_episodes as $episode_id) {

		$episodes_model = new Episodes_Model($episode_id);
		$episode_metadata = $episodes_model->get_metadata();

		$tracks_model = new Tracks_Model($episode_metadata['track_id']);

		$dvd_query['metadata']['series_title'] = $episode_metadata['series_title'];

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
				'name' => $episode_metadata['title'],
				'volume' => $episode_metadata['volume'],
				'season' => $episode_metadata['season'],
				'part_number' => $episode_metadata['part'],
				'index' => $episode_metadata['ix'],
			),

		);

	}

	if($opt_json)
		echo json_encode($dvd_query, JSON_PRETTY_PRINT)."\n";
	else {

		echo "Disc Title: ".$dvd_query['dvd']['volname']."\n";
		foreach($dvd_query['titles'] as $arr_title) {
			echo "Track: ".str_pad($arr_title['dvd']['track'], 2, 0, STR_PAD_LEFT);
			echo " ";
			echo "Chapters: ".str_pad($arr_title['dvd']['starting_chapter'], 2, 0, STR_PAD_LEFT)."-".str_pad($arr_title['dvd']['ending_chapter'], 2, 0, STR_PAD_LEFT);
			echo " ";
			echo "Episode: ".$arr_title['metadata']['name'];
			echo "\n";
		}

	}
