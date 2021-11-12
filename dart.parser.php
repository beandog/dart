<?php

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "DVD Archiving Tool";
	$parser->addArgument('devices', array('optional' => true, 'multiple' => true));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'long_name' => '--verbose',
		'description' => 'Be verbose',
		'action' => 'Counter',
		'default' => 0,
	));
	$parser->addOption('debug', array(
		'short_name' => '-z',
		'long_name' => '--debug',
		'description' => 'Print out debugging information',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_import', array(
		'short_name' => '-i',
		'long_name' => '--import',
		'description' => 'Import DVD metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_archive', array(
		'short_name' => '-a',
		'long_name' => '--archive',
		'description' => 'Update DVD metadata to latest schema',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_info', array(
		'long_name' => '--info',
		'description' => 'Display metadata about a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_encode_info', array(
		'long_name' => '--encode-info',
		'description' => 'Display encoding instructions for a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_copy_info', array(
		'long_name' => '--copy-info',
		'description' => 'Display dvd_copy instructions for a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_rip_info', array(
		'long_name' => '--rip-info',
		'description' => 'Display dvd_copy + ffmpeg instructions for a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_pts_info', array(
		'long_name' => '--pts-info',
		'description' => 'Display scan instructions for generating PTS output',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_pts_import', array(
		'long_name' => '--pts-import',
		'description' => 'Include instructions for importing PTS data',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_no_dvdnav', array(
		'long_name' => '--no-dvdnav',
		'description' => 'Tell HandBrake not to use libdvdnav',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_dump_iso', array(
		'long_name' => '--iso',
		'description' => 'Copy the DVD filesystem to an ISO',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_backup', array(
		'long_name' => '--backup',
		'description' => 'Backup to an ISO in batch mode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_vob', array(
		'long_name' => '--vob',
		'description' => 'Use a VOB file as encode info source',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_skip_existing', array(
		'long_name' => '--skip-existing',
		'description' => 'Skip writing to existing files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_wait', array(
		'short_name' => '-w',
		'long_name' => '--wait',
		'description' => 'Wait for media to be in the tray before proceeding',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_iso_filename', array(
		'long_name' => '--iso-filename',
		'description' => 'Display ISO filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_rename_iso', array(
		'long_name' => '--rename-iso',
		'description' => 'Rename ISO to correct syntax',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_qa', array(
		'long_name' => '--qa',
		'description' => 'Make changes when doing a QA run',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_time', array(
		'long_name' => '--time',
		'description' => 'Log time of encode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_crf', array(
		'long_name' => '--crf',
		'description' => 'Set encoding CRF',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('arg_hardware', array(
		'long_name' => '--hardware',
		'description' => 'Override target hardware',
		'action' => 'StoreString',
		'default' => 'main',
	));

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);
