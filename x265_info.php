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
			return 1;

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
		$codec = $formats[1];

		$encoding_settings = $mediainfo['Encoding settings'];
		$arr_encoding_settings = explode('/', $encoding_settings);

		foreach($arr_encoding_settings as $str) {

			$str = trim($str);

			if(strlen($str) == 1)
				continue;

			if(strpos($str, '=') !== false) {
				$arr = explode('=', $str);
				$x265[$arr[0]] = $arr[1];
			} else {
				$x265[$str] = 1;
			}

		}

		// Start at baseline
		$preset = 'ultrafast';

		if($x265['min-cu-size'] == 8 && $x265['rc-lookahead'] == 10)
			$preset = 'superfast';

		if($x265['bframes'] == 4 && $x265['rc-lookahead'] == 15)
			$preset = 'veryfast';

		if($x265['subme'] == 2)
			$preset = 'faster';

		if($x265['ref'] == 3 && $x265['no-early-skip'] == 1)
			$preset = 'fast';

		if($x265['b-adapt'] == 2 && $x265['rd'] == 3)
			$preset = 'medium';

		if($x265['ref'] == 4 && $x265['subme'] == 3)
			$preset = 'slow';

		if($x265['bframes'] == 8 && $x265['rc-lookahead'] == 30 && $x265['limit-refs'] == 2)
			$preset = 'slower';

		if($x265['rc-lookahead'] == 40)
			$preset = 'veryslow';

		if($x265['rc-lookahead'] == 60)
			$preset = 'placebo';

		echo "$basename: $preset\n";

	}
