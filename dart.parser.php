<?
	
	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "DVD Archiving Tool";
	$parser->addArgument('device', array('optional' => true));
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
	$parser->addOption('encode', array(
		'short_name' => '-e',
		'long_name' => '--encode',
		'description' => 'Encode episodes in the queue',
		'action' => 'StoreTrue',
	));
	$parser->addOption('info', array(
		'short_name' => '-i',
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
	$parser->addOption('mount', array(
		'short_name' => '-n',
		'long_name' => '--mount',
		'description' => 'Mount the file if it is a device',
		'action' => 'StoreTrue',
	));
	$parser->addOption('poll', array(
		'short_name' => '-p',
		'long_name' => '--poll',
		'description' => 'Continue to monitor the drive after ripping, and the queue after encoding',
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
	$parser->addOption('rip', array(
		'short_name' => '-r',
		'long_name' => '--rip',
		'description' => 'Rip the episodes from a DVD device or ISO',
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
	
	$result = $parser->parse();
	
	extract($result->args);
	extract($result->options);