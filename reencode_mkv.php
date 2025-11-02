#!/usr/bin/env php
<?php

	error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);

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

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	foreach($filenames as $filename) {

		$filename = realpath($filename);
		$pathinfo = pathinfo($filename);

		if(!array_key_exists('extension', $pathinfo) || $pathinfo['extension'] != 'mkv')
			continue;

		$basename = basename($filename);
		$video = "v2-$basename";

		if(file_exists($video))
			continue;

		$arg_input_filename = escapeshellarg($filename);
		$arg_output_filename = escapeshellarg($video);

		$ffmpeg_opts = "-i $arg_input_filename -vcodec 'copy' -map 's:0?' -scodec 'copy' -acodec 'libfdk_aac' -vbr '5' -map_metadata '0' -y $arg_output_filename";

		if($opt_batch)
			$ffmpeg_opts = "-v quiet -stats $ffmpeg_opts";

		echo "ffmpeg $ffmpeg_opts\n";

	}

?>
