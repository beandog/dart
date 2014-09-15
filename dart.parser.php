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
	$parser->addOption('opt_rip', array(
		'short_name' => '-r',
		'long_name' => '--rip',
		'description' => 'Rip the episodes from a DVD device or ISO',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_encode', array(
		'short_name' => '-e',
		'long_name' => '--encode',
		'description' => 'Encode episodes in the queue',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_random', array(
		'long_name' => '--random',
		'description' => 'Choose encoding order randomly',
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
	$parser->addOption('opt_dump_iso', array(
		'long_name' => '--iso',
		'description' => 'Copy the DVD filesystem to an ISO',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_dump_ifo', array(
		'long_name' => '--ifo',
		'description' => 'Backup the DVD IFOs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_max', array(
		'short_name' => '-m',
		'long_name' => '--max',
		'description' => 'Max # of episodes to rip or encode',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('arg_skip', array(
		'short_name' => '-s',
		'long_name' => '--skip',
		'description' => 'Skip the number of episodes to rip or encode',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('opt_wait', array(
		'short_name' => '-w',
		'long_name' => '--wait',
		'description' => 'Wait for media to be in the tray before proceeding',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_queue', array(
		'short_name' => '-q',
		'long_name' => '--queue',
		'description' => 'Display the episodes in the queue to be encoded',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_remove_queue', array(
		'long_name' => '--remove',
		'description' => 'Remove all episodes from the queue',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_reset_queue', array(
		'long_name' => '--reset',
		'description' => 'Reset queue status for episodes',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_resume', array(
		'long_name' => '--resume',
		'description' => 'Resume encoding episodes in queue',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_queue_series_id', array(
		'long_name' => '--series',
		'description' => 'Limit queue to series id',
		'action' => 'StoreInt',
		'default' => 0,
		'help_name' => 'id',
	));
	$parser->addOption('arg_queue_dvd_id', array(
		'long_name' => '--dvd',
		'description' => 'Limit queue to DVD id',
		'action' => 'StoreInt',
		'default' => 0,
		'help_name' => 'id',
	));
	$parser->addOption('arg_queue_track_id', array(
		'long_name' => '--track',
		'description' => 'Limit queue to track id',
		'action' => 'StoreInt',
		'default' => 0,
		'help_name' => 'id',
	));
	$parser->addOption('arg_queue_episode_id', array(
		'short_name' => '-p',
		'description' => 'Limit queue to episode id',
		'action' => 'StoreInt',
		'default' => 0,
		'help_name' => 'episode',
	));
	$parser->addOption('opt_qa', array(
		'long_name' => '--qa',
		'description' => 'Run qa checks on DVD',
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
	/*
	$parser->addOption('opt_ftp', array(
		'long_name' => '--ftp',
		'description' => 'FTP finished files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/
	/*
	$parser->addOption('opt_dumpvob', array(
		'long_name' => '--vob',
		'description' => 'Dump stream to .vob file',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/

	$result = $parser->parse();

	extract($result->args);
	extract($result->options);
