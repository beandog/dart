#!/usr/bin/env php
<?php

	require 'config.local.php';

	require_once 'Console/CommandLine.php';
	$parser = new Console_CommandLine();
	$parser->description = "Re-encode MKV to MP4 with AAC audio";
	$parser->addArgument('filenames', array('optional' => true, 'multiple' => true));
	$parser->addOption('opt_batch', array(
		'short_name' => '-b',
		'long_name' => '--batch',
		'description' => 'Only display encoding progress',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_mkv', array(
		'long_name' => '--mkv',
		'description' => 'Use Matroska container',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_mp4', array(
		'long_name' => '--mp4',
		'description' => 'Use MPEG4 container',
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

	if(!$opt_mkv && !$opt_mp4)
		$opt_mp4 = true;

	foreach($filenames as $filename) {

		$filename = realpath($filename);
		$pathinfo = pathinfo($filename);

		if(!array_key_exists('extension', $pathinfo) || $pathinfo['extension'] != 'mkv')
			continue;

		$basename = basename($filename, '.mkv');
		if($opt_mp4)
			$video = "v2-$basename.mp4";
		else
			$video = "v2-$basename.mkv";

		if(file_exists($video))
			continue;

		$arg_input_filename = escapeshellarg($filename);
		$arg_output_filename = escapeshellarg($video);

		// ffmpeg arguments:
		// - re-encode audio to AAC with same channels as source and highest VBR settings
		// - remove subtitles
		// - remove chapters (drop metadata) because ffprobe is complaining about them
		// - set languages to English (which is lost when dropping metadata)

		if($opt_mp4)
			$ffmpeg_opts = "-i $arg_input_filename -map_chapters '-1' -vcodec 'copy' -acodec 'libfdk_aac' -vbr '5' -sn -map_metadata '0' -movflags '+faststart' -y $arg_output_filename";

		if($opt_mkv)
			$ffmpeg_opts = "-i $arg_input_filename -vcodec 'copy' -acodec 'libfdk_aac' -vbr '5' -sn -map_metadata '0' -y $arg_output_filename";

		if($opt_batch)
			$ffmpeg_opts = "-v quiet -stats $ffmpeg_opts";

		echo "ffmpeg $ffmpeg_opts\n";

	}

?>
