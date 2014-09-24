<?php

	/** Matroska Metadata */

	$matroska = new Matroska();

	$matroska->setTitle($episode->metadata['episode_title']);

	$matroska->addTag();
	$matroska->addSimpleTag("encoding_spec", "dlna-usb-5");
	$matroska->addSimpleTag("metadata_spec", "dvd-mkv-3");
	$matroska->addSimpleTag("handbrake_version", $handbrake_version);
	$matroska->addSimpleTag("collection_title", $episode->metadata['collection_title']);
	$matroska->addSimpleTag("series_title", $episode->metadata['series_title']);
	$matroska->addSimpleTag("dvd_id", $episode->metadata['dvd_id']);
	$matroska->addSimpleTag("episode_id", $episode_id);

	// See http://matroska.org/files/tags/simpsons-s01e01.xml for an example of a DVD
	// from a TV show series.

	$matroska->addTag();
	$matroska->addTarget(70, "COLLECTION");
	$matroska->addSimpleTag("TITLE", $episode->metadata['series_title']);
	$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");

	/** Season **/
	if($episode->metadata['episode_season']) {

		$matroska->addTag();
		$matroska->addTarget(60, "SEASON");

		if($episode->metadata['episode_year']) {
			$matroska->addSimpleTag("DATE_RELEASE", $episode->metadata['episode_year']);
		}

		if($episode->metadata['episode_season'])
			$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_season']);

	}

	/** Episode **/
	$matroska->addTag();
	$matroska->addTarget(50, "EPISODE");
	$matroska->addSimpleTag("TITLE", $episode->metadata['episode_title']);
	if($episode->metadata['episode_number'])
		$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_number']);
	$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
	$matroska->addSimpleTag("PLAY_COUNTER", 0);

	if($episode->metadata['episode_part'] > 1) {
		$matroska->addTag();
		$matroska->addTarget(40, "PART");
		$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_part']);
	}

	$metadata_xml = $matroska->getXML();

	$bool = $matroska->addFile($episode->queue_handbrake_x264);
	if(!$bool)
		echo "* Adding media file ".$episode->queue_handbrake_x264." to Matroska object FAILED\n";

	$bool = $matroska->addGlobalTags($episode->queue_matroska_xml);
	if(!$bool)
		echo "* Adding global tags file ".$episode->queue_matroska_xml." to Matroska object FAILED\n";

	$matroska->setFilename($episode->queue_matroska_mkv);

	$remux_stage_command = $matroska->getCommandString();

