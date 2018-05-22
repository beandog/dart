#!/usr/bin/php
<?php

	if($argc == 1)
		return 1;

	array_shift($argv);

	$filenames = $argv;

	foreach($filenames as $filename) {

		$filename = realpath($filename);
		$basename = basename($filename);
		$arr_exec = array('mediainfo', escapeshellarg($filename));
		$arr_output = array();
		$retval = 1;

		$str_exec = implode(' ', $arr_exec);

		$mediainfo_out = exec($str_exec, $arr_output, $retval);

		if($retval)
			continue;

		$arr_options = array();
		$mediainfo = array();
		$formats = array();

		foreach($arr_output as $line) {

			$line = trim($line);

			if(strlen($line) == 0 || strpos($line, ':') === false)
				continue;

			$arr = explode(' : ', $line);

			$key = array_shift($arr);
			$key = trim($key);
			$value = implode(' : ', $arr);
			$value = trim($value);

			if($key == 'Format')
				$formats[] = $value;
			else
				$mediainfo[$key] = $value;

		}

		$container = $formats[0];
		$video_codec = $formats[1];

		if($video_codec != 'HEVC')
			continue;

		$encoding_settings = $mediainfo['Encoding settings'];
		$arr_encoding_settings = explode('/', $encoding_settings);

		foreach($arr_encoding_settings as $str) {

			$str = trim($str);

			if(strlen($str) == 1)
				continue;

			if(strpos($str, '=') !== false) {
				$arr = explode('=', $str);
				$codec[$arr[0]] = $arr[1];
			} else {
				$codec[$str] = 1;
			}

		}

		// Start at baseline
		$preset = 'ultrafast';

		if($codec['min-cu-size'] == 8 && $codec['rc-lookahead'] == 10)
			$preset = 'superfast';

		if($codec['bframes'] == 4 && $codec['rc-lookahead'] == 15)
			$preset = 'veryfast';

		if($codec['subme'] == 2)
			$preset = 'faster';

		if($codec['ref'] == 3 && $codec['no-early-skip'] == 1)
			$preset = 'fast';

		if($codec['b-adapt'] == 2 && $codec['rd'] == 3)
			$preset = 'medium';

		if($codec['ref'] == 4 && $codec['subme'] == 3)
			$preset = 'slow';

		if($codec['bframes'] == 8 && $codec['rc-lookahead'] == 30 && $codec['limit-refs'] == 2)
			$preset = 'slower';

		if($codec['rc-lookahead'] == 40)
			$preset = 'veryslow';

		if($codec['rc-lookahead'] == 60)
			$preset = 'placebo';

		echo "$basename: $video_codec $preset\n";

	}
