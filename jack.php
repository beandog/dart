#!/usr/bin/env php
<?php

	$hostname = php_uname('n');

	if($hostname != 'dlna.beandog.org' && $hostname != 'dlna')
		exit("Must run on DLNA\n");

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode More Options";
	$parser->addArgument('filenames', array('optional' => true, 'multiple' => true));
	$parser->addOption('opt_import', array(
		'long_name' => '--import',
		'description' => 'Import filesize and metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_symlink', array(
		'long_name' => '--link',
		'description' => 'Create symlinks',
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

	if($opt_stats) {

		$num_media_files = trim(shell_exec("find /media/ -mindepth 2 -maxdepth 2 -type f -name '*.mkv' | wc -l"));
		echo "# Total Media Files: $num_media_files\n";

		$num_symlinks = trim(shell_exec("find /home/beandog/Libraries/ -mindepth 2 -type l -name '*.mkv' | wc -l"));
		echo "# Total Episodes / Movies: $num_symlinks\n";

		if($num_media_files != $num_symlinks) {
			echo "# Missing Libraries symlinks: ".($num_media_files - $num_symlinks)."\n";
		}

		$num_symlinks = trim(shell_exec("find /home/beandog/Videos/ -type l -name '*.mkv' | wc -l"));
		echo "# Total Videos: $num_symlinks\n";

		$num_files = trim(shell_exec("find /home/beandog/Libraries/ -mindepth 2 -maxdepth 2 -type d | wc -l"));
		echo "# Total Series / Movies: $num_files\n";



		exit;

	}

	if($opt_qa) {

		$cmd = "find /home/beandog/Libraries /home/beandog/Videos -xtype l";
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

	// Get JSON
	if($opt_import) {

		$vcodec = '';
		$crf = '';
		$preset = '';

		$cmd_json = "mediainfo --Output=JSON $arg_episode_filename";
		$str_json = trim(shell_exec($cmd_json));

		$json = json_decode($str_json, true);

		$arr_info = array();

		$arr_info['duration'] = null;
		$arr_info['bitrate'] = null;
		$arr_info['frame_count'] = null;
		$arr_info['application'] = null;
		$arr_info['library'] = null;
		$arr_info['encode_settings'] = null;
		$arr_info['encoded_date'] = null;
		$arr_info['x264_version'] = null;
		$arr_info['x264_preset'] = null;
		$arr_info['framerate'] = null;
		$arr_info['episode_mbs'] = null;
		$arr_info['video_mbs'] = null;
		$arr_info['audio_mbs'] = null;
		$arr_info['vcodec'] = null;
		$arr_info['acodec'] = null;
		$arr_info['channels'] = null;
		$arr_info['scodec'] = null;

		$arr_info['uuid'] = $json['media']['track'][1]['UniqueID'];
		$arr_info['episode_mbs'] = (filesize($realpath) / 1048576);
		if(array_key_exists('Duration', $json['media']['track'][0]))
			$arr_info['duration'] = $json['media']['track'][0]['Duration'];
		if(array_key_exists('OverallBitRate', $json['media']['track'][0]))
			 $arr_info['bitrate'] = $json['media']['track'][0]['OverallBitRate'];
		if(array_key_exists('FrameRate', $json['media']['track'][0]))
			$arr_info['framerate'] = $json['media']['track'][0]['FrameRate'];
		if(array_key_exists('Encoded_Date', $json['media']['track'][0]))
			$arr_info['encoded_date'] = $json['media']['track'][0]['Encoded_Date'];
		if(array_key_exists('FrameCount', $json['media']['track'][0]))
			$arr_info['frame_count'] = $json['media']['track'][0]['FrameCount'];
		if(array_key_exists('Encoded_Application', $json['media']['track'][0]))
			$arr_info['application'] = $json['media']['track'][0]['Encoded_Application'];
		if(array_key_exists('Encoded_Library', $json['media']['track'][0]))
			$arr_info['library'] = $json['media']['track'][0]['Encoded_Library'];
		if(array_key_exists('Encoded_Library_Settings', $json['media']['track'][0]))
			$arr_info['encode_settings'] = $json['media']['track'][0]['Encoded_Library_Settings'];
		if(array_key_exists('Encoded_Library_Settings', $json['media']['track'][1]))
			$arr_info['encode_settings'] = $json['media']['track'][1]['Encoded_Library_Settings'];
		if(array_key_exists('StreamSize', $json['media']['track'][1]))
			$arr_info['video_mbs'] = ($json['media']['track'][1]['StreamSize'] / 1048576);
		if(array_key_exists('StreamSize', $json['media']['track'][2]))
			$arr_info['audio_mbs'] = ($json['media']['track'][2]['StreamSize'] / 1048576);
		if(array_key_exists('Format', $json['media']['track'][1]))
			$arr_info['vcodec'] = strtolower($json['media']['track'][1]['Format']);
		if($arr_info['vcodec'] == 'mpeg video')
			$arr_info['vcodec'] = 'mpeg2';
		if($json['media']['track'][2]['CodecID'] == 'A_AC3')
			$arr_info['acodec'] = 'ac3';
		elseif($json['media']['track'][2]['CodecID'] == 'A_AAC-2')
			$arr_info['acodec'] = 'aac';
		elseif($json['media']['track'][2]['CodecID'] == 'A_MPEG/L3')
			$arr_info['acodec'] = 'mp3';
		elseif($json['media']['track'][2]['CodecID'] == 'A_DTS' && $json['media']['track'][2]['Format_Commercial_IfAny'] == 'DTS-HD Master Audio')
			$arr_info['acodec'] = 'dts-hd';
		if(array_key_exists('Channels', $json['media']['track'][2]))
			$arr_info['channels'] = $json['media']['track'][2]['Channels'];
		foreach($json['media']['track'] as $arr) {
			if($arr['@type'] == 'Text')
				$arr_info['scodec'] = 'cc';
			if($arr['@type'] == 'Text' && $arr['Format'] == 'VobSub')
				$arr_info['scodec'] = 'vobsub';
			if($arr['@type'] == 'Text' && $arr['Format'] == 'PGS')
				$arr_info['scodec'] = 'pgs';
		}

		// Get more data if encoded with libx264
		if(strstr($str_json, 'rc=crf')) {

			$vcodec = 'x264';

			$x264_preset = 'medium';

			$arr_x264_info = array();

			// I only care about a few variables, so I can determine if its preset is medium or better
			$arr_preset_options = array('b_adapt', 'bframes', 'crf', 'direct', 'me', 'me_range', 'rc_lookahead', 'ref', 'subme', 'trellis');

			$arr_info['x264_version'] = $json['media']['track'][1]['Encoded_Library_Version'];

			$arr_x264_settings = explode('/', $arr_info['encode_settings']);

			sort($arr_x264_settings);

			foreach($arr_x264_settings as $value) {

				$arr = explode('=', $value);

				$option = trim($arr[0]);

				if(in_array($option, $arr_preset_options)) {
					$setting = trim($arr[1]);
					$arr_x264_info[$option] = $setting;
				}

			}

			$arr_x264_info['crf'] = abs($arr_x264_info['crf']);

			$json['media']['track'][1]['x264_settings'] = $arr_x264_info;

			extract($arr_x264_info);

			if($rc_lookahead >= '50' && $ref >= 5 && $subme >= 8 && $trellis >= 2)
				$x264_preset = 'slow';
			if($x264_preset == 'slow' && $b_adapt >= 2 && $me == 'umh' && $rc_lookahead >= 60 && $ref >= 8 && $subme >= 8)
				$x264_preset = 'slower';
			if($x264_preset == 'slower' && $bframes >= 8 && $me_range >= 24 && $ref >= 16 && $subme >= 10)
				$x264_preset = 'veryslow';

			$arr_info['x264_preset'] = $x264_preset;

		}

	}

	if($opt_import) {

		if($opt_upload) {

			$xfs = "";

			if($collection_id == 1)
				$xfs = "sd";
			elseif($collection_id == 2)
				$xfs = "tv";
			elseif($collection_id == 4)
				$xfs = "tv";
			elseif($collection_id == 7)
				$xfs = "sd";
			if(str_contains($emo_filename, ".HD"))
				$xfs = "hd";
			elseif(str_contains($emo_filename, ".BD"))
				$xfs = "bd";
			elseif(str_contains($emo_filename, ".4K"))
				$xfs = "bd";

			if(!$xfs)
				goto next_episode;

			$upload_video = true;

			$cmd = "pgrep -l '^(HandBrakeCLI|ffmpeg)' -a";
			$str = trim(shell_exec($cmd));
			if(str_contains($str, $basename)) {
				$upload_video = false;
				echo "# $basename - video already encoding\n";
			}

			$cmd = "pgrep -l '^rsync' -a";
			$str = trim(shell_exec($cmd));
			if(str_contains($str, $basename)) {
				$upload_video = false;
				if($opt_tails)
					echo "# $basename -> dlna:/opt/jfin/libraries/tails/$basename - already running\n";
				else
					echo "# $basename -> dlna:/media/$xfs/$emo_filename - already running\n";
			}

		}

	}

	if($opt_import) {

		require_once 'models/encodes.php';

		$encodes_model = new Encodes_Model();

		$encodes_model->delete_encodes($basename);
		$encode_id = $encodes_model->create_new();

		extract($arr_info);

		$bitrate = intval($bitrate);
		$crf = intval($crf);
		$duration = intval($duration);
		$framerate = floatval($framerate);
		$episode_mbs = ceil($episode_mbs);
		$video_mbs = ceil($video_mbs);
		$audio_mbs = ceil($audio_mbs);

		$encodes_model->episode_id = $episode_id;
		$encodes_model->filename = $basename;
		$encodes_model->uuid = $uuid;
		$encodes_model->filesize = $filesize;
		if($encoded_date)
			$encodes_model->encoded_date = $encoded_date;
		if($duration)
			$encodes_model->duration = $duration;
		if($bitrate)
			$encodes_model->bitrate = $bitrate;
		if($frame_count)
			$encodes_model->frame_count = $frame_count;
		if($application)
			$encodes_model->application = $application;
		if($library)
			$encodes_model->library = $library;
		if($encode_settings)
			$encodes_model->encode_settings = $encode_settings;
		if($encoded_date)
			$encodes_model->encoded_date = $encoded_date;
		if($x264_version)
			$encodes_model->x264_version = $x264_version;
		if($x264_preset)
			$encodes_model->x264_preset = $x264_preset;
		if($crf)
			$encodes_model->x264_crf = $crf;
		if($framerate)
			$encodes_model->framerate = $framerate;
		if($episode_mbs)
			$encodes_model->episode_mbs = $episode_mbs;
		if($video_mbs)
			$encodes_model->video_mbs = $video_mbs;
		if($audio_mbs)
			$encodes_model->audio_mbs = $audio_mbs;
		if($vcodec)
			$encodes_model->vcodec = $vcodec;
		if($acodec)
			$encodes_model->acodec = $acodec;
		if($channels)
			$encodes_model->channels = $channels;
		if($scodec)
			$encodes_model->scodec = $scodec;

	}

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
		} elseif($collection_id == 3)
			goto next_episode;
		elseif($collection_id == 4)
			$library = 'Movies';
		elseif($collection_id == 5)
			goto next_episode;
		elseif($collection_id == 6)
			goto next_episode;
		elseif($collection_id == 7) {
			$library = 'Two-Player';
			$provider_title = 'tvdbid';
			$has_seasons = true;
		} elseif($collection_id == 8 && $prefix == '4K')
			$library = '4K-UHD';
		elseif($collection_id == 8)
			$library = 'Blu-rays';
		elseif($collection_id == 9)
			$library = 'Holidays';

		if($has_seasons && intval($episode_number) == 0) {
			echo "# !! NO EPISODE NUMBER !! $basename - http://dlna.beandog.org:8080/index.php/dvds/episodes/$dvd_id\n";
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
			if(!$opt_dry_run)
				exec($cmd, $output, $retval);

			if($retval != 0) {
				$str = implode(' ', $output);
				echo "# FAILED $cmd - $str\n";
				goto next_episode;
			}

		}

		$symlink_filename = "$symlink_dirname/$symlink_series_title";
		if($has_seasons)
			$symlink_filename .= " - s".$symlink_season."e".str_pad($episode_number, 2, 0, STR_PAD_LEFT);
		$symlink_filename .= ".$extension";

		$arg_xfs_filename = escapeshellarg($xfs_filename);
		$arg_symlink_filename = escapeshellarg($symlink_filename);

		$retval = 0;

		if(!file_exists($symlink_filename)) {

			if($opt_dry_run && !file_exists($symlink_filename))
				echo "# !! DRY RUN !! $filename : '$symlink_filename'\n";
			if(!$opt_dry_run && !file_exists($symlink_filename)) {
				echo "# $filename : '$symlink_filename'\n";
				$cmd = "ln -s -v $arg_xfs_filename $arg_symlink_filename";
				exec($cmd, $output, $retval);
			}

			if($retval != 0) {
				$str = implode(' ', $output);
				echo "# FAILED $cmd - $str\n";
				goto next_episode;
			}

		}

		$retval = 0;
		$homedir_filename = "/home/beandog/Videos/$basename";
		if(!$opt_dry_run && !file_exists($homedir_filename)) {
			$arg_homedir_filename = escapeshellarg($homedir_filename);
			$cmd = "ln -s -v $arg_xfs_filename $arg_homedir_filename";
			exec($cmd, $output, $retval);
		}

		if($retval != 0) {
			$str = implode(' ', $output);
			echo "# FAILED $cmd - $str\n";
			goto next_episode;
		}

	}

	next_episode:

}
