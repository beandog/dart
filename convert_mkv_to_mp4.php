#!/usr/bin/php
<?php

	if($argc == 1)
		exit(1);
	
	$arr_files = array_slice($argv, 1);

	foreach($arr_files as $mkv) {

		$mkv = realpath($mkv);

		if(!file_exists($mkv))
			continue;

		$pathinfo = pathinfo($mkv);

		if($pathinfo['extension'] != 'mkv' || $pathinfo['extension'] == 'mp4')
			continue;

		$mp4 = $pathinfo['dirname'].'/'.$pathinfo['filename'].'.mp4';

		if(file_exists($mp4))
			continue;

		$cmd = "avconv -y -i ".escapeshellarg($mkv)." -vcodec copy -acodec copy -scodec copy -map 0 ".escapeshellarg($mp4);

		exec($cmd, $output, $retval);

		if($retval) {
			$cmd = "avconv -y -i ".escapeshellarg($mkv)." -vcodec copy -acodec copy -sn ".escapeshellarg($mp4);
			exec($cmd, $output, $retval);
		}
	
	}
