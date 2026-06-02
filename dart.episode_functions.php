<?php

	// Standalone file to get metadata about an episode

	require_once 'models/dbtable.php';
	require_once 'models/episodes.php';

	function get_epix($season, $episode_number) {

		$str = 's' . str_pad($season, 2, '0', STR_PAD_LEFT) . 'e' . str_pad($episode_number, 2,'0', STR_PAD_LEFT);

		return $str;

	}

	function episode_details($arr_metadata) {

		extract($arr_metadata);

		/*
		if(file_exists($episode_filename))
			$rip_status = '@';
		else
			$rip_status = '!';
		*/

		if($episode_part)
			$episode_title .= ", Part $episode_part";

		$d_epix = get_epix($season, $episode_number);

		$arr_d_details = array("[ $episode_filename ] $d_epix - $series_title", $episode_title);

		$d_details = implode(' : ', $arr_d_details);

		return $d_details;

	}

