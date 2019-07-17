#!/usr/bin/php
<?php

	require_once 'config.local.php';
	require_once 'inc.mdb2.php';
	require_once 'models/dbtable.php';
	require_once 'models/episodes.php';

	$pts_files = $argv;

	array_shift($pts_files);

	$count = 0;

	foreach($pts_files as $pts_file) {

		$realpath = realpath($pts_file);
		$pathinfo = pathinfo($realpath);
		$basename = $pathinfo['basename'];
		$str_elements = explode('.', $pathinfo['basename']);
		if(count($str_elements) < 3 || !file_exists($realpath) || $pathinfo['extension'] != 'pts')
			continue;

		$episode_id = intval($str_elements[3]);
		$episodes_model = new Episodes_Model($episode_id);

		if(!$episodes_model)
			continue;

		$fd = fopen($realpath, 'r');

		if($fd === false)
			continue;

		echo "$basename: ";

		$progressive = 0;
		$top_field = 0;
		$bottom_field = 0;
		$crop = '';

		$arr_frame = array('n' => null);
		$arr_frames = array();

		$arr_crop_count = array();

		while($str = fgets($fd)) {

			if($str === false)
				continue;

			$str = trim($str);

			$str = preg_replace('/:\s+/', ':', $str);

			// showinfo
			if(substr($str, 1, 15) == 'Parsed_showinfo') {

				preg_match_all('/(n:\d+|pts:\d+|pts_time:\d+|pos:\-?\d+|i:\w|type:\w)/', $str, $matches);

				$arr = current($matches);
				if(count($arr) != 6)
					continue;

				$arr_frame = array('n' => null, 'pts' => null, 'pts_time' => null, 'pos' => null, 'i' => '', 'type' => '', 'pblack' => null, 'crop' => '');

				foreach($arr as $value) {

					$arr_vars = explode(':', $value);
					$arr_frame[$arr_vars[0]] = $arr_vars[1];

				}

				if($arr_frame['i'] == 'P')
					$progressive++;
				elseif($arr_frame['i'] == 'T')
					$top_field++;
				elseif($arr_frames['i'] == 'B')
					$bottom_field++;

				$count++;

			}

			// blackframe
			if(substr($str, 1, 17) == 'Parsed_blackframe') {

				// preg_match_all('/(frame:\d+|pblack:\d+|pts:\d+)/', $str, $matches);
				preg_match_all('/pblack:\d+/', $str, $matches);
				$arr = current($matches);

				$arr_pblack = explode(':', current($arr));

				$arr_frame['pblack'] = $arr_pblack[1];

			}

			// cropdetect
			if(substr($str, 1, 17) == 'Parsed_cropdetect') {

				$arr = explode('=', $str);
				$crop_value = end($arr);
				// $arr_frame['crop'] = $crop_value;

				if($crop_value && (strpos($crop_value, '-') === false))
					@$arr_crop_count[$crop_value]++;


			}

		}

		arsort($arr_crop_count);
		$crop = current(array_keys($arr_crop_count));

		$episodes_model->progressive = $progressive;
		$episodes_model->top_field = $top_field;
		$episodes_model->bottom_field = $bottom_field;
		$episodes_model->crop = $crop;

		echo "progressive: $progressive top-field: $top_field bottom-field: $bottom_field\n";

	}
