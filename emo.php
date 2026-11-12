#!/usr/bin/env php
<?php

	require_once 'config.local.php';

	$hostname = php_uname('n');

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode More Options";
	$parser->addArgument('filenames', array('optional' => false, 'multiple' => true));
	$parser->addOption('opt_info', array(
		'long_name' => '--info',
		'description' => 'Display episode info',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_fetch', array(
		'long_name' => '--fetch',
		'description' => 'Fetch a copy of the current episode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_upload', array(
		'long_name' => '--upload',
		'description' => 'Upload episode to media server',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_tails', array(
		'long_name' => '--tails',
		'description' => 'Upload episode to QA library',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_import', array(
		'long_name' => '--import',
		'description' => 'Import filesize and metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_delete', array(
		'long_name' => '--delete',
		'description' => 'Delete episode and its realpath',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_rename_episode', array(
		'long_name' => '--rename-episode',
		'description' => 'Fix episode filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_json', array(
		'long_name' => '--json',
		'description' => 'Display episode info in JSON format',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Do a dry run and don\'t overwrite files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'description' => 'Verbose output',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('debug', array(
		'short_name' => '-z',
		'description' => 'Debugging output',
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

	if($debug)
		$verbose = true;

	require_once 'config.local.php';
	require_once 'models/dbtable.php';
	require_once 'models/series.php';
	require_once 'models/episodes.php';

foreach($filenames as $filename) {

	$src_filename = $filename;

	$extension = pathinfo($filename, PATHINFO_EXTENSION);
	if(!in_array($extension, array('mkv', 'mpg', 'mp4')))
		goto next_episode;

	if(is_link($filename)) {
		$src_basename = basename($filename);
		$src_realpath = realpath($filename);
		echo "# $src_basename -> $src_realpath\n";
		$filename = realpath($filename);
	}

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

	$emo_filename = $episodes_model->get_filename($extension);

	$episode_metadata = $episodes_model->get_metadata();
	if(!is_array($episode_metadata) || !count($episode_metadata)) {
		echo "* !!! $basename - cannot find episode '$episode_id' in database\n";
		goto next_episode;
	}

	extract($episode_metadata);

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

	$arr_d_info = array();
	$arr_d_info[] = "# $basename";
	$arr_d_info[] = $series_title;
	$d_season = '';
	if($season)
		$d_season = "s$season";
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

	echo "$d_info\n";

	if(!file_exists($filename))
		goto next_episode;

	$realpath = realpath($filename);

	if($opt_delete) {

		$src_dirname = dirname($src_filename);
		$link_basename = $src_dirname.'/'.basename($src_filename, ".$extension");

		$nfo_filename = "$link_basename.nfo";
		$jpg_filename = "$link_basename-thumb.jpg";

		if(file_exists($filename)) {
			echo "# !! Removing '$src_basename'\n";
			if(!$opt_dry_run)
				unlink($src_filename);
			if(file_exists($nfo_filename)) {
				echo "# !! Removing '$nfo_filename'\n";
				if(!$opt_dry_run)
					unlink($nfo_filename);
			}
			if(file_exists($jpg_filename)) {
				echo "# !! Removing '$jpg_filename'\n";
				if(!$opt_dry_run)
					unlink($jpg_filename);
			}
			if(file_exists($realpath)) {
				echo "# !! Removing '$realpath'\n";
				if(!$opt_dry_run)
					unlink($realpath);
			}
		}

	}

	$arg_episode_filename = escapeshellarg($realpath);

	// Get JSON
	if($opt_json || $opt_info || $opt_import) {

		$vcodec = '';
		$crf = '';
		$preset = '';

		$cmd_json = "mediainfo --Output=JSON $arg_episode_filename";

		if($debug)
			echo "* Executing: $cmd_json\n";

		$str_json = trim(shell_exec($cmd_json));

		$json = json_decode($str_json, true);

		if(!array_key_exists('Duration', $json['media']['track'][0])) {
			echo "# $basename - no duration, probably encoding\n";
			continue;
		}

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


		$arr_info['episode_mbs'] = (filesize($realpath) / 1048576);
		if(array_key_exists('Duration', $json['media']['track'][0]))
			$arr_info['duration'] = $json['media']['track'][0]['Duration'];
		if(array_key_exists('Encoded_Date', $json['media']['track'][0]))
			$arr_info['encoded_date'] = $json['media']['track'][0]['Encoded_Date'];
		elseif(array_key_exists('File_Modified_Date_Local', $json['media']['track'][0]))
			$arr_info['encoded_date'] = $json['media']['track'][0]['File_Modified_Date_Local'];
		if(array_key_exists('Encoded_Application', $json['media']['track'][0]))
			$arr_info['application'] = $json['media']['track'][0]['Encoded_Application'];
		if(array_key_exists('Encoded_Library', $json['media']['track'][0]))
			$arr_info['library'] = $json['media']['track'][0]['Encoded_Library'];
		if(array_key_exists('Encoded_Library_Settings', $json['media']['track'][0]))
			$arr_info['encode_settings'] = $json['media']['track'][0]['Encoded_Library_Settings'];
		if(array_key_exists('Encoded_Library_Settings', $json['media']['track'][1]))
			$arr_info['encode_settings'] = $json['media']['track'][1]['Encoded_Library_Settings'];
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

		if($opt_json) {
			$str_json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			echo "$str_json\n";
			goto next_episode;
		}

	}

	if($opt_tails)
		$opt_upload = true;

	if($opt_info || $opt_upload || $opt_import) {

		if($opt_upload) {

			$xfs = "";

			if($collection_id == 1)
				$xfs = "sd";
			elseif($collection_id == 2)
				$xfs = "tv";
			elseif($collection_id == 3)
				$xfs = "tv";
			elseif($collection_id == 4)
				$xfs = "tv";
			elseif($collection_id == 5)
				$xfs = "sd";
			elseif($collection_id == 6)
				$xfs = "sd";
			elseif($collection_id == 7)
				$xfs = "sd";
			elseif($collection_id == 8)
				$xfs = "bd";
			elseif($collection_id == 9)
				$xfs = "sd";
			if(str_contains($emo_filename, ".HD"))
				$xfs = "hd";
			elseif(str_contains($emo_filename, ".BD"))
				$xfs = "bd";
			elseif(str_contains($emo_filename, ".4K"))
				$xfs = "bd";

			if($xfs && $extension == 'mp4')
				$xfs .= "/v2";

			if(!$xfs)
				goto next_episode;

			$upload_video = true;

			$cmd = "pgrep -l '^(HandBrakeCLI|ffmpeg)' -a";
			if($debug)
				echo "* Executing: $cmd\n";
			$str = trim(shell_exec($cmd));
			if(str_contains($str, $basename)) {
				$upload_video = false;
				echo "# $basename - video already encoding\n";
			}

			$cmd = "pgrep -l '^rsync' -a";
			if($debug)
				echo "* Executing: $cmd\n";
			$str = trim(shell_exec($cmd));
			if(str_contains($str, $basename)) {
				$upload_video = false;
				if($opt_tails)
					echo "# $basename -> dlna /opt/jfin/libraries/tails/$basename - already running\n";
				else
					echo "# $basename -> dlna /media/$xfs/$emo_filename - already running\n";
			}

			if($upload_video) {
				if($opt_tails) {
					$cmd = "rsync -q -u --zc none --whole-file $arg_episode_filename dlna/opt/jfin/libraries/tails/$basename";
					echo "# $basename -> dlna /opt/jfin/libraries/tails/$basename\n";
				} else {
					if($verbose)
						$cmd = "rsync -v -u -t --zc none --whole-file --progress $arg_episode_filename rsync://dlna/media/$xfs/$emo_filename";
					else
						$cmd = "rsync -q -u -t --zc none --whole-file $arg_episode_filename rsync://dlna/media/$xfs/$emo_filename";
					echo "# $basename -> dlna /media/$xfs/$emo_filename\n";
				}
				if($opt_dry_run)
					echo "$cmd\n";
				else
					passthru($cmd);
			}

		}

	}

	if($opt_fetch) {

		$hostname = gethostname();
		if($hostname == "dlna" || $hostname == "dlna.beandog.org")
			goto next_episode;

		if($basename == $emo_filename)
			goto next_episode;

		if(file_exists($emo_filename)) {
			echo "# $emo_filename exists, skipping\n";
			goto next_episode;
		}

		echo "# Fetching $emo_filename ...\n";
		$cmd = "scp dlna:/media/*/$emo_filename .";
		passthru($cmd);

	}

	if($opt_rename_episode) {

		if(file_exists($emo_filename))
			goto next_episode;

		$new_filename = "$dirname/$emo_filename";

		if($realpath == $new_filename)
			goto next_episode;

		$arg_new_filename = escapeshellarg($new_filename);
		echo "$arg_episode_filename -> $arg_new_filename\n";
		if(!$opt_dry_run)
			rename($realpath, $new_filename);

	}

	if($opt_import || $opt_delete) {

		require_once 'models/encodes.php';

		$encodes_model = new Encodes_Model();

		$encodes_model->delete_encodes($basename);
		if($opt_delete)
			continue;
		$encode_id = $encodes_model->create_new();

		extract($arr_info);

		$bitrate = intval($bitrate);
		$crf = intval($crf);
		$duration = intval($duration);
		$framerate = floatval($framerate);
		$episode_mbs = ceil($episode_mbs);
		if(!is_null($video_mbs))
			$video_mbs = ceil($video_mbs);
		if(!is_null($audio_mbs))
			$audio_mbs = ceil($audio_mbs);

		$encodes_model->episode_id = $episode_id;
		$encodes_model->filename = $basename;
		$encodes_model->filesize = $filesize;
		if($encoded_date)
			$encodes_model->encoded_date = $encoded_date;
		if($duration)
			$encodes_model->duration = $duration;
		if($application)
			$encodes_model->application = $application;
		if($library)
			$encodes_model->library = $library;
		if($encode_settings)
			$encodes_model->encode_settings = $encode_settings;
		if($encoded_date)
			$encodes_model->encoded_date = $encoded_date;
		if($episode_mbs)
			$encodes_model->episode_mbs = $episode_mbs;
		if($vcodec)
			$encodes_model->vcodec = $vcodec;
		if($acodec)
			$encodes_model->acodec = $acodec;
		if($channels)
			$encodes_model->channels = $channels;
		if($scodec)
			$encodes_model->scodec = $scodec;

	}

	next_episode:

}
