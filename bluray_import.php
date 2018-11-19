#!/usr/bin/php
<?php

	if($argc == 1) {

		if(!file_exists('bluray_json')) {

			echo "Need bluray_json file\n";
			exit(1);

			/*
			exec('bluray_info --json', $output, $retval);

			if($retval !== 0) {
				echo "bluray_info failed\n";
				exit(1);
			}
			*/

			$contents = implode("\n", $output);

		}

	} else {

		if(!file_exists($argv[1])) {
			echo "Filename ".$argv[1]." does not exist\n";
			exit(1);
		}

		$contents = file_get_contents($argv[1]);

	}

	$json = json_decode($contents, true);

	if(!$json) {
		echo "Could not decode JSON\n";
		exit(1);
	}

	$disc_id = strtolower($json['bluray']['disc id']);
	$disc_title = $json['bluray']['disc title'];

	echo "[Blu-ray]\n";
	echo "Title:		$disc_title\n";
	echo "Disc id:	$disc_id\n";

	$pdo_dsn = "pgsql:host=dlna;dbname=dvds;user=steve";
	$pg = new PDO($pdo_dsn);
	$pg->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

	$sql = "SELECT id FROM dvds WHERE dvdread_id = '$disc_id' AND title = ".$pg->quote($disc_title).";";
	$rs = $pg->query($sql);
	$dvd_id = $rs->fetchColumn();
	if(!$dvd_id) {
		$sql = "INSERT INTO dvds (dvdread_id, title, side) VALUES (".$pg->quote($disc_id).", ".$pg->quote($disc_title).", 1);";
		$pg->query($sql);
		$rs = $pg->query("SELECT id FROM dvds WHERE dvdread_id = '$disc_id';");
		$dvd_id = $rs->fetchColumn();
	}

	$main_playlist = $json['bluray']['main playlist'];

	$bluray_filesize_mbs = 0;

	echo "[Titles]\n";

	foreach($json['titles'] as $arr_title) {

		// print_r($arr_title);

		extract($arr_title);

		echo "Title $title:	Length: $length Filesize: $filesize\n";

		$sql = "SELECT id FROM tracks WHERE dvd_id = $dvd_id AND ix = $title;";
		$rs = $pg->query($sql);
		$track_id = $rs->fetchColumn();

		$seconds = abs(intval($msecs / 100));

		$filesize_mbs = $filesize / 1024;
		$bluray_filesize_mbs += $filesize_mbs;

		$video_codec = $video[0]['codec'];
		$aspect_ratio = $video[0]['aspect ratio'];
		$resolution = $video[0]['format'];
		$tag = '';

		if($playlist == $main_playlist)
			$tag = 'main';

		if(!$track_id) {
			$sql = "INSERT INTO tracks (dvd_id, ix, length, aspect, valid, codec, filesize, closed_captioning, tag, playlist) VALUES ($dvd_id, $title, $seconds, ".$pg->quote($aspect_ratio).", 1, ".$pg->quote($video_codec).", $filesize_mbs, 0, '$tag', $playlist);";
			$rs = $pg->query($sql);
			$sql = "SELECT id FROM tracks WHERE dvd_id = $dvd_id AND ix = $title;";
			$rs = $pg->query($sql);
			$track_id = $rs->fetchColumn();
		}

		// Update old information
		$sql = "SELECT aspect FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			$sql = "UPDATE tracks SET aspect = ".$pg->quote($aspect_ratio)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT codec FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			$sql = "UPDATE tracks SET codec = ".$pg->quote($video_codec)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT resolution FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			$sql = "UPDATE tracks SET resolution = ".$pg->quote($resolution)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT filesize FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			$sql = "UPDATE tracks SET filesize = $filesize WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT tag FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str && ($playlist == $main_playlist)) {
			$sql = "UPDATE tracks SET tag = 'main' WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT playlist FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(is_null($str)) {
			$sql = "UPDATE tracks SET playlist = $playlist WHERE id = $track_id;";
			$pg->query($sql);
		}

		foreach($arr_title['audio'] as $arr_audio) {

			extract($arr_audio);

			$sql = "SELECT id FROM audio WHERE track_id = $track_id AND ix = $track;";
			$rs = $pg->query($sql);
			$audio_id = $rs->fetchColumn();

			$channels = 0;

			if($format == 'mono')
				$channels = 1;
			elseif($format == 'stereo')
				$channels = 2;

			// Update information
			if($audio_id) {

				$sql = "SELECT streamid FROM audio WHERE id = $audio_id;";
				$rs = $pg->query($sql);
				$str = $rs->fetchColumn();
				if(!$str) {
					$sql = "UPDATE audio SET streamid = ".$pg->quote($stream)." WHERE id = $audio_id;";
					$pg->query($sql);
				}

				$sql = "SELECT channels FROM audio WHERE id = $audio_id;";
				$rs = $pg->query($sql);
				$str = $rs->fetchColumn();
				if($str == 0 && $channels) {
					$sql = "UPDATE audio SET channels = $channels WHERE id = $audio_id;";
					echo "$sql\n";
					$pg->query($sql);
				}

			}

			if($audio_id)
				continue;

			$sql = "INSERT INTO audio (track_id, ix, langcode, format, channels, streamid, active) VALUES ($track_id, $track, ".$pg->quote($language).", ".$pg->quote($codec).", $channels, ".$pg->quote($stream).", 1);";
			echo "$sql\n";
			$pg->query($sql);

		}

		foreach($arr_title['subtitles'] as $arr_subtitles) {

			extract($arr_subtitles);

			$sql = "SELECT id FROM subp WHERE track_id = $track_id AND ix = $track;";
			$rs = $pg->query($sql);
			$subp_id = $rs->fetchColumn();

			if($subp_id)
				continue;

			$sql = "INSERT INTO subp (track_id, ix, langcode, streamid, active) VALUES ($track_id, $track, ".$pg->quote($language).", ".$pg->quote($stream).", 1);";
			$pg->query($sql);

		}

		foreach($arr_title['chapters'] as $arr_chapter) {

			extract($arr_chapter);

			$sql = "SELECT id FROM chapters WHERE track_id = $track_id AND ix = $chapter;";
			$rs = $pg->query($sql);
			$chapter_id = $rs->fetchColumn();

			if($chapter_id)
				continue;

			$sql = "INSERT INTO chapters (track_id, ix, length, startcell) VALUES ($track_id, $chapter, ".($msecs / 100).", $chapter);";
			echo "$sql\n";
			$pg->query($sql);

		}

	}

	if($bluray_filesize_mbs) {
		$sql = "UPDATE dvds SET filesize = $bluray_filesize_mbs WHERE dvdread_id = ".$pg->quote($disc_id).";";
		$pg->query($sql);
	}
