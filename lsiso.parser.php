<?php

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "List and Search DVD ISOs";
	$parser->addArgument('directories', array('optional' => true, 'multiple' => true));

	// Searches
	$parser->addOption('q_view_nsix', array(
		'long_name' => '--nsix',
		'description' => 'Limit to NSIX',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('q_view_episodes_series_title', array(
		'long_name' => '--title',
		'description' => 'Search for series title',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('q_presets_name', array(
		'long_name' => '--preset',
		'description' => 'Search for preset name',
		'action' => 'StoreString',
		'default' => '',
	));

	// Booleans
	$parser->addOption('q_ripping_name_handbrake', array(
		'long_name' => '--handbrake',
		'description' => 'Using HandBrake',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_ripping_name_ffmpeg', array(
		'long_name' => '--ffmpeg',
		'description' => 'Using ffmpeg',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_ripping_name_ffpipe', array(
		'long_name' => '--ffpipe',
		'description' => 'Using ffpipe',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_collection_title_cartoons', array(
		'long_name' => '--cartoons',
		'description' => 'Cartoons series',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_collection_title_shorts', array(
		'long_name' => '--shorts',
		'description' => 'Cartoons shorts',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_collection_title_movies', array(
		'long_name' => '--movies',
		'description' => 'Movies',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_presets_name_animation', array(
		'long_name' => '--animation',
		'description' => 'Preset name has animation',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_presets_name_film', array(
		'long_name' => '--film',
		'description' => 'Preset name has film',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_presets_ivtc', array(
		'long_name' => '--ivtc',
		'description' => 'Series that need inverse telecine',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_skip_ivtc', array(
		'long_name' => '--no-ivtc',
		'description' => 'Skip series that need inverse telecine',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_skip_digital', array(
		'long_name' => '--no-digital',
		'description' => 'Skip series that are digital',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_presets_digital', array(
		'long_name' => '--digital',
		'description' => 'Series is digital animation',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('q_dvd_bugs_dvd_id', array(
		'long_name' => '--has-bugs',
		'description' => 'DVD has bugs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('dir_media_cartoons', array(
		'long_name' => '--usb',
		'description' => 'On external drive',
		'action' => 'StoreTrue',
		'default' => false,
	));

	$parser->addOption('opt_symlink', array(
		'long_name' => '--symlink',
		'description' => 'Create symlinks here',
		'action' => 'StoreTrue',
		'default' => false,
	));

	$parser->addOption('opt_mkdir', array(
		'long_name' => '--mkdir',
		'description' => 'Create directories here of series title',
		'action' => 'StoreTrue',
		'default' => false,
	));

	$parser->addOption('opt_samples', array(
		'long_name' => '--samples',
		'description' => 'Use the first ISO from a series',
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
