<?php

	// Check source - helper function
	function is_dvd($device) {

		if(get_disc_type($device) == 'dvd')
			return true;

		return false;

	}

	// Helper function
	function is_bluray($device) {

		if(get_disc_type($device) == 'bluray')
			return true;

		return false;

	}

	function get_disc_type($source) {

		$source = realpath($source);

		if(is_dir($source) && is_dir("$source/VIDEO_TS"))
			return 'dvd';

		if(is_dir($source) && is_dir("$source/BDMV"))
			return 'bluray';

		$arg_device = escapeshellarg($source);

		$return = 0;

		if(substr($source, 0, 5) == '/dev/') {

			$command = "udevadm info $arg_device";
			exec($command, $arr, $return);

			if(in_array("E: ID_CDROM_MEDIA_DVD=1", $arr))
				return 'dvd';

			elseif(in_array("E: ID_CDROM_MEDIA_BD=1", $arr))
				return 'bluray';

		}

		$command = "/usr/local/bin/disc_type $arg_device 2> /dev/null";
		exec($command, $arr, $return);

		$str = current($arr);

		if($str == 'dvd' || $str == 'bluray')
			return $str;

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

	function beep_error() {

		system("beep -f 1000 -n -f 2000 -n -f 1500 -n -f 1750 -n f 1750 -n -f 1750");

	}

	function safe_filename_title($str = 'Title') {

		$str = preg_replace("/[^A-Za-z0-9 \-,\.\?':!_]/", '', $str);
		$str = str_replace("/", "-", $str);
		return $str;

	}

	function get_dvd_iso_filename($source) {

		$disc_type = get_disc_type($source);

		require_once 'class.dvd.php';
		require_once 'models/dvds.php';
		require_once 'models/series.php';

		$dvds_model = new Dvds_Model();

		if($disc_type == 'dvd') {

			$dvd = new DVD($source);
			if(is_null($dvd->dvd_info))
				return '';
			$dvdread_id = $dvd->dvdread_id;

		} elseif($disc_type == 'bluray') {

			require_once 'class.bluray.php';
			require_once 'models/blurays.php';

			$bluray = new Bluray($source);
			$blurays_model = new Blurays_Model();
			$dvdread_id = $bluray->dvdread_id;
			$blurays_model->load_dvdread_id($dvdread_id);

			if(!$blurays_model->id) {
				$filename = "$dvdread_id.iso";
				return $filename;
			}

		} else {

			return '';

		}

		$dvds_model->load_dvdread_id($dvdread_id);
		if(!$dvds_model->id) {
			$filename = "$dvdread_id.iso";
			return $filename;
		}
		$series_id = $dvds_model->get_series_id();
		if(!$series_id) {
			$filename = "$dvdread_id.iso";
			return $filename;
		}
		$series_model = new Series_Model($series_id);
		$nsix = $series_model->nsix;
		if(!$nsix)
			$nsix = 'NSIX';
		$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
		$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		$filename .= ".$nsix";
		$filename .= ".iso";
		return $filename;

	}

	function rename_iso($source) {

		$dvd_iso_filename = get_dvd_iso_filename($source);
		$bool = true;
		// PHP will complain if source is a directory, so make it look like a file
		$source = dirname($source)."/".basename($source);

		if(!file_exists($dvd_iso_filename)) {
			$bool = rename($source, $dvd_iso_filename);
		}

		return $bool;

	}

	function get_episode_filename($episode_id, $container = 'mkv') {

		require_once 'models/dvds.php';
		require_once 'models/tracks.php';
		require_once 'models/series.php';
		require_once 'models/episodes.php';

		$episodes_model = new Episodes_Model($episode_id);
		$episode_metadata = $episodes_model->get_metadata();

		$dvds_model = new Dvds_Model($episode_metadata['dvd_id']);
		$tracks_model = new Tracks_Model($episodes_model->track_id);
		$series_model = new Series_Model($episodes_model->get_series_id());

		$filename = str_pad($dvds_model->get_collection_id(), 1, '0');
		$filename .= ".".str_pad($dvds_model->get_series_id(), 3, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
		$filename .= ".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
		$filename .= ".".$series_model->nsix;
		$filename .= ".$container";

		return $filename;

	}

?>
