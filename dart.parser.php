<?php

	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "DVD Archiving Tool";
	$parser->addArgument('devices', array('optional' => true, 'multiple' => true));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'long_name' => '--verbose',
		'description' => 'Be verbose',
		'action' => 'Counter',
		'default' => 0,
	));
	$parser->addOption('debug', array(
		'short_name' => '-z',
		'long_name' => '--debug',
		'description' => 'Print out debugging information',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_force', array(
		'long_name' => '--force',
		'description' => 'Force import of new Blu-ray',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_info', array(
		'long_name' => '--info',
		'description' => 'Display metadata about a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_encode_info', array(
		'long_name' => '--encode-info',
		'description' => 'Display encoding instructions for a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_log_progress', array(
		'long_name' => '--log-progress',
		'description' => 'Log progress of ffmpeg encode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_copy_info', array(
		'long_name' => '--copy-info',
		'description' => 'Display dvd_copy instructions for a DVD',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_subtitles', array(
		'long_name' => '--subtitles',
		'description' => 'Copy subtitle streams',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_backup', array(
		'long_name' => '--backup',
		'description' => 'Backup device to filesystem',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_backup_dir', array(
		'long_name' => '--dirname',
		'description' => 'Set target backup directory',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('opt_makemkv', array(
		'long_name' => '--makemkv',
		'description' => 'Use MakeMKV for backup',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_skip_existing', array(
		'long_name' => '--skip-existing',
		'description' => 'Skip writing to existing files',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_test_existing', array(
		'long_name' => '--test-existing',
		'description' => 'Prefix encode to check if file exists',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_iso_filename', array(
		'long_name' => '--iso-filename',
		'description' => 'Display ISO filename',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_rename_iso', array(
		'long_name' => '--rename-iso',
		'description' => 'Rename ISO to correct syntax',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_episode_filenames', array(
		'long_name' => '--episode-filenames',
		'description' => 'Print episode filenames',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_geniso', array(
		'long_name' => '--geniso',
		'description' => 'Create ISO from directory',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_qa', array(
		'long_name' => '--qa',
		'description' => 'Make changes when doing a QA run',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_one', array(
		'long_name' => '--one',
		'description' => 'Only handle one episode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_time', array(
		'long_name' => '--time',
		'description' => 'Log time of encode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_handbrake', array(
		'long_name' => '--handbrake',
		'description' => 'Use HandBrake to rip DVDs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	/*
	$parser->addOption('opt_dvdrip', array(
		'long_name' => '--dvdrip',
		'description' => 'Use dvd_rip to rip DVDs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	*/
	$parser->addOption('opt_ffmpeg', array(
		'long_name' => '--ffmpeg',
		'description' => 'Use ffmpeg to rip DVDs',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_ffplay', array(
		'long_name' => '--ffplay',
		'description' => 'Use ffplay to playback video directly',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_ffprobe', array(
		'long_name' => '--ffprobe',
		'description' => 'Use ffprobe to get DVD information',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_ffpipe', array(
		'long_name' => '--ffpipe',
		'description' => 'Use dvd_copy or bluray_copy to pipe to ffmpeg',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_crop', array(
		'long_name' => '--crop',
		'description' => 'Crop video with ffmpeg',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_scan', array(
		'long_name' => '--scan',
		'description' => 'Use HandBrake to get DVD information',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_remux', array(
		'long_name' => '--remux',
		'description' => 'Remux a DVD episode using ffmpeg for debugging',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_ssa', array(
		'long_name' => '--ssa',
		'description' => 'Export SSA subtitles using ffmpeg',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_vcodec', array(
		'long_name' => '--vcodec',
		'description' => 'Use video codec',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('arg_acodec', array(
		'long_name' => '--acodec',
		'description' => 'Use audio codec',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('arg_crf', array(
		'long_name' => '--crf',
		'description' => 'Set encoding CRF',
		'action' => 'StoreString',
		'default' => null,
	));
	$parser->addOption('opt_no_crf', array(
		'long_name' => '--no-crf',
		'description' => 'Disable setting encoding CRF',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_fps', array(
		'long_name' => '--fps',
		'description' => 'Set encoding FPS',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('arg_prefix', array(
		'long_name' => '--prefix',
		'description' => 'Add prefix string to episode filenames',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('opt_no_fps', array(
		'long_name' => '--no-fps',
		'description' => 'Disable setting framerate',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_no_filters', array(
		'long_name' => '--no-filters',
		'description' => 'Disable all video filters',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_comb_detect', array(
		'long_name' => '--comb-detect',
		'description' => 'Detect interlacing',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_bwdif', array(
		'long_name' => '--bwdif',
		'description' => 'Detect interlacing using ffmpeg bwdif filter',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('arg_decomb', array(
		'long_name' => '--decomb',
		'description' => 'Deinterlace video using filters',
		'action' => 'StoreString',
		'default' => null,
	));
	$parser->addOption('opt_fast', array(
		'long_name' => '--fast',
		'description' => 'Speed up encoding by using ultrafast preset',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_slow', array(
		'long_name' => '--slow',
		'description' => 'Use slow preset for encoding',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_mkv', array(
		'long_name' => '--mkv',
		'description' => 'Use Matroska container',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('opt_mp4', array(
		'long_name' => '--mp4',
		'description' => 'Use MP4 container',
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
