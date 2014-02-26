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
	function formatTitle($str = 'Title', $underlines = true) {
		$str = preg_replace("/[^A-Za-z0-9 \-,.?':!_]/", '', $str);
		$underlines && $str = str_replace(' ', '_', $str);
		return $str;
	}

	function get_episode_filename($episode_id) {

		// Class instatiation
		$episodes_model = new Episodes_Model($episode_id);
		$episode_title = $episodes_model->title;
		$track_id = $episodes_model->track_id;
		$episode_number = $episodes_model->get_number();
		$display_episode_number = str_pad($episode_number, 2, 0, STR_PAD_LEFT);
		$episode_part = $episodes_model->part;
		$episode_season = $episodes_model->get_season();
		$series_model = new Series_Model($episodes_model->get_series_id());
		$episode_prefix = '';
		$episode_suffix = '';

		// FIXME Take into account 10+seasons
		if($series_model->indexed == 't') {
			if(!$episode_season)
				$display_season = 1;
			else
				$display_season = $episode_season;

			$episode_prefix = "${display_season}x${display_episode_number}._";
		}

		$series_model = new Series_Model($episodes_model->get_series_id());
		$series_title = $series_model->title;
		$series_dir = formatTitle($series_title)."/";

		if($episode_part)
			$episode_suffix = ", Part $episode_part";

		/** Filenames **/
		$episode_filename = $series_dir.formatTitle($episode_prefix.$episode_title.$episode_suffix);

		return $episode_filename;

	}

	// Switch to the next device
	function toggle_device($device) {
		if($device == '/dev/dvd')
			return '/dev/dvd1';
		if($device == '/dev/dvd1')
			return '/dev/dvd2';
		if($device == '/dev/dvd2')
			return '/dev/dvd';
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
			shell::msg("execution died: $str");
			die($return);
		} else
			return $arr;

	}
?>
