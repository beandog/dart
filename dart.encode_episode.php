<?php

if($disc_type == 'dvd' && ($opt_encode || $opt_copy)) {

	$dart_status = 'encode_episode';

	if($opt_skip_existing && file_exists($filename))
		goto next_episode;

	$cmd = "pgrep -af '^(HandBrakeCLI|rsync|emo|ffmpeg|dvd_copy)'";
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

	$arr_episode_titles = $episodes_model->get_episode_titles();
	$episode_display_title = $arr_episode_titles['display_title'];

	echo "[Encoding]\n";
	echo "* $epiosde_display_title\n";
	echo "* Source: $arg_device\n";
	echo "* Target: $arg_filename\n";

	$logfile = "/tmp/".basename($filename).".log";
	$arg_logfile = escapeshellarg($logfile);

	$encode_command = '';

	if($dvd_encoder == 'ffmpeg')
		$encode_command = $ffmpeg_command;

	if($disc_type == 'dvd' && $dvd_encoder == 'dvd_copy')
		$encode_command = $dvd_copy_command;

	if($dvd_encoder == 'handbrake')
		$encode_command = $handbrake_command;

	if($verbose)
		echo "* $encode_command\n";

	if($dvd_encoder == 'handbrake' && !$verbose)
		$encode_command .= " 2> $arg_logfile";

	$retval = -1;
	if($encode_command)
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
