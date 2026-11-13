#!/usr/bin/env php
<?php

	$hostname = php_uname('n');

	if($hostname != 'dlna.beandog.org' && $hostname != 'dlna')
		exit("Must run on DLNA\n");

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode More Options";
	$parser->addArgument('filenames', array('optional' => true, 'multiple' => true));
	$parser->addOption('opt_symlink', array(
		'long_name' => '--link',
		'description' => 'Create symlinks',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_cron', array(
		'long_name' => '--cron',
		'description' => 'Execute cron job to do libraries maintenance',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_qa', array(
		'long_name' => '--qa',
		'description' => 'Delete broken symlinks and empty directories',
		'action' => 'StoreTrue',
		'default' => false,
	));
	/*
	$parser->addOption('opt_find_orphans', array(
		'long_name' => '--find-orphans',
		'description' => 'Find episode files not in database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/
	$parser->addOption('opt_dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Do a dry run and don\'t overwrite files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_force', array(
		'short_name' => '-y',
		'long_name' => '--force',
		'description' => 'Force overwriting symlinks',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_stats', array(
		'long_name' => '--stats',
		'description' => 'Get library statistics',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'description' => 'Add verbosity',
		'action' => 'StoreTrue',
		'default' => false,
	));

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	require_once 'config.local.php';
	require_once 'models/dbtable.php';
	require_once 'models/series.php';
	require_once 'models/episodes.php';

	$series_model = new Series_Model();

	if($opt_cron) {

		$verbose = true;
		$opt_qa = true;
		$opt_symlink = true;

		$filenames = array();

		$cmd = "find /media -mindepth 2 -maxdepth 2 -type f -name '*.m??' -mtime -1";

		$str = shell_exec($cmd);
		$str = trim($str);

		$arr = explode("\n", $str);

		if(is_array($arr) && count($arr))
			$filenames = $arr;
		else
			exit;

	}

	if($opt_stats) {

		$num_media_files = trim(shell_exec("find /media/ -mindepth 2 -maxdepth 2 -type f -name '*.m??' | wc -l"));
		echo "# Total Media Files: $num_media_files\n";

		$num_symlinks = trim(shell_exec("find /home/beandog/Libraries/ -mindepth 2 -type l -name '*.m??' | wc -l"));
		echo "# Total Episodes / Movies: $num_symlinks\n";

		if($num_media_files != $num_symlinks) {
			echo "# Missing Libraries symlinks: ".($num_media_files - $num_symlinks)."\n";
		}

		$num_symlinks = trim(shell_exec("find /home/beandog/Videos/ -type l -name '*.m??' | wc -l"));
		echo "# Total Videos: $num_symlinks\n";

		$num_files = trim(shell_exec("find /home/beandog/Libraries/ -mindepth 2 -maxdepth 2 -type d | wc -l"));
		echo "# Total Series / Movies: $num_files\n";



		exit;

	}

	if($opt_qa) {

		$cmd = "find /home/beandog/Libraries /home/beandog/Videos -xtype l";
		$arr = array(); // array needs to be empty first
		exec($cmd, $arr, $retval);

		$num_files = count($arr);

		if($num_files) {

			foreach($arr as $filename) {
				$arg_filename = escapeshellarg($filename);
				echo "# !! DEAD SYMLINK !! $arg_filename\n";
			}

			$cmd .= " -delete";
			if(!$opt_dry_run)
				passthru($cmd, $retval);
		}

		$cmd = "find /home/beandog/Libraries /home/beandog/Videos -empty";
		$arr = array();
		exec($cmd, $arr, $retval);

		$num_files = count($arr);

		if($num_files) {

			$cmd .= " -delete";

			foreach($arr as $filename) {
				$arg_filename = escapeshellarg($filename);
				if(is_dir($filename))
					echo "# !! EMPTY DIR !! $arg_filename\n";
				else
					echo "# !! EMPTY FILE !! $arg_filename\n";
			}

			if(!$opt_dry_run)
				passthru($cmd, $retval);

		}

		if(!$opt_cron)
			exit;

	}

foreach($filenames as $filename) {

	$filename = realpath($filename);

	$extension = pathinfo($filename, PATHINFO_EXTENSION);
	if(!in_array($extension, array('mkv', 'mpg', 'mp4')))
		goto next_episode;

	$dirname = pathinfo($filename, PATHINFO_DIRNAME);
	$basename = pathinfo($filename, PATHINFO_BASENAME);

	$expression = "/\d\.\d{3}\.\d{4}\.\d{5}\.[A-Z0-9.]{5}/";
	preg_match($expression, $basename, $arr_matches);
	if(count($arr_matches) != 1)
		goto next_episode;

	$episode_id = substr($arr_matches[0], 11, 5);

	if(!is_numeric($episode_id))
		goto next_episode;

	$episodes_model = new Episodes_Model($episode_id);

	$emo_filename = $episodes_model->get_filename();

	$episode_metadata = $episodes_model->get_metadata();
	if(!count($episode_metadata)) {
		echo "* $basename - cannot find episode '$episode_id' in database\n";
		goto next_episode;
	}

	if($episode_metadata['season'] == 100)
		$episode_metadata['season'] = '0';

	extract($episode_metadata);

	$arr_episode_titles = $episodes_model->get_episode_titles();

	extract($arr_episode_titles);

	$arr_d_info = array();
	$arr_d_info[] = "# $basename";
	$arr_d_info[] = $series_title;
	$d_season = '';
	if($season)
		$d_season = "s$season";
	if($season == 100)
		$d_season = "s00";
	if($episode_number)
		$d_season .= "e$episode_number";
	if($d_season)
		$arr_d_info[] = "$d_season";
	if($episode_part)
		$episode_title .= " ($episode_part)";
	$arr_d_info[] = "$episode_title";

	if(file_exists($filename)) {
		$filesize = filesize($filename);
		$mbs = number_format(ceil($filesize / 1048576));
		if($filesize)
			$arr_d_info[] = "$mbs MBs";
	}

	$d_info = implode(" : ", $arr_d_info);

	if(!$opt_symlink)
		echo "$d_info\n";

	if(!file_exists($filename))
		goto next_episode;

	$realpath = realpath($filename);

	$arg_episode_filename = escapeshellarg($realpath);

	if($opt_symlink) {

		if(!str_contains($realpath, '/media') && !str_contains($realpath, '/home/beandog/Videos')) {
			echo "# $realpath not an XFS filename\n";
			goto next_episode;
		}

		$library = '';
		$provider_title = '';

		$prefix = substr($nsix, 0, 2);

		$has_seasons = false;

		if($collection_id == 1) {
			$library = 'Cartoons';
			$provider_title = 'tvdbid';
			$has_seasons = true;
		} elseif($collection_id == 2) {
			$library = 'TV-Shows';
			$provider_title = 'tvdbid';
			$has_seasons = true;
		} elseif($collection_id == 3) {
			$library = 'Mormon';
			$provider_title = 'tvdbid';
			goto next_episode;
		} elseif($collection_id == 4) {
			$provider_title = 'tmdbid';
			$library = 'Movies';
		} elseif($collection_id == 5) {
			goto next_episode;
		} elseif($collection_id == 6) {
			$library = '';
			$provider_title = 'tvdbid';
			$has_seasons = true;
		} elseif($collection_id == 7) {
			$library = 'Shorts';
			$provider_title = 'tvdbid';
			$has_seasons = true;
		} elseif($collection_id == 8 && $prefix == '4K') {
			$library = '4K-UHD';
		} elseif($collection_id == 8) {
			$library = 'Blu-rays';
		} elseif($collection_id == 9) {
			$library = 'Holidays';
		}

		if($library_id || $collection_id == 6) {
			$series_model->load($series_id);
			$library = $series_model->get_library_name();
		}

		if($has_seasons && intval($episode_number) == 0) {
			echo "# !! NO EPISODE NUMBER !! $basename - http://dlna.beandog.org/index.php/dvds/episodes/$dvd_id\n";
			goto next_episode;
		}

		$xfs_filename = $realpath;

		$symlink_dirname = "/home/beandog/Libraries/$library/";
		$symlink_series_title = preg_replace("/[^0-9A-Za-z \-_.]/", '', $series_title);
		$symlink_dirname .= $symlink_series_title;
		if($production_year) {
			$symlink_dirname .= " ($production_year)";
		}

		if($provider_title && $provider_id)
			$symlink_dirname .= " [$provider_title-$provider_id]";

		$symlink_season = $season;
		if($has_seasons) {
			if(strlen($season) < 3)
				$symlink_season = str_pad($episode_metadata['season'], 2, 0, STR_PAD_LEFT);
			$symlink_dirname .= "/Season $symlink_season";
		}

		if(!is_dir($symlink_dirname)) {

			$arg_symlink_dirname = escapeshellarg($symlink_dirname);
			$cmd = "mkdir -p $arg_symlink_dirname";

			$retval = 0;
			if(!$opt_dry_run) {
				$output = array();
				exec($cmd, $output, $retval);
			}

			if($retval != 0) {
				$str = implode(' ', $output);
				echo "# FAILED $cmd - $str\n";
				goto next_episode;
			}

		}

		$symlink_filename = "$symlink_dirname/$symlink_series_title";
		if($has_seasons)
			$symlink_filename .= " - s".$symlink_season."e".str_pad($episode_number, 2, 0, STR_PAD_LEFT);
		if($episode_part && in_array($collection_id, array(4, 5, 8, 9)))
			$symlink_filename .= "-disc-$episode_part";
		$symlink_filename .= ".$extension";

		$arg_xfs_filename = escapeshellarg($xfs_filename);
		$arg_symlink_filename = escapeshellarg($symlink_filename);

		$retval = 0;

		if($verbose)
			echo "# Checking $symlink_filename\n";

		if(!file_exists($symlink_filename)) {

			if($opt_dry_run && !file_exists($symlink_filename))
				echo "# !! DRY RUN !! $filename : '$symlink_filename'\n";
			if(!$opt_dry_run && !file_exists($symlink_filename)) {
				$nfo_filename = str_replace(".$extension", '.nfo', $symlink_filename);
				echo "$nfo_filename\n";
				if(file_exists($nfo_filename))
					unlink($nfo_filename);
				echo "# $filename : '$symlink_filename'\n";
				symlink($xfs_filename, $symlink_filename);
			}

		}

		if($extension == 'mp4') {
			$mkv_filename = str_replace(".$extension", '.mkv', $xfs_filename);
			if(file_exists($xfs_filename) && file_exists($mkv_filename))
				echo "# !! WOULD DELETE .MKV FILE '$mkv_filename'\n";
		}

		$homedir_filename = "/home/beandog/Videos/$basename";
		if(!$opt_dry_run && !file_exists($homedir_filename)) {
			symlink($xfs_filename, $homedir_filename);
		}

	}

	next_episode:

}
