<?php

	/** Matroska Metadata */

	$matroska = new Matroska();

	$matroska->setTitle($episode->metadata['episode_title']);

	$matroska->addTag();
	$matroska->addSimpleTag("encode_date", date("Y-m-d"));
	$matroska->addSimpleTag("play_counter", 0);
	$matroska->addSimpleTag("encoding_spec", "dlna-usb-5");
	$matroska->addSimpleTag("metadata_spec", "dvd-mkv-3");
	$matroska->addSimpleTag("collection_title", $episode->metadata['collection_title']);
	$matroska->addSimpleTag("series_title", $episode->metadata['series_title']);
	if($episode->metadata['episode_season'])
		$matroska->addSimpleTag("season", $episode->metadata['episode_season']);
	if($episode->metadata['episode_number'])
		$matroska->addSimpleTag("episode_number", $episode->metadata['episode_number']);
	if($episode->metadata['episode_part'])
		$matroska->addSimpleTag("episode_part", $episode->metadata['episode_part']);
	$matroska->addSimpleTag("production_year", $episode->metadata['production_year']);
	$matroska->addSimpleTag("episode_id", $episode_id);

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
			$encodes_model->remux_metadata = mb_convert_encoding($xml, 'UTF-8');

		} else {

			// Creating the XML file failed for some reason
			$queue_model->set_episode_status($episode_id, 'xml', 3);
			echo "Metadata:\tfailed\n";

		}

	}
