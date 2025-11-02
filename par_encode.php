#!/usr/bin/env php
<?php

	require 'config.local.php';

	require_once 'Console/CommandLine.php';
	$parser = new Console_CommandLine();
	$parser->description = "Encode files in parallel";
	$parser->addArgument('filenames', array('optional' => true, 'multiple' => true));
	$parser->addOption('arg_max_procs', array(
		'short_name' => '-j',
		'description' => 'Run # of jobs at once',
		'action' => 'StoreInt',
		'optional' => true,
		'default' => 8,
	));
	$parser->addOption('arg_max_encodes', array(
		'short_name' => '-f',
		'description' => 'Max # of files to encode',
		'action' => 'StoreInt',
		'optional' => true,
		'default' => 1048576,
	));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'description' => 'Display verbose output',
		'action' => 'StoreTrue',
	));

	try { $result = $parser->parse(); }
	catch(PEAR_Exception $e) {
		echo "Invalid options passed, try --help instead\n";
		exit(1);
	}

	extract($result->args);
	extract($result->options);

	if(!count($filenames))
		exit;

	if($arg_max_encodes < $arg_max_procs)
		$arg_max_procs = $arg_max_encodes;

	if(count($filenames) < $arg_max_procs)
		$arg_max_procs = count($filenames);

	function get_procs() {
		$pgrep_cmd = "pgrep -af '^parallel-ffmpeg' | cut -d ' ' -f 2- | sort | uniq";
		exec($pgrep_cmd, $arr_pgrep_out);
		return array_unique($arr_pgrep_out);
	}

	function get_num_procs() {
		return count(get_procs());
	}

	function get_target_filename($src) {
		$basename = basename($src);
		$str = "v2-$basename";
		return $str;
	}

	$num_filenames = 0;

	$all_args = implode(' ', $argv);

	$pgrep_binary_cmd = "pgrep -af ^parallel-ffmpeg -c";
	$str = shell_exec($pgrep_binary_cmd);
	$num_binary_procs = intval(trim($str));
	if($num_binary_procs) {
		echo "# $num_binary_procs parallel encodes already running\n";
		exit;
	}

	$arr_filenames = array();

	foreach($filenames as $filename) {
		$output_filename = get_target_filename($filename);
		if(file_exists($output_filename))
			continue;
		$arr_filenames[] = $filename;
		$num_filenames++;
		if($num_filenames >= $arg_max_encodes)
			break;
	}

	$arr_filenames = array_slice($arr_filenames, 0, $arg_max_encodes);

	if(!count($arr_filenames))
		exit;

	$arr_encodes = array();
	foreach($arr_filenames as $filename) {
		$filename = realpath($filename);
		$arg_filename = escapeshellarg($filename);
		$reencode_mkv_cmd = "/home/steve/bin/reencode_mkv --batch $arg_filename";
		$arr_output = array();
		exec($reencode_mkv_cmd, $arr_output);
		$encode_cmd = current($arr_output);
		if(strlen($encode_cmd)) {
			$arr_encodes[] = array(
				'filename' => $filename,
				'target' => get_target_filename($filename),
				'encode_cmd' => $encode_cmd,
			);
		}
	}

	$num_encodes = count($arr_encodes);

	echo "# Total files to encode: $num_encodes\n";
	echo "# Max parallel jobs: $arg_max_procs\n";

	$ix = 1;

	while(count($arr_encodes)) {

		$num_procs = get_num_procs();

		if($num_procs < $arg_max_procs) {
			echo "# Currently running: $num_procs\n";
		}

		while(get_num_procs() < $arg_max_procs) {

			$arr_encode = current($arr_encodes);

			$encode_cmd = $arr_encode['encode_cmd'];

			$filename = $arr_encode['filename'];
			if(!file_exists($filename)) {
				echo "File not found: $filename\n";
				array_shift($arr_encodes);
				continue;
			}

			$target = $arr_encode['target'];

			if(file_exists($target)) {
				echo "Encoded file found: $target\n";
				array_shift($arr_encodes);
				continue;
			}

			// Avoid *possible* race conditions, don't wait for ffmpeg to start outputting
			touch($target);

			$progress_file = tempnam("/tmp", "ffmpeg-enc-").".log";

			$encode_cmd = $arr_encode['encode_cmd'];

			$encode_cmd = str_replace('ffmpeg', 'parallel-ffmpeg', $encode_cmd);

			$nohup_cmd = "nohup $encode_cmd -progress $progress_file > /dev/null 2>&1 &";

			echo "# [$ix/$num_encodes] Encoding: $filename\n";
			if($verbose) {
				echo "# $encode_cmd\n";
				echo "# [$ix/$num_encodes] Log file: $progress_file\n";
			}

			if($verbose) {
				passthru($encode_cmd);
			} else {
				shell_exec($nohup_cmd);
			}

			array_shift($arr_encodes);

			$ix++;

			if(!count($arr_encodes) || is_null($arr_encodes)) {
				break;
			}

			sleep(1);

		}

	}

	$num_procs = get_num_procs();

	echo "# $num_procs encodes running in the background\n";

