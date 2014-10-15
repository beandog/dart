<?php

	// Capture SIGINT
	declare(ticks = 1);
	function master_crash($signo) {
		echo "\n";
		echo "Captured SIGINT, oh noes!!\n";
		echo "Crashing gracefully ... *boom*\n";
		exit(1);
	}
	pcntl_signal(SIGINT, 'master_crash');

	// Switch to the next device
	function toggle_device($all, $current) {

		$current_key = array_search($current, $all);
		if(array_key_exists($current_key + 1, $all))
			return $all[$current_key + 1];
		else
			return $all[0];

	}

	// Human-friendly output
	function d_yes_no($var) {
		if($var)
			return "yes";
		else
			return "no";
	}

	function beep_error() {

		system("beep -f 1000 -n -f 2000 -n -f 1500 -n -f 1750 -n f 1750 -n -f 1750");

	}

	function display_audio($codec, $channels) {

		if($codec == 'ac3')
			$str = "Dolby Digital ";
		else
			$str = "DTS ";

		switch($channels) {

			case 1:
				$str .= "Mono";
				break;
			case 2:
				$str .= "Stereo";
				break;
			case 3:
				$str .= "Stereo 2.1";
				break;
			case 4:
				$str .= "Surround Sound 4.0";
				break;
			case 5:
				$str .= "Surround Sound 5.0";
				break;
			case 6:
				$str .= "Surround Sound 5.1";
				break;
		}

		return $str;

	}

	function safe_filename_title($str = 'Title', $underlines = false) {

		$str = preg_replace("/[^A-Za-z0-9 -_]/", '', $str);
		$str = str_replace("/", "-", $str);
		if($underlines)
			$str = str_replace(' ', '_', $str);
		return $str;

	}

?>
