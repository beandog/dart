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
	$parser->addOption('opt_remux', array(
		'long_name' => '--remux',
		'description' => 'Encode audio track and remux with mkvmerge',
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
		$opt_mkv = true;

	if($opt_remux) {
		$opt_mkv = false;
		$opt_mp4 = false;
	}

	foreach($filenames as $filename) {

		$filename = realpath($filename);
		$pathinfo = pathinfo($filename);

		if(!array_key_exists('extension', $pathinfo) || $pathinfo['extension'] != 'mkv')
			continue;

		$basename = basename($filename, '.mkv');
		if($opt_mp4)
			$video = "v2-$basename.mp4";
		elseif($opt_mkv || $opt_remux)
			$video = "v2-$basename.mkv";
		$audio = "v2-$basename.aac";

		if(file_exists($video))
			continue;

		$arg_input_filename = escapeshellarg($filename);
		$arg_output_filename = escapeshellarg($video);
		$arg_aac_filename = escapeshellarg($audio);

		// ffmpeg arguments:
		// - re-encode audio to AAC with same channels as source and highest VBR settings
		// - remove subtitles
		// - remove chapters (drop metadata) because ffprobe is complaining about them
		// - set languages to English (which is lost when dropping metadata)

		if($opt_mp4)
			$ffmpeg_opts = "-i $arg_input_filename -map_chapters '-1' -vcodec 'copy' -acodec 'libfdk_aac' -vbr '5' -sn -map_metadata '0' -movflags '+faststart' -y $arg_output_filename";

		if($opt_mkv)
			$ffmpeg_opts = "-i $arg_input_filename -vcodec 'copy' -acodec 'libfdk_aac' -vbr '5' -sn -map_metadata '0' -y $arg_output_filename";

		if($opt_remux) {
			$ffmpeg_opts = "-i $arg_input_filename -vn -acodec 'libfdk_aac' -vbr '5' -sn -map_chapters '-1' -map_metadata '-1' -metadata:s 'language=eng' -y $arg_aac_filename";
		}
		if($opt_batch)
			$ffmpeg_opts = "-v quiet -stats $ffmpeg_opts";

		if(!$opt_remux || ($opt_remux && !file_exists($audio)))
			echo "ffmpeg $ffmpeg_opts\n";

		if($opt_remux) {

			$mkvmerge_cmd = "mkvmerge -o $arg_output_filename -A -S $arg_input_filename $arg_aac_filename";
			echo "$mkvmerge_cmd\n";

		}

	}

?>
