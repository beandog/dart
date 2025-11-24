#!/usr/bin/env php
<?php

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode More Options";
	$parser->addArgument('episodes', array('optional' => false, 'multiple' => true));
	$parser->addOption('opt_info', array(
		'long_name' => '--info',
		'description' => 'Display episode info',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_fetch', array(
		'long_name' => '--fetch',
		'description' => 'Fetch a copy of the current episode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_import', array(
		'long_name' => '--import',
		'description' => 'Import filesize and metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_json', array(
		'long_name' => '--json',
		'description' => 'Display episode info in JSON format',
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

	if($opt_info) {
		require_once 'config.local.php';
		require_once 'models/dbtable.php';
		require_once 'models/series.php';
		require_once 'models/episodes.php';
	}

	$opt_import = false;

	$i = 0;

foreach($episodes as $episode) {

	if(!file_exists($episode))
		goto next_episode;

	$valid_episode = false;

	$filename = realpath($episode);
	$pathinfo = pathinfo($filename);
	$basename = basename($filename);
	$episode = $basename;
	$arg_episode = escapeshellarg($filename);

	if(!array_key_exists('extension', $pathinfo))
		goto next_episode;

	$extension = $pathinfo['extension'];

	if(!in_array($extension, array('mkv', 'mpg', 'mp4')))
		goto next_episode;

	$arr = explode('.', $episode);

	$str_episode = '';

	// Check for 1.234.5678.9012.E-M-O.mkv
	if(is_numeric($episode[0]))
		$str_episode = current(explode('-', $episode));

	// Get episode from string prefix-foo-1.234.5678.9012.E-M-O.mkv
	if(!is_numeric($episode[0])) {

		$expression = "/\d\.\d{3}\.\d{4}\.\d{5}.*/";
		preg_match($expression, $episode, $arr_matches);

		if(!count($arr_matches))
			goto next_episode;

	}

	if(!strlen($str_episode))
		goto next_episode;

	$collection_id = $str_episode[0];
	$series_id = substr($str_episode, 2, 3);
	$dvd_id = substr($str_episode, 6, 4);
	$episode_id = substr($str_episode, 11, 5);
	$nsix = substr($str_episode, 17, 5);

	$valid_episode = true;
	$i++;

	// Get JSON
	if($opt_import || $opt_json) {
		$cmd_json = "mediainfo --Output=JSON $arg_episode | jq";
		$str_json = trim(shell_exec($cmd_json));
	}

	// Display JSON and quit
	if($opt_json) {
		echo "$str_json\n";
		goto next_episode;
	}

	// Build standardized filename
	$emo_filename = "$collection_id.$series_id.$dvd_id.$episode_id.$nsix.mkv";

	if($opt_info || $opt_import)  {

		$episodes_model = new Episodes_Model;
		$episodes_model->load($episode_id);
		$episode_title = $episodes_model->get_display_name();
		$episode_metadata = $episodes_model->get_metadata();

		extract($episode_metadata);

	}

	if($opt_info) { 

		$arr_d_info = array();
		// $arr_d_info[] = $basename;
		$arr_d_info[] = "$series_title";
		$d_season = '';
		if($season)
			$d_season = "s$season";
		if($episode_number)
			$d_season .= "e$episode_number";
		if($d_season)
			$arr_d_info[] = "$d_season";
		$arr_d_info[] = "$title";

		$d_title = implode(" : ", $arr_d_info);

		$filesize = filesize($filename);
		$mbs = number_format(ceil($filesize / 1048576));

		echo "# $basename - $d_title : ${mbs} MBs\n";

	}

	if($opt_fetch) {

		$hostname = gethostname();
		if($hostname == "dlna" || $hostname == "dlna.beandog.org")
			goto next_episode;

		if($basename == $emo_filename)
			goto next_episode;

		if(file_exists($emo_filename)) {
			echo "# $emo_filename exists, skipping\n";
			goto next_episode;
		}

		echo "# Fetching $emo_filename ...\n";
		$cmd = "scp dlna:/opt/plex/*/$emo_filename .";
		passthru($cmd);

	}

	next_episode:

}

