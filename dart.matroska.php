<?php

	/** Matroska Metadata */

	$matroska = new Matroska();

	if($episode->metadata['episode_title'])
		$matroska->setTitle($episode->metadata['episode_title']);

	$episode_metadata = array(
		'track_number' => $episode->metadata['track_ix'],
		'starting_chapter' => $episode->metadata['episode_starting_chapter'],
		'ending_chapter' => $episode->metadata['episode_ending_chapter'],
		'production_studio' => $episode->metadata['production_studio'],
		'production_year' => $episode->metadata['production_year'],
		'season' => $episode->metadata['episode_season'],
		'volume' => $episode->metadata['series_dvds_season'],
		'number' => $episode->metadata['episode_number'],
		'part' => $episode->metadata['episode_part'],
	);

	$matroska->addTag();
	$matroska->addTarget(70, "COLLECTION");
	$matroska->addSimpleTag("TITLE", $episode->metadata['series_title']);
	if($episode_metadata['production_studio'])
		$matroska->addSimpleTag("PRODUCTION_STUDIO", $episode_metadata['production_studio']);
	if($episode_metadata['production_year'])
		$matroska->addSimpleTag("DATE_RELEASE", $episode_metadata['production_year']);
	$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");

	// Tag MKV with latest spec I've created
	$matroska->addSimpleTag("ENCODING_SPEC", "dlna-usb-4");

	// Metadata specification DVD-MKV-1
	$matroska->addSimpleTag("METADATA_SPEC", "DVD-MKV-1");
	$matroska->addSimpleTag("DVD_COLLECTION", $episode->metadata['collection_title']);
	$matroska->addSimpleTag("DVD_SERIES_TITLE", $episode->metadata['series_title']);
	if($episode_metadata['season'])
		$matroska->addSimpleTag("DVD_SERIES_SEASON", $episode_metadata['season']);
	if($episode_metadata['volume'])
		$matroska->addSimpleTag("DVD_SERIES_VOLUME", $episode_metadata['volume']);
	$matroska->addSimpleTag("DVD_TRACK_NUMBER", $episode_metadata['track_number']);
	if($episode_metadata['number'])
		$matroska->addSimpleTag("DVD_EPISODE_NUMBER", $episode_metadata['number']);
	$matroska->addSimpleTag("DVD_EPISODE_TITLE", $episode->metadata['episode_title']);
	if($episode_metadata['part'])
		$matroska->addSimpleTag("DVD_EPISODE_PART_NUMBER", $episode_metadata['part']);
	$matroska->addSimpleTag("DVD_ID", $dvd_id);
	$matroska->addSimpleTag("DVD_SERIES_ID", $series_id);
	$matroska->addSimpleTag("DVD_TRACK_ID", $track_id);
	$matroska->addSimpleTag("DVD_EPISODE_ID", $episode_id);

	/** Season **/
	if($episode_metadata['season']) {

		$matroska->addTag();
		$matroska->addTarget(60, "SEASON");

		if($episode_metadata['production_year']) {
			$episode_metadata['year'] = $episode_metadata['production_year'] + $episode_metadata['season'] - 1;
			$matroska->addSimpleTag("DATE_RELEASE", $episode_metadata['year']);
		}

		$matroska->addSimpleTag("PART_NUMBER", $episode_metadata['season']);

	}

	/** Episode **/
	$matroska->addTag();
	$matroska->addTarget(50, "EPISODE");
	if($episode->metadata['episode_title'])
		$matroska->addSimpleTag("TITLE", $episode->metadata['episode_title']);
	if($episode_metadata['number'])
		$matroska->addSimpleTag("PART_NUMBER", $episode_metadata['number']);
	$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
	$matroska->addSimpleTag("PLAY_COUNTER", 0);

	if($episode_metadata['part'] > 1) {
		$matroska->addTag();
		$matroska->addTarget(40, "PART");
		$matroska->addSimpleTag("PART_NUMBER", $episode_metadata['part']);
	}

	if(!file_exists($episode->queue_matroska_mkv) && !file_exists($episode->queue_matroska_xml) && file_exists($episode->queue_handbrake_x264) && !$dry_run) {

		$queue_model->set_episode_status($episode_id, 3);

		$xml = $matroska->getXML();

		if($xml) {
			file_put_contents($episode->queue_matroska_xml, $xml);
			$matroska_xml_success = true;
		} else {
			// Creating the XML file failed for some reason
			$queue_model->set_episode_status($episode_id, 4);
			$matroska_xml_success = false;
		}

	}
