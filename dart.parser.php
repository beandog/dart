<?
	
	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "DVD Archiving Tool";
	$parser->addArgument('devices', array('optional' => true, 'multiple' => true));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'long_name' => '--verbose',
		'description' => 'Be verbose',
		'action' => 'StoreTrue',
	));
	$parser->addOption('debug', array(
		'short_name' => '-z',
		'long_name' => '--debug',
		'description' => 'Print out debugging information',
		'action' => 'StoreTrue',
	));
	$parser->addOption('import', array(
		'short_name' => '-i',
		'long_name' => '--import',
		'description' => 'Import DVD metadata into database',
		'action' => 'StoreTrue',
	));
	$parser->addOption('rip', array(
		'short_name' => '-r',
		'long_name' => '--rip',
		'description' => 'Rip the episodes from a DVD device or ISO',
		'action' => 'StoreTrue',
	));
	$parser->addOption('encode', array(
		'short_name' => '-e',
		'long_name' => '--encode',
		'description' => 'Encode episodes in the queue',
		'action' => 'StoreTrue',
	));
	$parser->addOption('info', array(
		'long_name' => '--info',
		'description' => 'Display metadata about a DVD',
		'action' => 'StoreTrue',
	));
	$parser->addOption('dump_iso', array(
		'short_name' => '-o',
		'long_name' => '--iso',
		'description' => 'Copy the DVD filesystem to an ISO',
		'action' => 'StoreTrue',
	));
	$parser->addOption('max', array(
		'short_name' => '-m',
		'long_name' => '--max',
		'description' => 'Max # of episodes to rip or encode',
		'action' => 'StoreInt',
	));
	$parser->addOption('wait', array(
		'short_name' => '-w',
		'long_name' => '--wait',
		'description' => 'Wait for media to be in the tray before proceeding',
		'action' => 'StoreTrue',
	));
	$parser->addOption('queue', array(
		'short_name' => '-q',
		'long_name' => '--queue',
		'description' => 'Display the episodes in the queue to be encoded',
		'action' => 'StoreTrue',
	));
	$parser->addOption('reset_queue', array(
		'long_name' => '--reset',
		'description' => 'Remove all episodes from the queue',
		'action' => 'StoreTrue',
	));
	$parser->addOption('skip', array(
		'short_name' => '-s',
		'long_name' => '--skip',
		'description' => 'Skip the number of episodes to rip or encode',
		'action' => 'StoreInt',
	));
	$parser->addOption('eject', array(
		'short_name' => '-t',
		'long_name' => '--eject',
		'description' => 'Eject the DVD drive when finished accessing it',
		'action' => 'StoreTrue',
	));
	$parser->addOption('eject_trays', array(
		'short_name' => '-j',
		'long_name' => '--eject-trays',
		'description' => 'Open all DVD trays',
		'action' => 'StoreTrue',
	));
	$parser->addOption('all', array(
		'short_name' => '-a',
		'long_name' => '--all',
		'description' => 'Run commands on all devices',
		'action' => 'StoreTrue',
	));
	$parser->addOption('ftp', array(
		'short_name' => '-f',
		'long_name' => '--ftp',
		'description' => 'FTP finished files',
		'action' => 'StoreTrue',
	));
	$parser->addOption('dumpvob', array(
		'long_name' => '--vob',
		'description' => 'Dump stream to .vob file',
		'action' => 'StoreTrue',
	));
	$parser->addOption('svn', array(
		'long_name' => '--svn',
		'description' => 'Use svn binaries where possible',
		'action' => 'StoreTrue',
	));
	$parser->addOption('close', array(
		'long_name' => '--close',
		'description' => 'Close DVD tray',
		'action' => 'StoreTrue',
	));

	
	$result = $parser->parse();
	
	extract($result->args);
	extract($result->options);
