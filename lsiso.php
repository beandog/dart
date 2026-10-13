#!/usr/bin/env php
<?php

	require_once 'config.local.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/blurays.php';
	require_once 'models/episodes.php';
	require_once 'models/series_dvds.php';
	require_once 'models/series.php';
	require_once 'models/tracks.php';
	require_once 'models/audio.php';
	require_once 'models/subp.php';
	require_once 'models/chapters.php';
	require_once 'models/cells.php';

	require_once 'lsiso.parser.php';

	if(!count($directories))
		$directories = array('.');

	$search_tables = array(
		'view_episodes' => array(),
		'series' => array(),
		'presets' => array(),
		'ripping' => array()
	);

	// view_episodes
	if($q_view_nsix)
		$search_tables['view_episodes'][] = array('nsix', $q_view_nsix, 'equals');
	if($q_view_episodes_series_title)
		$search_tables['view_episodes'][] = array('series_title', $q_view_episodes_series_title, 'like');

	// presets
	if($q_presets_name)
		$search_tables['presets'][] = array('name', $q_presets_name, 'like');
	if($q_presets_ivtc)
		$search_tables['presets'][] = array('ivtc', 1, 'integer');
	if($q_presets_digital)
		$search_tables['presets'][] = array('digital', 1, 'integer');

	// encoders
	if($q_ripping_name_handbrake)
		$search_tables['ripping'][] = array('name', 'handbrake', 'equals');
	if($q_ripping_name_ffmpeg)
		$search_tables['ripping'][] = array('name', 'ffmpeg', 'equals');
	if($q_ripping_name_ffpipe)
		$search_tables['ripping'][] = array('name', 'ffmpeg+pipe', 'equals');

	$isos = array();

	// Get all the ISOs, this will capture ones that are in different directory sources
	foreach($directories as $directory) {
		$directory = realpath($directory);
		$isos = array_merge($isos, glob("$directory/*iso"));
	}

	// Keep track of NSIXs searched for
	$arr_queried_nsix = array();

	foreach($isos as $iso_realpath) {

		$realpath = $iso_realpath;
		$basename = basename($iso_realpath);
		$collection_id = substr(basename($iso_realpath), 0, 1);
		$series_id = substr(basename($iso_realpath), 2, 3);
		$dvd_id = substr(basename($iso_realpath), 6, 4);
		$nsix = substr(basename($iso_realpath), 11, 5);

		// Check for a specif series NSIX
		if($q_view_nsix && $q_view_nsix != $nsix)
			continue;

		// Check for cartoons
		if($q_collection_title_cartoons && $collection_id != 1)
			continue;

		// Check for cartoons
		if($q_collection_title_shorts && $collection_id != 7)
			continue;

		$series_model = new Series_Model($series_id);

		$series_title = $series_model->title;
		assert(strlen($series_title));

		// Check if source needs inverse telecine
		$ivtc = $series_model->get_preset_ivtc();
		if($q_presets_ivtc && !$ivtc)
			continue;

		// Check for digital source
		$digital = $series_model->get_preset_digital();
		if($q_presets_digital && !$digital)
			continue;

		// Check for skips
		if($digital && $opt_skip_digital)
			continue;
		if($ivtc && $opt_skip_ivtc)
			continue;

		// Nothing uses this right now
		$crop_video = $series_model->get_preset_crop_video();

		$preset_name = $series_model->get_preset_name();

		$str = $series_title;
		$str = preg_replace("/[^0-9A-Za-z \-_.]/", '', $str);
		$str = str_replace(',', '', $str);
		$str = str_replace('\'', '', $str);
		$str = str_replace(':', '', $str);
		$str = str_replace('.', '', $str);
		$str = str_replace('-', ' ', $str);
		$str = str_replace('  ', ' ', $str);
		$str = str_replace('  ', ' ', $str);
		$str = str_replace(' ', '_', $str);
		$str = "$str-$nsix";

		$safe_dirname = $str;

		// Skip if only want samples, and it's already been listed
		if($opt_samples && in_array($nsix, $arr_queried_nsix))
			continue;

		echo "# $basename - '$series_title', preset: '$preset_name'\n";

		// Create symlinks for the ISO
		if($opt_symlink && !is_file($basename))
			symlink($realpath, $basename);

		// Make directory
		if($opt_mkdir && !is_dir($safe_dirname))
			mkdir($safe_dirname);

		// Make a symlink in the directory for the ISO
		$safe_symlink_target = "$safe_dirname/$basename";
		if($opt_mkdir && !file_exists($safe_symlink_target))
			symlink($realpath, $safe_symlink_target);

		if(!in_array($nsix, $arr_queried_nsix))
			$arr_queried_nsix[] = $nsix;

	}
