#!/usr/bin/env php
<?php

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Episode More Options";
	$parser->addArgument('episodes', array('optional' => false, 'multiple' => true));
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
	$parser->addOption('opt_import', array(
		'long_name' => '--import',
		'description' => 'Import filesize and metadata into database',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_rename_file', array(
		'long_name' => '--rename-file',
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

	if($opt_info || $opt_import || $opt_rename_file || $opt_upload || $opt_fetch)  {
		require_once 'config.local.php';
		require_once 'models/dbtable.php';
		require_once 'models/series.php';
		require_once 'models/episodes.php';
	}

	$i = 0;

foreach($episodes as $episode_filename) {

	if(!file_exists($episode_filename))
		goto next_episode;

	$valid_episode = false;

	$realpath = realpath($episode_filename);
	$dirname = dirname($realpath);
	$pathinfo = pathinfo($realpath);
	$filename = basename($realpath);
	$arg_episode_filename = escapeshellarg($realpath);

	if(!array_key_exists('extension', $pathinfo))
		goto next_episode;

	$extension = $pathinfo['extension'];

	if(!in_array($extension, array('mkv', 'mpg', 'mp4')))
		goto next_episode;

	$arr = explode('.', $filename);

	$str_episode = '';

	// Check for 1.234.5678.9012.E-M-O.mkv
	if(is_numeric($filename[0])) {
		$str_episode = current(explode('-', $filename));
		$episode_id = substr($str_episode, 11, 5);
	}

	// Get episode from string prefix-foo-1.234.5678.9012.E-M-O.mkv
	if(!is_numeric($filename[0])) {

		$expression = "/\d\.\d{3}\.\d{4}\.\d{5}.*\.mkv$/";
		preg_match($expression, $filename, $arr_matches);

		if(!count($arr_matches))
			goto next_episode;

		// Find it the hard way
		$arr = explode('.', $filename);
		foreach($arr as $value) {
			if(is_numeric($value) && strlen($value) == 5) {
				$episode_id = $value;
				$str_episode = current($arr_matches);
				break;
			}
		}

	}

	if(!$episode_id)
		goto next_episode;

	$valid_episode = true;
	$i++;

	// Get JSON
	if($opt_info || $opt_json || $opt_import) {

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
		$arr_info['episode_mbs'] = (filesize($episode_filename) / 1048576);
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
		if($json['media']['track'][2]['CodecID'] == 'A_AC3')
			$arr_info['acodec'] = 'ac3';
		elseif($json['media']['track'][2]['CodecID'] == 'A_AAC-2')
			$arr_info['acodec'] = 'aac';
		if(array_key_exists('Channels', $json['media']['track'][2]))
			$arr_info['channels'] = $json['media']['track'][2]['Channels'];
		foreach($json['media']['track'] as $arr) {
			if($arr['@type'] == 'Text')
				$arr_info['scodec'] = 'text';
			if($arr['@type'] == 'Text' && $arr['Format'] == 'VobSub')
				$arr_info['scodec'] = 'vobsub';
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

	// Get metadata and standardized filename
	if($opt_info || $opt_import || $opt_rename_file || $opt_upload || $opt_fetch)  {

		$episodes_model = new Episodes_Model($episode_id);

		$episode_metadata = $episodes_model->get_metadata();
		if(!count($episode_metadata)) {
			echo "* $filename - cannot find episode '$episode_id' in database\n";
			goto next_episode;
		}

		$episode_title = $episodes_model->get_display_name();
		$emo_filename = $episodes_model->get_filename();

		extract($episode_metadata);

	}

	if($opt_info || $opt_upload || $opt_import) {

		$arr_d_info = array();
		$arr_d_info[] = "$series_title";
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

		$filesize = filesize($episode_filename);
		$mbs = number_format(ceil($filesize / 1048576));
		$arr_d_info[] = "$mbs MBs";

		$d_title = implode(" : ", $arr_d_info);

		$d_info = "# $filename - $d_title";

		echo "$d_info\n";

		if($opt_upload) {

			$xfs = "";

			$collection_id = $filename[0];

			if($collection_id == 1)
				$xfs = "sd";
			elseif($collection_id == "2")
				$xfs = "tv";
			elseif($collection_id == "4")
				$xfs = "tv";
			if(strstr($filename, ".HD"))
				$xfs = "hd";
			elseif(strstr($filename, ".BD"))
				$xfs = "bd";
			elseif(strstr($filename, ".4K"))
				$xfs = "bd";

			if(!$xfs)
				goto next_episode;

			$cmd = "rsync -au --quiet $arg_episode_filename dlna:/opt/plex/$xfs";
			echo "# $cmd\n";

			passthru($cmd);

		}

	}

	if($opt_fetch) {

		$hostname = gethostname();
		if($hostname == "dlna" || $hostname == "dlna.beandog.org")
			goto next_episode;

		if($filename == $emo_filename)
			goto next_episode;

		if(file_exists($emo_filename)) {
			echo "# $emo_filename exists, skipping\n";
			goto next_episode;
		}

		echo "# Fetching $emo_filename ...\n";
		$cmd = "scp dlna:/opt/plex/*/$emo_filename .";
		passthru($cmd);

	}

	if($opt_rename_file) {

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

		extract($arr_info);
		$encode_id = $encodes_model->load_uuid($uuid);

		if($encode_id) {
			$encodes_model->delete();
			$encode_id = $encodes_model->create_new();
			// goto next_episode;
		} else {
			$encode_id = $encodes_model->create_new();
		}

		$bitrate = intval($bitrate);
		$crf = intval($crf);
		$duration = intval($duration);
		$framerate = floatval($framerate);
		$episode_mbs = ceil($episode_mbs);
		$video_mbs = ceil($video_mbs);
		$audio_mbs = ceil($audio_mbs);

		$encodes_model->episode_id = $episode_id;
		$encodes_model->filename = $filename;
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

	next_episode:

}
