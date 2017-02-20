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
	$parser->addOption('opt_display_filenames', array(
		'long_name' => '--filenames',
		'short_name' => '-f',
		'description' => 'Display episode filenames instead of info',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_num', array(
		'long_name' => '--num',
		'short_name' => '-n',
		'description' => 'Display # of episodes',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_encoded', array(
		'long_name' => '--encoded',
		'short_name' => '-e',
		'description' => 'Display episodes encoded',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_not', array(
		'long_name' => '--not',
		'short_name' => '-x',
		'description' => 'Display opposite',
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

	$opt_info = true;

	if($opt_json || $opt_display_filenames || $opt_num)
		$opt_info = false;
	
	$num_episodes = 0;
	$num_encoded = 0;
	$num_not_encoded = 0;
	$episode_filenames[] = array();
	$episodes_encoded = array();
	$episodes_not_encoded = array();
	$episode_encoded = false;
	$display_episode = '';

	start:

	$device = array_shift($devices);

	$dvd_query = array();

	$dvds_model = new Dvds_Model;

	$dvd = new DVD($device);
			
	$dvd_query['dvd']['dvdread_id'] = $dvd->dvdread_id();
	$dvds_model_id = $dvds_model->find_dvdread_id($dvd_query['dvd']['dvdread_id']);

	if(!$dvds_model_id && $opt_info) {
		echo "Disc Title: Not found -- needs import\n";
		if(count($devices))
			goto start;
	}

	$dvds_model = new Dvds_Model($dvds_model_id);
	$dvd_query['dvd']['volname'] = $dvds_model->title;
	$dvd_query['titles'] = array();
	
	$dvd_episodes = $dvds_model->get_episodes();

	$num_episodes = count($dvd_episodes);

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

		$episode_filenames[] = $episode_metadata['filename'];

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

		if($opt_display_filenames) {
			echo $episode_metadata['filename'];
			echo "\n";
		}

		if(file_exists($episode_metadata['filename'])) {
			$num_encoded++;
			$episodes_encoded[] = $episode_metadata['filename'];
			$episode_encoded = true;
		} else {
			$num_not_encoded++;
			$episodes_not_encoded[] = $episode_metadata['filename'];
			$episode_encoded = false;
		}

	}

	if(!count($dvd_query['titles'])) {
		echo "No episodes listed for $device\n";
		die;
	}

	if($opt_json)
		echo json_encode($dvd_query, JSON_PRETTY_PRINT)."\n";
	
	if($opt_info) {

		echo "Disc Title: ".$dvd_query['dvd']['volname']."\n";

		foreach($dvd_query['titles'] as $arr_title) {
			$display_episode = $arr_title['database']['filename'];
			$display_episode .= " ";
			$display_episode .= "Track: ".str_pad($arr_title['dvd']['track'], 2, 0, STR_PAD_LEFT);
			$display_episode .= " ";
			$display_episode .= "Chapters: ".str_pad($arr_title['dvd']['starting_chapter'], 2, 0, STR_PAD_LEFT)."-".str_pad($arr_title['dvd']['ending_chapter'], 2, 0, STR_PAD_LEFT);
			$display_episode .= " ";
			$display_episode .= "Season: ".str_pad($arr_title['metadata']['season'], 2, 0, STR_PAD_LEFT)."x".str_pad($arr_title['metadata']['number'], 2, 0, STR_PAD_LEFT);
			$display_episode .= " ";
			$display_episode .= "Episode: ".$arr_title['metadata']['name'];
			$display_episode .= "\n";

			if(!$opt_encoded)
				echo $display_episode;

			if($opt_encoded && !$opt_not && $episode_encoded)
				echo $display_episode;
			
			if($opt_encoded && $opt_not && !$episode_encoded)
				echo $display_episode;

		}

	}

	// The ghosts of monolithic code haunt me. :)
	if(count($devices))
		goto start;
	
	if($opt_num && $opt_encoded && !$opt_not) {
		echo $num_encoded;
		echo "\n";
		exit(0);
	}

	if($opt_num && $opt_encoded && $opt_not) {
		echo $num_not_encoded;
		echo "\n";
		exit(0);
	}

	if($opt_num) {
		echo $num_episodes;
		echo "\n";
	}
