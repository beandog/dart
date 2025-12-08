<?php

	declare(ticks = 1);

	function signal_handler($signal) {
		return;
	}

	pcntl_signal(SIGINT, "signal_handler");

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

		// FIXME pull this from dvds_model
		$series_id = $dvds_model->get_series_id();
		if(!$series_id) {
			$filename = "0.000";
			$filename .= ".".str_pad($dvds_model->id, 4, '0', STR_PAD_LEFT);
			$filename .= ".NSIX.iso";
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
