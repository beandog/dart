#!/usr/bin/env php
<?php

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
	$parser->addOption('opt_rename_episode', array(
		'long_name' => '--rename-episode',
		'description' => 'Fix episode filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_link', array(
		'long_name' => '--link',
		'description' => 'Create symlinks',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_json', array(
		'long_name' => '--json',
		'description' => 'Display episode info in JSON format',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Do a dry run and don\'t overwrite files',
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

foreach($filenames as $filename) {

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

	extract($episode_metadata);

	$d_episode_title = $episodes_model->get_display_name();

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
		if($part)
			$title .= " ($part)";
		$arr_d_info[] = "$title";

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

	$arg_episode_filename = escapeshellarg($realpath);

	// Get JSON
	if($opt_json || $opt_info || $opt_import) {

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
					echo "# $basename -> dlna:/opt/plex/$xfs/$emo_filename - already running\n";
			}

			if($upload_video) {
				if($opt_tails) {
					$cmd = "rsync -q -u --zc none $arg_episode_filename dlna:/opt/jfin/libraries/tails/$basename";
					echo "# $basename -> dlna:/opt/jfin/libraries/tails/$basename\n";
				} else {
					$cmd = "rsync -q -u --zc none $arg_episode_filename dlna:/opt/plex/$xfs/$emo_filename";
					echo "# $basename -> dlna:/opt/plex/$xfs/$emo_filename\n";
				}
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
		$cmd = "scp dlna:/opt/plex/*/$emo_filename .";
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
		if(!$dry_run)
			rename($realpath, $new_filename);

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

	if($opt_link) {

		$library = '';

		$prefix = substr($nsix, 0, 2);

		if($collection_id == 1)
			$library = 'cartoons';
		elseif($collection_id == 2)
			$library = 'tv';
		elseif($collection_id == 3)
			$library = 'jared';
		elseif($collection_id == 4)
			$library = 'movies';
		elseif($collection_id == 5)
			$library = 'jared';
		elseif($collection_id == 6)
			$library = 'archives';
		elseif($collection_id == 7)
			$library = 'archives';
		elseif($collection_id == 8 && $prefix == '4K')
			$library = 'ultrahd';
		elseif($collection_id == 8)
			$library = 'bluray';
		elseif($collection_id == 9)
			$library = 'holiday';



	}

	next_episode:

}
