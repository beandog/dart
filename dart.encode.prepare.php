<?php

	$series_title = $episode['series_title'];
	$collection_title = $episode['collection_title']
	$dvd_episode_iso = $episodes_model->get_iso();
	$dvd_source_iso = $export_dir."isos/".safe_filename_title($collection_title)."/".safe_filename_title($series_title)."/$dvd_episode_iso";

	$encodes_model->episode_id = $episode_id;
	$encodes_model->encoder_version = strval($handbrake_version);
	$encodes_model->remux_version = strval($mkvmerge_version);
	$encodes_model->encode_begin = date('%r');

	$episode_title = $episodes_model->get_long_title();

	// Create temporary queue directory, symlinks, files
	$series_queue_dir = $export_dir."queue/".safe_filename_title($series_title);
	$episode_queue_dir = "$series_queue_dir/".safe_filename_title($episode_title);

	// Get filenames
	$queue_files = array(
		'dvd_iso_symlink' => $series_queue_dir."/$dvd_episode_iso",
		'handbrake_script' => $episode_queue_dir."/handbrake.sh",
		'handbrake_log' => $episode_queue_dir."/encode.log",
		'handbrake_output_filename' => $episode_queue_dir."/x264.mkv",
		'metadata_xml_file' => $episode_queue_dir."/matroska.xml",
		'mkvmerge_script' => $episode_queue_dir."/mkvmerge.sh",
		'mkvmerge_log' => $episode_queue_dir."/remux.out",
		'mkvmerge_output_filename' => $episode_queue_dir."/remux.mkv",
	);

	$target_files = array(
		'series_dir' => $export_dir."episodes/".safe_filename_title($series_title),
		'episode_mkv' => $export_dir."episodes/".safe_filename_title($series_title)."/".safe_filename_title($episode_title).".mkv",
	);
