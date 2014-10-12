<?php

	/** Matroska Metadata */

	$matroska = new Matroska();
	$matroska->setFilename($queue_files['mkvmerge_output_filename']);

	$matroska->setTitle($episode_title);

	$matroska->addTag();
	$matroska->addSimpleTag("encoding_spec", "dlna-usb-5");
	$matroska->addSimpleTag("metadata_spec", "dvd-mkv-3");
	$matroska->addSimpleTag("handbrake_version", $handbrake_version);
	$matroska->addSimpleTag("collection_title", $collection_title);
	$matroska->addSimpleTag("series_title", $series_title);
	$matroska->addSimpleTag("dvd_id", $episode['dvd_id']);
	$matroska->addSimpleTag("episode_id", $episode_id);

	// See http://matroska.org/files/tags/simpsons-s01e01.xml for an example of a DVD
	// from a TV show series.

	$matroska->addTag();
	$matroska->addTarget(70, "COLLECTION");
	$matroska->addSimpleTag("TITLE", $series_title);
	$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");

	/** Episode **/
	$matroska->addTag();
	$matroska->addTarget(50, "EPISODE");
	$matroska->addSimpleTag("TITLE", $episode_title);
	if($episode_number)
		$matroska->addSimpleTag("PART_NUMBER", $episode_number);
	$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
	$matroska->addSimpleTag("PLAY_COUNTER", 0);

	if($episode_part > 1) {
		$matroska->addTag();
		$matroska->addTarget(40, "PART");
		$matroska->addSimpleTag("PART_NUMBER", $episode['part']);
	}

	// Files will not exist at this point, so don't check for its existence
	$matroska->addFile($queue_files['handbrake_output_filename']);
	$matroska->addGlobalTags($queue_files['metadata_xml_file']);

	$matroska_xml = $matroska->getXML();
	$matroska_xml = mb_convert_encoding($matroska_xml, 'UTF-8');
	$mkvmerge_command = $matroska->getCommandString();

