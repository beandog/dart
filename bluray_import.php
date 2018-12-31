#!/usr/bin/php
<?php

	require_once 'config.local.php';
	require_once 'inc.mdb2.php';
	require_once 'models/dbtable.php';
	require_once 'models/blurays.php';
	$blurays_model = new Blurays_Model;

	if($argc > 2) {
		echo "Syntax: bluray_import [device]\n";
		exit(1);
	}

	$device = "/dev/sr0";

	if($argc == 2)
		$device = $argv[1];

	$device = realpath($device);

	$bluray_drive = true;

	if(is_dir($device))
		$bluray_drive = false;

	$bluray_info = "bluray_info --json $device";

	echo "[Blu-ray]\n";
	echo "- $bluray_info\n";

	exec($bluray_info, $output, $retval);
	$contents = implode(" ", $output);

	$json = json_decode($contents, true);

	if(!$json) {
		echo "Could not decode JSON\n";
		exit(1);
	}


	// Cleanup JSON
	$json['bluray']['udf title'] = $json['bluray']['udf title'];

	$dvd_id = null;
	$bluray_id = null;

	$disc_title = $json['bluray']['disc title'];

	// Reference using XML md5sums
	$eng_xml_file = "$device/BDMV/META/DL/bdmt_eng.xml";
	$mcmf_xml_file = "$device/MAKEMKV/AACS/mcmf.xml";
	$bdmv_index_file = "$device/BDMV/index.bdmv";

	$bluray_xml_id = '';
	$eng_xml_md5 = '';
	$mcmf_xml_md5 = '';
	$bdmv_index_md5 = '';

	if(file_exists($eng_xml_file)) {
		$eng_xml_md5 = md5_file($eng_xml_file);
		$bluray_xml_id = $eng_xml_md5;
	}
	if(file_exists($mcmf_xml_file)) {
		$mcmf_xml_md5 = md5_file($mcmf_xml_file);
		if(!$bluray_xml_id)
			$bluray_xml_id = $mcmf_xml_md5;
	}
	if(file_exists($bdmv_index_file)) {
		$bdmv_index_md5 = md5_file($bdmv_index_file);
		if(!$bluray_xml_id)
			$bluray_xml_id = $bdmv_index_md5;
	}

	$disc_id = strtolower($json['bluray']['disc id']);

	// Insert new metadata

	// Find existing entry
	$legacy_md5_id = $blurays_model->load_legacy_md5($bluray_xml_id);
	$disc_id_id = $blurays_model->load_disc_id($disc_id);

	echo "[Metadata]\n";

	// Prefer disc ID first, then legacy md5 hash
	if($disc_id_id) {
		echo "* Using disc id for lookup: '$disc_id'\n";
		$bluray_id = $disc_id_id;
	} elseif($legacy_md5_id) {
		echo "* Using legacy MD5 hash for lookup $bluray_xml_id\n";
		$bluray_id = $legacy_md5_id;
	}

	if(!$bluray_id) {
		echo "Creating a new Blu-ray and DVD ids at the same time not supported yet\n";
		// $bluray_id = $blurays_model->new_from_dvd_id($dvd_id);
		exit(1);
	}

	echo "* Bluray ID: $bluray_id\n";

	$blurays_model->load($bluray_id);

	$latest_metatadata_spec = 3;

	if($blurays_model->metadata_spec < 3) {
		echo "* Updating disc title: '$disc_title'\n";
		$blurays_model->disc_title = $disc_title;
	}

	if($json['bluray']['disc id'] && !$blurays_model->disc_id) {
		$disc_id = strtolower($json['bluray']['disc id']);
		echo "* Updating disc ID: '$disc_id'\n";
		$blurays_model->disc_id = $disc_id;
	}

	if($blurays_model->metadata_spec < 3 && $bluray_drive) {
		$udf_title = $json['bluray']['udf title'];
		echo "* Updating legacy volname: '$udf_title'\n";
		$blurays_model->legacy_volname = $udf_title;
	}

	if($eng_xml_md5 && !$blurays_model->eng_xml_md5) {
		echo "* Updating eng_xml_md5: '$eng_xml_md5'\n";
		$blurays_model->eng_xml_md5 = $eng_xml_md5;
	}

	if($mcmf_xml_md5 && !$blurays_model->mcmf_xml_md5) {
		echo "* Updating mcmf_xml_md5: '$mcmf_xml_md5'\n";
		$blurays_model->mcmf_xml_md5 = $mcmf_xml_md5;
	}

	if($bdmv_index_md5 && !$blurays_model->bdmv_index_md5) {
		echo "* Updating bdmv_index_md5: '$bdmv_index_md5'\n";
		$blurays_model->bdmv_index_md5 = $bdmv_index_md5;
	}

	if(is_null($blurays_model->first_play_supported)) {
		echo "* Updating first play supported\n";
		$blurays_model->first_play_supported = intval($json['bluray']['first play supported']);
	}

	if(is_null($blurays_model->top_menu_supported)) {
		echo "* Updating top menu supported\n";
		$blurays_model->top_menu_supported = intval($json['bluray']['top menu supported']);
	}

	if(is_null($blurays_model->has_3d_content)) {
		echo "* Updating 3D content\n";
		$blurays_model->has_3d_content = intval($json['bluray']['3D content']);
	}

	if(is_null($blurays_model->bdinfo_titles)) {
		echo "* Updating bdinfo titles\n";
		$blurays_model->bdinfo_titles = intval($json['bluray']['bdinfo titles']);
	}

	if(is_null($blurays_model->bdj_titles)) {
		echo "* Updating bdj titles\n";
		$blurays_model->bdj_titles = intval($json['bluray']['bdj titles']);
	}

	if(is_null($blurays_model->hdmv_titles)) {
		echo "* Updating HDMV titles\n";
		$blurays_model->hdmv_titles = intval($json['bluray']['hdmv titles']);
	}

	$dvd_id = $blurays_model->dvd_id;

	if(!$dvd_id) {

		echo "No DVD id exists, create a new one\n";
		exit(1);

	}

	echo "* DVD ID: $dvd_id\n";

	$bluray_filesize_mbs = 0;

	if(is_dir($device)) {
		$arr = exec("du -s $device 2> /dev/null");
		$bluray_filesize_mbs = intval(current(explode(' ', $arr)));
		$bluray_filesize_mbs = round($bluray_filesize_mbs / 1024);
	}

	echo "[Blu-ray]\n";
	echo "Title:	'$disc_title'\n";
	if(!$bluray_drive)
		echo "XML id:\t'$bluray_xml_id'\n";

	$pdo_dsn = "pgsql:host=dlna;dbname=dvds;user=steve";
	$pg = new PDO($pdo_dsn);
	$pg->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

	/*
	echo "Import:\tImporting new disc\n";
	$sql = "INSERT INTO dvds (dvdread_id, title, side, filesize, bluray, metadata_spec) VALUES ($q_bluray_xml_id, ".$pg->quote($disc_title).", 1, $bluray_filesize_mbs, 1, 1);";
	$rs = $pg->query($sql);
	$sql = "SELECT id FROM dvds WHERE dvdread_id = $q_bluray_xml_id";
	$rs = $pg->query($sql);
	$dvd_id = $rs->fetchColumn();
	$metadata_spec = 1;
	*/

	$main_playlist = $json['bluray']['main playlist'];

	if($bluray_filesize_mbs) {
		$sql = "UPDATE dvds SET filesize = $bluray_filesize_mbs WHERE id = $dvd_id;";
		$pg->query($sql);
	}

	echo "[Playlists]\n";

	foreach($json['titles'] as $arr_title) {

		// print_r($arr_title);

		extract($arr_title);

		$d_playlist = str_pad($playlist, 4, 0, STR_PAD_LEFT);

		$d_filesize = str_pad(round($filesize / 1024 / 1024), 5, ' ', STR_PAD_LEFT). " MBs";

		// echo "Playlist $d_playlist: Length: $length Filesize: $d_filesize\n";

		$sql = "SELECT id FROM tracks WHERE dvd_id = $dvd_id AND ix = $playlist;";
		$rs = $pg->query($sql);
		$track_id = $rs->fetchColumn();

		$seconds = abs(intval($msecs / 100));

		$filesize_mbs = $filesize / 1024;

		$video_codec = $video[0]['codec'];
		$aspect_ratio = $video[0]['aspect ratio'];
		$resolution = $video[0]['format'];
		$tag = '';

		if($playlist == $main_playlist)
			$tag = 'main';

		if(!$track_id) {
			echo "Playlist $d_playlist: Length: $length Filesize: $d_filesize\n";
			echo "- Inserting new playlist $playlist.mpls\n";
			$sql = "INSERT INTO tracks (dvd_id, ix, length, aspect, valid, codec, filesize, closed_captioning, tag) VALUES ($dvd_id, $playlist, $seconds, ".$pg->quote($aspect_ratio).", 1, ".$pg->quote($video_codec).", $filesize_mbs, 0, '$tag');";
			$rs = $pg->query($sql);
			$sql = "SELECT id FROM tracks WHERE dvd_id = $dvd_id AND ix = $playlist;";
			$rs = $pg->query($sql);
			$track_id = $rs->fetchColumn();
		}

		// Update old information
		$sql = "SELECT aspect FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			echo "- Updating playlist $d_playlist aspect ratio $aspect_ratio\n";
			$sql = "UPDATE tracks SET aspect = ".$pg->quote($aspect_ratio)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT codec FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			echo "- Updating playlist $d_playlist video codec $video_codec\n";
			$sql = "UPDATE tracks SET codec = ".$pg->quote($video_codec)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT resolution FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			echo "- Updating playlist $d_playlist resolution $resolution\n";
			$sql = "UPDATE tracks SET resolution = ".$pg->quote($resolution)." WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT filesize FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str) {
			echo "- Updating playlist $d_playlist filesize ".number_format($filesize)." MBs\n";
			$sql = "UPDATE tracks SET filesize = $filesize WHERE id = $track_id;";
			$pg->query($sql);
		}

		$sql = "SELECT tag FROM tracks WHERE id = $track_id;";
		$rs = $pg->query($sql);
		$str = $rs->fetchColumn();
		if(!$str && ($playlist == $main_playlist)) {
			echo "- Flagging playlist $d_playlist as main\n";
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
					echo "- Updating playlist $d_playlist audio stream ix $track\n";
					$sql = "UPDATE audio SET streamid = ".$pg->quote($stream)." WHERE id = $audio_id;";
					$pg->query($sql);
				}

				$sql = "SELECT channels FROM audio WHERE id = $audio_id;";
				$rs = $pg->query($sql);
				$str = $rs->fetchColumn();
				if($str == 0 && $channels) {
					echo "- Updating playlist $d_playlist audio channels\n";
					$sql = "UPDATE audio SET channels = $channels WHERE id = $audio_id;";
					$pg->query($sql);
				}

			}

			if($audio_id)
				continue;

			echo "- Inserting audio track\n";
			$sql = "INSERT INTO audio (track_id, ix, langcode, format, channels, streamid, active) VALUES ($track_id, $track, ".$pg->quote($language).", ".$pg->quote($codec).", $channels, ".$pg->quote($stream).", 1);";
			$pg->query($sql);

		}

		foreach($arr_title['subtitles'] as $arr_subtitles) {

			extract($arr_subtitles);

			$sql = "SELECT id FROM subp WHERE track_id = $track_id AND ix = $track;";
			$rs = $pg->query($sql);
			$subp_id = $rs->fetchColumn();

			if($subp_id)
				continue;

			echo "- Inserting playlist $d_playlist PGS\n";
			$sql = "INSERT INTO subp (track_id, ix, langcode, streamid, active) VALUES ($track_id, $track, ".$pg->quote($language).", ".$pg->quote($stream).", 1);";
			$pg->query($sql);

		}

		foreach($arr_title['chapters'] as $arr_chapter) {

			extract($arr_chapter);

			$sql = "SELECT id FROM chapters WHERE track_id = $track_id AND ix = $chapter;";
			$rs = $pg->query($sql);
			$chapter_id = $rs->fetchColumn();

			if($chapter_id) {
				if($metadata_spec == 0) {
					$sql = "SELECT length FROM chapters WHERE id = $chapter_id;";
					$rs = $pg->query($sql);
					$length = $rs->fetchColumn();

					if($length != ($duration / 100)) {
						$sql = "UPDATE chapters SET length = ".($duration / 100)." WHERE id = $chapter_id;";
						$pg->query($sql);
						echo "- Updating playlist $d_playlist legacy metadata - chapter $chapter length\n";
					}
				}
				continue;
			}

			echo "- Inserting chapter\n";
			$sql = "INSERT INTO chapters (track_id, ix, length, startcell) VALUES ($track_id, $chapter, ".($duration / 100).", $chapter);";
			// echo "$sql\n";
			$pg->query($sql);

		}

	}

	$blurays_model->metadata_spec = 2;
