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
	$parser->addOption('opt_dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Do a dry run',
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
	$parser->addOption('opt_open_trays', array(
		'long_name' => '--open',
		'description' => 'Open all DVD trays',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_close_trays', array(
		'long_name' => '--close',
		'description' => 'Close all DVD trays',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_force', array(
		'short_name' => '-f',
		'long_name' => '--force',
		'description' => 'Close all DVD trays',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_stage', array(
		'long_name' => '--stage',
		'description' => 'Specific stage to execute',
		'action' => 'StoreString',
		'default' => 'all',
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
	$parser->addOption('opt_makemkv', array(
		'long_name' => '--makemkv',
		'description' => 'Use MakeMKV where possible',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_qa', array(
		'long_name' => '--qa',
		'description' => 'Make changes when doing a QA run',
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
