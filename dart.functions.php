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

	function stdout($str) {
		$int = fwrite(STDOUT, "$str\n");
		if($int === false) {
			echo "stdout() failed on fwrite\n";
			echo "original string: $str\n";
		}
	}

	function stderr($str) {
		$int = fwrite(STDERR, "$str\n");
		if($int === false) {
			echo "stderr() failed on fwrite\n";
			echo "original string: $str\n";
		}
	}

	/**
	 * Format a title for saving to filesystem
	 *
	 * @param string original title
	 * @return new title
	 */
	function formatTitle($str = 'Title', $underlines = false) {
		$str = preg_replace("/[^A-Za-z0-9 \-,.?':!_]/", '', $str);
		if($underlines)
			$str = str_replace(' ', '_', $str);
		return $str;
	}

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

	/** imported from class.shell.php **/

	/**
	* Execute shell scripts
	*
	* @param string execution string
	* @param boolean drop stderr to /dev/null
	* @param boolean ignore exit codes
	* @param array exit codes that indicate success
	* @return output array
	*/
	function command($str, $stderr_to_null = true, $ignore_exit_code = false, $passthru = false, $arr_successful_exit_codes = array(0)) {

		$arr = array();

		if($stderr_to_null)
			$exec = "$str 2> /dev/null";
		else
			$exec =& $str;

		if($passthru)
			passthru($exec, $return);
		else
			exec($exec, $arr, $return);

		if(!in_array($return, $arr_successful_exit_codes) && !$ignore_exit_code) {
			echo "execution died: $str\n";
			die($return);
		} else
			return $arr;

	}
?>
