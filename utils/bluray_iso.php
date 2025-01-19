#!/usr/bin/php
<?php

	$hostname = gethostname();

	if($argc != 4) {
		echo "Syntax: bluray_iso <volume name> <source directory> <target iso>\n";
		exit(1);
	}

	$volname = escapeshellarg($argv[1]);

	$bkup_dir = escapeshellarg($argv[2]);

	$target_iso = escapeshellarg($argv[3]);

	if(!is_dir($argv[2])) {
		echo "$bkup_dir is not a directory\n";
		exit(1);
	}

	if(!is_dir($argv[2]."/BDMV/")) {
		echo "BDMV directory doesn't exist\n";
		exit(1);
	}

	/*
	echo "* volume name: $volname\n";
	echo "* directory:   $bkup_dir\n";
	echo "* target iso:  $target_iso\n";
	*/

	if($hostname == 'tobe')
		$cmd = "mkisofs -verbose -posix-L -iso-level 3 -input-charset utf-8 -udf -volid $volname -o $target_iso $bkup_dir";
	else
		$cmd = "mkisofs -verbose -posix-L -iso-level 3 -input-charset utf-8 -allow-limited-size -udf -volid $volname -o $target_iso $bkup_dir";

	echo "$cmd\n";

	passthru($cmd, $retval);

	exit($retval);

