<?php

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

	function beep_error() {

		system("beep -f 1000 -n -f 2000 -n -f 1500 -n -f 1750 -n f 1750 -n -f 1750");

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

	function get_episode_filenames($source) {

		$arg_source = escapeshellarg(realpath($source));
		exec("disc_type $arg_source 2> /dev/null", $output, $retval);

		$disc_type = current($output);

		if($disc_type == 'dvd') {

			require_once 'class.dvd.php';
			require_once 'models/dvds.php';

			$dvds_model = new Dvds_Model();
			$dvd = new DVD($source);
			if(is_null($dvd->dvd_info))
				return array();

		} elseif($disc_type == 'bluray') {

			require_once 'class.bluray.php';
			require_once 'models/blurays.php';

			$dvd = new Bluray($source);
			$dvds_model = new Blurays_Model();
			$dvdread_id = $dvd->dvdread_id;

		} else {

			return array();

		}

		$dvdread_id = $dvd->dvdread_id;

		$dvds_model->load_dvdread_id($dvdread_id);

		if(!$dvds_model->id)
			return array();

		$arr_episodes = $dvds_model->get_episodes(false);

		$filenames = array();

		foreach($arr_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);

			$filenames[] = $episodes_model->get_filename();

		}

		return $filenames;

	}

?>
