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
	$parser->addOption('import', array(
		'short_name' => '-i',
		'long_name' => '--import',
		'description' => 'Import DVD metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('archive', array(
		'short_name' => '-a',
		'long_name' => '--archive',
		'description' => 'Update DVD metadata to latest schema',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('rip', array(
		'short_name' => '-r',
		'long_name' => '--rip',
		'description' => 'Rip the episodes from a DVD device or ISO',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('encode', array(
		'short_name' => '-e',
		'long_name' => '--encode',
		'description' => 'Encode episodes in the queue',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('random', array(
		'long_name' => '--random',
		'description' => 'Choose encoding order randomly',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Do a dry run',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('info', array(
		'long_name' => '--info',
		'description' => 'Display metadata about a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('dump_iso', array(
		'long_name' => '--iso',
		'description' => 'Copy the DVD filesystem to an ISO',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('dump_ifo', array(
		'long_name' => '--ifo',
		'description' => 'Backup the DVD IFOs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('max', array(
		'short_name' => '-m',
		'long_name' => '--max',
		'description' => 'Max # of episodes to rip or encode',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('wait', array(
		'short_name' => '-w',
		'long_name' => '--wait',
		'description' => 'Wait for media to be in the tray before proceeding',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('queue', array(
		'short_name' => '-q',
		'long_name' => '--queue',
		'description' => 'Display the episodes in the queue to be encoded',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('reset_queue', array(
		'long_name' => '--reset',
		'description' => 'Remove all episodes from the queue',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('skip', array(
		'short_name' => '-s',
		'long_name' => '--skip',
		'description' => 'Skip the number of episodes to rip or encode',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('open_trays', array(
		'long_name' => '--open',
		'description' => 'Open all DVD trays',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('close_trays', array(
		'long_name' => '--close',
		'description' => 'Close all DVD trays',
		'action' => 'StoreTrue',
		'default' => false,
	));
	/*
	$parser->addOption('ftp', array(
		'short_name' => '-f',
		'long_name' => '--ftp',
		'description' => 'FTP finished files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/
	/*
	$parser->addOption('dumpvob', array(
		'long_name' => '--vob',
		'description' => 'Dump stream to .vob file',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/

	$result = $parser->parse();

	extract($result->args);
	extract($result->options);
