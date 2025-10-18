#!/usr/bin/env php
<?php

	require_once 'config.local.php';

	require_once 'dart.functions.php';
	require_once 'class.dvd.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/tracks.php';
	require_once 'models/series.php';

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode Query Tool";
	$parser->addArgument('filenames', array('required' => true, 'multiple' => true));
	$parser->addOption('opt_dirname', array(
		'long_name' => '--dirname',
		'description' => '\'Series Name (Year)/Season XX/\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_filename', array(
		'long_name' => '--filename',
		'description' => '\'Series Name - sXXeXX\' (default)',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_verbose', array(
		'long_name' => '--verbose',
		'short_name' => '-v',
		'description' => '\'Series Name - sXXeXX - Episode Title\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_jfin', array(
		'long_name' => '--jfin',
		'description' => '\'Series Name (Year)/\'',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_codec_info', array(
		'long_name' => '--codec-info',
		'description' => 'Display audio and video codecs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_reencode_info', array(
		'long_name' => '--reencode-info',
		'description' => 'Display reencode to MP4 info',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_time', array(
		'long_name' => '--time',
		'description' => 'Log time of encode',
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

	function safe_filename_title($str = 'Title') {

		$str = preg_replace("/[^A-Za-z0-9 \-,\.\?':!_]/", '', $str);
		$str = str_replace("/", "-", $str);
		return $str;

	}

	/** Start everything **/

	start:

	$source = array_shift($filenames);
	$arg_source = escapeshellarg($source);

	$filename = $source;

	if(!file_exists($filename)) {
		echo "File doesn't exist '$filename'\n";
		exit;
	}

	$realpath = realpath($filename);
	$pathinfo = pathinfo($realpath);
	$movie = false;
	if(substr($pathinfo['basename'], 0, 1) == '3' || substr($pathinfo['basename'], 0, 1) == '4' || substr($pathinfo['basename'], 0, 1) == '5' || substr($pathinfo['basename'], 0, 1) == '8' || substr($pathinfo['basename'], 0, 1) == '9')
		$movie = true;
	$str_elements = explode('.', $pathinfo['basename']);

	if(count($str_elements) < 3 || !file_exists($realpath) || !(in_array($pathinfo['extension'], array('mkv', 'mp4')))) {
		goto next_episode;
	}

	// If giving an ISO, just want the series title name
	if($pathinfo['extension'] == 'iso') {
		$series_id = intval($str_elements[1]);
		$series_model = new Series_Model($series_id);
		$series_title = $series_model->title;
		if(!strlen($series_title)) {
			echo "DELETED-SERIES-".$pathinfo['filename']."\n";
			exit;
		}
		$safe_title = safe_filename_title($series_title);
		$safe_title = preg_replace('/^The /', '', $safe_title);
		$safe_title = str_replace(' ', '-', $safe_title);
		$safe_title = str_replace(':', '', $safe_title);
		$safe_title = str_replace('!', '', $safe_title);
		$safe_title = str_replace('?', '', $safe_title);
		$safe_title = str_replace(',', '', $safe_title);
		echo "$safe_title\n";
		exit;
	}

	if($opt_verbose || $opt_codec_info)
		echo "$source ";

	if($opt_codec_info) {
		$d_vcodec = exec("mediainfo $arg_source --Output=JSON 2> /dev/null | jq -M -r '.media.track[1].CodecID'");
		$d_acodec = exec("mediainfo $arg_source --Output=JSON 2> /dev/null | jq -M -r '.media.track[2].CodecID'");
		$d_codecs = "$d_vcodec $d_acodec";
		echo "$d_codecs\n";
		goto next_episode;
	}

	if($opt_reencode_info && $pathinfo['extension'] == 'mkv') {

		$mp4 = str_replace(".mkv", ".mp4", $source);
		$arg_mp4 = escapeshellarg($mp4);

		if(file_exists($mp4))
			goto next_episode;

		$cmd = "ffmpeg -i $arg_source -vcodec 'copy' -acodec 'libfdk_aac' -vbr '5' -scodec 'copy' -map_metadata '0' -movflags '+faststart' -y $arg_mp4";

		if($opt_time)
			$cmd = "tout $cmd";

		echo "$cmd\n";

		goto next_episode;

	}

	$episode_query = array();
	$episode_id = intval($str_elements[3]);
	$episodes_model = new Episodes_Model($episode_id);

	if(!$episodes_model) {
		goto next_episode;
	}

	// If no options passed, simply pass the filename
	if(!$opt_dirname && !$opt_filename)
		$opt_filename = true;

	$episode_metadata = $episodes_model->get_metadata();

	// If season is set as '100' in database, it's flagged as as 'Special' for Plex to pick up
	if($episode_metadata['season'] == 100)
		$episode_metadata['season'] = 0;

	// An episode can override series title if it is in format (Series Title)
	if(substr($episode_metadata['title'], 0, 1) == "(") {


		$episode_metadata['series_title'] = substr($episode_metadata['title'], 1, strpos($episode_metadata['title'], ")"));

	}

	$series_dirname = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['series_title']);

	$filename = $series_dirname;

	if($episode_metadata['production_year']) {
		$series_dirname .= " ";
		$series_dirname .= "(".$episode_metadata['production_year'].")";
	}

	$season_dirname = "Season ";
	$season_dirname .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);

	$season_filename = " - ";
	$season_filename .= "s";
	$season_filename .= str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);
	$season_filename .= "e";
	$episode_number = $episodes_model->get_number();
	$season_filename .= str_pad($episode_number, 2, 0, STR_PAD_LEFT);

	$episode_title = preg_replace("/[^0-9A-Za-z \-_.]/", '', $episode_metadata['title']);

	$series_jfin = $episode_metadata['jfin'];

	if($movie) {

		$filename = $episode_title;

	} else {

		$filename .= $season_filename;
		if(($opt_verbose)) {
			$filename .= " - ";
			$filename .= $episode_title;
		}
	}

	$filename .= ".".$pathinfo['extension'];
	$episode_title .= ".".$pathinfo['extension'];

	if($opt_jfin) {

		if($opt_dirname) {
			if($series_jfin)
				echo $series_dirname." [tvdbid-$series_jfin]/";
			else
				echo "$series_dirname/";
		}
		if(!$movie && $opt_dirname)
			echo $season_dirname."/";
		if($opt_filename)
			echo $filename;

	} else {

		if($opt_dirname)
			echo $series_dirname."/";
		if(!$movie && $opt_dirname)
			echo $season_dirname."/";
		if($opt_filename)
			echo $filename;

	}

	echo "\n";

	// The ghosts of monolithic code haunt me. :)
	next_episode:
	if(count($filenames))
		goto start;
