<?php

	// Allow cleanly killing running encodes
	pcntl_async_signals(true);

	function sig_handler($signo) {

		if($signo == SIGTERM)
			exit;

		if($signo != SIGINT)
			return;

		global $dart_status;

		if($dart_status == 'encode_episode') {

			global $filename;

			$arg_filename = escapeshellarg($filename);
			echo "* Removing $arg_filename\n";
			if(file_exists($filename))
				unlink($filename);

		}

		echo "Goodbye!\n";
		posix_kill(posix_getpid(), SIGUSR1);

		exit;

	}

	pcntl_signal(SIGINT, "sig_handler");

	function get_dvd_iso_filename($source) {

		$disc_type = get_disc_type($source);

		require_once 'class.dvd.php';
		require_once 'models/pdo.dvds.php';
		require_once 'models/pdo.series.php';

		$dvds_model = new Dvds_Model();

		if($disc_type == 'dvd') {

			$dvd = new DVD($source);
			if(is_null($dvd->dvd_info))
				return '';
			$dvdread_id = $dvd->dvdread_id;

		} elseif($disc_type == 'bluray') {

			require_once 'class.bluray.php';
			require_once 'models/pdo.blurays.php';

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
			require_once 'models/pdo.dvds.php';

			$dvds_model = new Dvds_Model();
			$dvd = new DVD($source);
			if(is_null($dvd->dvd_info))
				return array();

		} elseif($disc_type == 'bluray') {

			require_once 'class.bluray.php';
			require_once 'models/pdo.blurays.php';

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

	function get_episode_titles($series_title, $episode_title, $episode_part, $provider_id) {

		$arr_episode_titles = array(
			'series_title' => $series_title,
			'episode_title' => $episode_title,
			'episode_part' => $episode_part,
			'provider_id' => $provider_id,
			'display_title' => '',
		);

		if(str_contains($episode_title, '(') && str_contains($episode_title, ')')) {

			$arr = explode(')', $episode_title);
			$str = current($arr);
			$episode_title = end($arr);
			$episode_title = trim($episode_title);

			$str = substr($str, 1);

			$series_title = $str;

			if(str_contains($series_title, '|')) {

				$arr = explode('|', $series_title);

				$series_title = current($arr);
				$provider_id = end($arr);

			}

			$arr_episode_titles['series_title'] = $series_title;
			$arr_episode_titles['episode_title'] = $episode_title;

		}

		$display_title = "$series_title: ";

		if($episode_title)
			$display_title .= "$episode_title";

		if($episode_part)
			$display_title .= ", Part $episode_part";

		$arr_episode_titles['display_title'] = $display_title;

		return $arr_episode_titles;

	}

	// File_Find class from PEAR
	// https://pear.php.net/package/File_Find

	require_once 'pear/File/Find.php';
	$file_find = new File_Find;

	// Get DVD total filesize, rounding up to megabytes, so unless it's not a file it will always be 1+
	function dvd_filesize($device) {

		if(!file_exists($device))
			return 0;

		$dvd_filename = realpath($device);

		$bytes = 0;
		$megabytes = 0;

		if(is_file($dvd_filename))
			$bytes = filesize($dvd_filename);

		if(dirname($dvd_filename) == '/dev') {

			$str = shell_exec("udfinfo $dvd_filename 2> /dev/null");
			$str = trim($str);

			$arr = explode("\n", $str);
			$arr = preg_grep('/^blocks=/', $arr);
			$str = current($arr);

			$blocks = substr($str, 7);
			$blocks = intval($blocks);
			$bytes = $blocks * 2048;

		}

		if(is_dir($dvd_filename)) {

			global $file_find;

			$arr_maptree = $file_find->maptree($dvd_filename);

			if(!is_array($arr_maptree))
				return 0;

			$arr_filenames = $arr_maptree[1];

			$bytes = 0;
			foreach($arr_filenames as $filename) {

				$filename = realpath($filename);

				// Look for strange anomalies where the file doesn't actually exist ???
				// This really shouldn't happen unless there happens to be garbage in the directory
				if(!file_exists($filename))
					continue;

				$filesize = filesize($filename);

				if($filesize)
					$bytes += $filesize;
			}

		}

		if($bytes) {
			$megabytes = $bytes / 1048576;
			$megabytes = ceil($megabytes);
			return $megabytes;
		}

		return 0;

	}

?>
