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
?>
