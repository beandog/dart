<?php

	/** Matroska Metadata */

	$matroska = new Matroska();

	$matroska->setTitle($episode->metadata['episode_title']);

	$matroska->addTag();
	$matroska->addTarget(70, "COLLECTION");
	$matroska->addSimpleTag("TITLE", $episode->metadata['series_title']);
	$matroska->addSimpleTag("PRODUCTION_STUDIO", $episode->metadata['production_studio']);
	$matroska->addSimpleTag("DATE_RELEASE", $episode->metadata['production_year']);
	$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");

	// Tag MKV with latest spec I've created
	$matroska->addSimpleTag("ENCODING_SPEC", "dlna-usb-5");

	// Metadata specification DVD-MKV-1
	$matroska->addSimpleTag("METADATA_SPEC", "DVD-MKV-1");
	$matroska->addSimpleTag("DVD_COLLECTION", $episode->metadata['collection_title']);
	$matroska->addSimpleTag("DVD_SERIES_SEASON", $episode->metadata['episode_season']);
	$matroska->addSimpleTag("DVD_SERIES_VOLUME", $episode->metadata['series_dvds_volume']);
	$matroska->addSimpleTag("DVD_TRACK_NUMBER", $episode->metadata['track_ix']);
	$matroska->addSimpleTag("DVD_EPISODE_NUMBER", $episode->metadata['episode_number']);
	$matroska->addSimpleTag("DVD_EPISODE_TITLE", $episode->metadata['episode_title']);
	$matroska->addSimpleTag("DVD_EPISODE_PART_NUMBER", $episode->metadata['episode_part']);
	$matroska->addSimpleTag("DVD_ID", $episode->metadata['dvd_id']);
	$matroska->addSimpleTag("DVD_SERIES_ID", $episode->metadata['series_id']);
	$matroska->addSimpleTag("DVD_TRACK_ID", $episode->metadata['track_id']);
	$matroska->addSimpleTag("DVD_EPISODE_ID", $episode_id);

	/** Season **/
	if($episode->metadata['episode_season']) {

		$matroska->addTag();
		$matroska->addTarget(60, "SEASON");

		if($episode->metadata['episode_year']) {
			$matroska->addSimpleTag("DATE_RELEASE", $episode->metadata['episode_year']);
		}

		$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_season']);

	}

	/** Episode **/
	$matroska->addTag();
	$matroska->addTarget(50, "EPISODE");
	$matroska->addSimpleTag("TITLE", $episode->metadata['episode_title']);
	$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_number']);
	$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
	$matroska->addSimpleTag("PLAY_COUNTER", 0);

	if($episode->metadata['episode_part'] > 1) {
		$matroska->addTag();
		$matroska->addTarget(40, "PART");
		$matroska->addSimpleTag("PART_NUMBER", $episode->metadata['episode_part']);
	}

	// Create the Matroska XML file if we are ready on the first run,
	// or if it failed on a previous one.
	if($episode->xml_ready() || $episode->xml_failed()) {

		// Create queue directory if not already there
		$episode->create_queue_dir();

		// Flag creating XML status as "in progress"
		$queue_model->set_episode_status($episode_id, 'xml', 1);

		$xml = $matroska->getXML();

		if($xml) {

			file_put_contents($episode->queue_matroska_xml, $xml);
			$queue_model->set_episode_status($episode_id, 'xml', 2);
			echo "Metadata:\tpassed\n";

		} else {

			// Creating the XML file failed for some reason
			$queue_model->set_episode_status($episode_id, 'xml', 3);
			echo "Metadata:\tfailed\n";

		}

	}