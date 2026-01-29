<?php

if($disc_type == 'dvd' && $opt_encode) {

	$dart_status = 'encode_episode';

	if($opt_skip_existing && file_exists($filename))
		goto next_episode;

	$cmd = "pgrep -af '^(HandBrakeCLI|rsync|emo|ffmpeg)'";
	if($debug) {
		echo "* Looking for encode of '$filename'\n";
		echo "* Executing: $cmd\n";
	}
	$str = shell_exec($cmd);
	$str = trim($str);

	$arr = explode(' ', $str);
	array_shift($arr);
	$str = implode(' ', $arr);

	if($debug)
		echo "* $str\n";

	if(str_contains($str, $filename))
		goto next_episode;

	$arg_device = escapeshellarg(realpath($device));

	$arg_filename = escapeshellarg($filename);

	// Stolen from emo :D
	$episode_metadata = $episodes_model->get_metadata();
	$title = $episode_metadata['title'];
	$d_episode_title = $episodes_model->get_display_name();
	$arr_d_info = array();
	$arr_d_info[] = $episode_metadata['series_title'];
	$d_season = '';
	if($episode_metadata['season'])
		$d_season = "s${episode_metadata['season']}";
	if($episode_metadata['episode_number'])
		$d_season .= "e${episode_metadata['episode_number']}";
	if($d_season)
		$arr_d_info[] = "$d_season";
	if($episode_metadata['part'])
		$title .= " (".$episode_metadata['part'].")";
	$arr_d_info[] = $title;
	$d_info = implode(" : ", $arr_d_info);

	echo "[Encoding]\n";
	echo "* $d_info\n";
	echo "* Source: $arg_device\n";
	echo "* Target: $arg_filename\n";

	$logfile = "/tmp/".basename($filename).".log";
	$arg_logfile = escapeshellarg($logfile);

	if($verbose) {
		echo "* $encode_command\n";
	} else {
		$encode_command .= " 2> $arg_logfile";
	}

	passthru($encode_command, $retval);

	if($retval) {

		$arg_filename = escapeshellarg($filename);
		echo "* Encode failed, removing $arg_filename\n";
		if(!$verbose)
			echo "* See encode log at $arg_logfile\n";
		if(file_exists($filename))
			unlink($filename);

		goto next_episode;

	}

	next_episode:

}

	$dart_status = '';
