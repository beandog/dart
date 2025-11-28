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
	if($opt_import || $opt_json) {

		$cmd_json = "mediainfo --Output=JSON $arg_episode_filename | jq";
		$str_json = trim(shell_exec($cmd_json));

		$json = json_decode($str_json, true);

		$arr_info = array(
			'uuid' => $json['media']['track'][0]['UniqueID'],
			'date' => $json['media']['track'][0]['File_Modified_Date_Local'],
			'filesize' => $json['media']['track'][0]['FileSize'],
			'duration' => $json['media']['track'][0]['Duration'],
			'bitrate' => $json['media']['track'][0]['OverallBitRate'],
			'frame_count' => $json['media']['track'][0]['FrameCount'],
			'application' => $json['media']['track'][0]['Encoded_Application'],
			'library' => $json['media']['track'][0]['Encoded_Library'],
		);

		// Get more data if encoded with libx264
		if(strstr($str_json, 'rc=crf')) {

			$arr_x264_info = array();

			// I only care about a few variables, so I can determine if its preset is medium or better
			$arr_preset_options = array('b_adapt', 'bframes', 'direct', 'me', 'me_range', 'rc_lookahead', 'ref', 'subme', 'trellis');

			$arr_info['x264_version'] = $json['media']['track'][1]['Encoded_Library_Version'];
			$arr_info['x264_settings'] = $json['media']['track'][1]['Encoded_Library_Settings'];

			$arr_x264_settings = explode('/', $arr_info['x264_settings']);

			sort($arr_x264_settings);

			foreach($arr_x264_settings as $value) {

				$arr = explode('=', $value);

				$option = trim($arr[0]);

				if(in_array($option, $arr_preset_options)) {
					$setting = trim($arr[1]);
					$arr_x264_info[$option] = $setting;
				}

			}

			$str_x264_json = json_encode($arr_x264_info);
			$json['media']['track'][1]['x264_settings'] = $arr_x264_info;

			$str_json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		}

		if($opt_json) {
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

	if($opt_import) {

		require_once 'models/encodes.php';

		$encodes_model = new Encodes_Model();

		$encodes_model->load_filename($filename);

	}

	if($opt_info || $opt_upload) {

		$arr_d_info = array();
		$arr_d_info[] = "$series_title";
		$d_season = '';
		if($season)
			$d_season = "s$season";
		if($episode_number)
			$d_season .= "e$episode_number";
		if($d_season)
			$arr_d_info[] = "$d_season";
		$arr_d_info[] = "$title";

		$d_title = implode(" : ", $arr_d_info);

		$filesize = filesize($episode_filename);
		$mbs = number_format(ceil($filesize / 1048576));

		$d_info = "# $filename - $d_title : ${mbs} MBs";

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

	next_episode:

}

