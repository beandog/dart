#!/usr/bin/php
<?php

	require_once 'class.mp3.php';
	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "MP3 Encoder and Metadata Tools";
	$parser->addArgument('input_files', array('optional' => false, 'multiple' => true));
	$parser->addOption('opt_remove_metadata', array(
		'long_name' => '--remove-metadata',
		'description' => 'Remove all metadata',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_title', array(
		'long_name' => '--title',
		'description' => 'MP3 track title',
		'action' => 'StoreString',
		'help_name' => 'Title',
	));
	$parser->addOption('opt_artist', array(
		'long_name' => '--artist',
		'description' => 'MP3 artist',
		'action' => 'StoreString',
		'help_name' => 'Artist Name',
	));
	$parser->addOption('opt_album', array(
		'long_name' => '--album',
		'description' => 'MP3 album',
		'action' => 'StoreString',
		'help_name' => 'Album Title',
	));
	$parser->addOption('opt_track', array(
		'long_name' => '--track',
		'description' => 'MP3 track number',
		'action' => 'StoreInt',
		'help_name' => 1,
	));
	$parser->addOption('opt_year', array(
		'long_name' => '--year',
		'description' => 'MP3 year',
		'action' => 'StoreInt',
		'help_name' => 1976,
	));
	$parser->addOption('opt_album_art', array(
		'long_name' => '--album-art',
		'description' => 'MP3 album art',
		'action' => 'StoreString',
		'help_name' => 'cover.png',
	));

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	/** Start everything **/

	$num_input_files = count($input_files);
	$num_encoded = 0;

	start:

	while(count($input_files)) {
	
		$input_file = array_shift($input_files);

		$mp3 = new MP3($input_file);

		if($opt_remove_metadata) {
			$mp3->remove_metadata();
		}

		if($opt_title) {
			$mp3->set_title($opt_title);
		}

		if($opt_artist) {
			$mp3->set_artist($opt_artist);
		}

		if($opt_album) {
			$mp3->set_album($opt_album);
		}

		if($opt_track) {
			$mp3->set_track($opt_track);
		}

		if($opt_year) {
			$mp3->set_year($opt_year);
		}

		if($opt_album_art) {
			$mp3->set_album_art($opt_album_art);
		}

	}
