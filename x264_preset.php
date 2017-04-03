#!/usr/bin/php
<?php

	function display_syntax($argc, $argv) {
		echo "Syntax: ".basename($argv[0])." filename [filename] [...]\n";
	}

	if($argc < 2) {
		display_syntax($argc, $argv);
		exit(1);
	}

	if(!file_exists($argv[1])) {
		echo "Could not find filename ".$argv[1]."\n";
		exit(1);
	}

	require_once 'class.mediainfo.php';

	$filenames = $argv;
	array_shift($filenames);

	foreach($filenames as $filename) {

		$filename = realpath($filename);
		$display_name = basename($filename);

		if(is_dir($filename))
			continue;

		$mediainfo = new MediaInfo($filename);

		if($mediainfo === false)
			continue;

		$x264_preset = $mediainfo->x264_preset;
		if(!strlen($x264_preset))
			continue;

		echo "$display_name: ";
		echo $x264_preset;
		echo "\n";

	 }
