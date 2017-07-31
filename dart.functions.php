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

	// Check source
	function is_dvd($device) {

		$arr_devices = array('/dev/bluray', '/dev/dvd', '/dev/sr0', '/dev/sr1');

		if(!in_array($device, $arr_devices) && pathinfo($device, PATHINFO_EXTENSION) != "iso")
			return false;

		return true;

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

	function safe_filename_title($str = 'Title') {

		$str = preg_replace("/[^A-Za-z0-9 \-,\.\?':!_]/", '', $str);
		$str = str_replace("/", "-", $str);
		return $str;

	}

	function get_dvd_iso_filename($source) {

		require_once 'class.dvd.php';
		require_once 'models/dvds.php';
		require_once 'models/series.php';

		$dvd = new DVD($source);
		$dvds_model = new Dvds_Model();
		$dvds_model->load_dvdread_id($dvd->dvdread_id);
		$series_model = new Series_Model($dvds_model->get_series_id());
		$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
		$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		$filename .= ".".$series_model->nsix;
		$filename .= ".iso";
		return $filename;

	}

	function rename_iso($source) {

		$dvd_iso_filename = get_dvd_iso_filename($source);
		$bool = true;

		if(!file_exists($dvd_iso_filename)) {
			$bool = rename($source, $dvd_iso_filename);
		}

		return $bool;

	}

	function get_episode_filename($episode_id, $container = 'mkv', $hardware = 'main') {

		require_once 'models/dvds.php';
		require_once 'models/tracks.php';
		require_once 'models/series.php';
		require_once 'models/episodes.php';

		$episodes_model = new Episodes_Model($episode_id);
		$episode_metadata = $episodes_model->get_metadata();

		$dvds_model = new Dvds_Model($episode_metadata['dvd_id']);
		$tracks_model = new Tracks_Model($episodes_model->track_id);
		$series_model = new Series_Model($episodes_model->get_series_id());

		switch($hardware) {

			case 'psp':
			case 'sansa':
			case 'vfat':
			$filename = $episode_metadata['series_title'];
			$filename .= " - ";
			$filename .= $episode_metadata['title'];
			if($episode_metadata['part']) {
				$filename .= ", Part ".$episode_metadata['part'];
			}
			$filename .= ".$container";
			break;

			default:
			$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
			$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
			$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
			$filename .= ".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
			$filename .= ".".$series_model->nsix;
			$filename .= ".$container";
			break;

		}

		return $filename;

	}

?>
