#!/usr/bin/php
<?php

	require_once 'dart.functions.php';

	ini_set('include_path', ini_get('include_path').":/home/steve/git/dart");

	require_once 'Console/ProgressBar.php';

	require_once 'includes/mdb2.php';

	require_once 'class.dvd.php';
	require_once 'class.dvddrive.php';
	require_once 'class.dvdvob.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.dvdaudio.php';
	require_once 'class.dvdsubs.php';
	require_once 'class.matroska.php';
	require_once 'class.handbrake.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/series_dvds.php';
	require_once 'models/series.php';
	require_once 'models/tracks.php';
	require_once 'models/audio.php';
	require_once 'models/subp.php';
	require_once 'models/chapters.php';
	require_once 'models/cells.php';
	require_once 'models/queue.php';

	require_once 'dart.parser.php';
	require_once 'includes/prefs.php';

	/** Start everything **/
	$all_devices = array('/dev/dvd', '/dev/dvd1', '/dev/dvd2', '/dev/dvd3');
	$export_dir = getenv('HOME').'/dvds/';
	$ifo_export_dir = $export_dir.'ifos/';

	// Parser allows multiple levels of verbosity

	// FIXME setting verbose to 1 here because I'm
	// used to the code spitting out the output as normal.
	// Need to review all the code and change verbosity checks
	// if they are too much.
	if(!$verbose)
		$verbose = 1;

	if($debug)
		$verbose = 10;

	// Base URL to access DVD admin frontend
	// Override in preferences
	if(empty($baseurl))
		$baseurl = '';

	if($open_trays && !$close_trays) {
		foreach($all_devices as $str) {
			$drive = new DVDDrive($str);
			$drive->open();
		}
	}

	if($close_trays && !$open_trays) {
		foreach($all_devices as $str) {
			$drive = new DVDDrive($str);
			$drive->close(0);
		}
	}

	if(!count($devices) && ($rip || $info || $dump_iso || $dump_ifo || $import || $archive))
		$devices = $all_devices;

	// Process request to reset the queue
	if($reset_queue) {
		$queue_model = new Queue_Model;
		$queue_model->reset();
	}

	// General boolean for various items
	$first_run = true;

	foreach($devices as $device) {

		start:

		if(!$first_run)
			echo "\n";
		if($debug) {
			echo "[Initialization]\n";
			echo "* Device: $device\n";
		}

		clearstatcache();

		$dvd = new DVD($device);
		$dvd->setDebug($debug);
		$dvds_model = new Dvds_Model;
		$dvd_episodes = array();

		// If disc has an entry in the database
		$disc_indexed = false;

		// If all the disc metadata is in the database
		$disc_archived = false;

		// Is the source filename a block device
		$device_is_hardware = false;

		// Can we poll the file or device
		$access_device = false;

		// If it's DVD drive, can it be accessed
		$access_drive = false;

		// Are we missing any data in the database
		$missing_import_data = false;
		$missing_audio_streams = false;

		// Does the device tray have media
		$has_media = false;

		// Making a run for it! :)
		$first_run = false;

		// Get the real path of the device (no symlinks, full dirname)
		$device_realpath = realpath($device);

		// Check if source filename is a block device or not
		$device_dirname = dirname($device_realpath);
		if($device_dirname == "/dev") {
			$device_is_hardware = true;
			$device_is_iso = false;
		} else {
			$device_is_hardware = false;
			$device_is_iso = true;
		}

		// Verify file exists
		if(!file_exists($device)) {
			echo "* Couldn't find $device\n";
			exit(1);
		}

		// Device name to display to stdout
		$display_device = $device;
		if($device_is_iso)
			$display_device = basename($device);

		// Determine whether we are reading the device
		if($rip || $info || $import || $archive || $dump_iso || $dump_ifo) {
			$access_device = true;
			echo "[Access Device]\n";
			echo "* Reading $display_device\n";
			if($debug)
				echo "* Reading $device_realpath\n";
		}

		// Determine whether we need physical access to a disc.
		// Note that since the next steps are so dependent upon
		// whether 'wait' is true or not, it's easier to just
		// create a whole new block
		if($device_is_hardware && $access_device) {

			$drive = new DVDDrive($device);
			$drive->set_debug($debug);

			if(!$wait || ($wait && $drive->is_closed())) {

				// Close the tray if not waiting (i.e., --import, --info, etc. is passed)
				if(!$wait && $drive->is_open()) {
					$drive->close();
				}

				$has_media = $drive->has_media();

				if($has_media) {
					$access_drive = true;
					echo "* Found a DVD, ready to nom!\n";
				} else {
					echo "* No media, so out we go!\n";
					$drive->open();
					$access_device = false;
				}
			} else {
				// Disable access to the device since the
				// above conditions are not met.
				$access_device = false;
			}
		}

		if($access_device) {

			// Decrypt the CSS to avoid disc access errors
			if($device_is_hardware)
				$dvd->load_css();

			echo "[DVD]\n";

			$device_filesize = $dvd->getSize();
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				echo "* DVD size reported as zero! Aborting\n";
				exit(1);
			}

			echo "* Filesize:\t$display_filesize MB\n";

			// Get the uniq ID for the disc
			$uniq_id = $dvd->getID();
			echo "* Title:\t".$dvd->getTitle()."\n";
			echo "* Disc ID:\t$uniq_id\n";

			// Get the serial ID for the disc
			$serial_id = $dvd->getSerialID();
			echo "* Serial ID:\t$serial_id\n";

			// Lookup the database dvds.id
			echo "[Database]\n";
			$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);

			// Use the serial ID as a unique identifer as well
			if($device_is_iso && !$dvds_model_id) {

				echo "* Lookup on serial ID and disc title: ";

				$tmp_dvds_model = new Dvds_Model;
				$find_serial_id = $tmp_dvds_model->find_id('serial_id', $serial_id);

				if(!$find_serial_id)
					echo "none found; marking as new disc\n";
				else {
					$dvds_model->load($find_serial_id);
					if($dvd->getTitle() == $tmp_dvds_model->title) {
						echo "match found\n";
						$dvds_model_id = $find_serial_id;
						unset($tmp_dvds_model);
					}
				}
			}

			if($dvds_model_id) {

				$series_title = $dvds_model->get_series_title();

				echo "* DVD ID:\t$dvds_model_id\n";
				echo "* Series:\t$series_title\n";

				$disc_indexed = true;

				$dvds_model->load($dvds_model_id);

			}

			if($disc_indexed) {
				echo "* Indexed:\tYes\n";
			} else {
				echo "* Unindexed:\tNo\n";
			}

		}

		require 'dart.info.php';
		require 'dart.archive.php';
		require 'dart.import.php';
		require 'dart.ifo.php';
		require 'dart.iso.php';
		require 'dart.rip.php';

		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($rip || $import || $archive || $dump_iso || $dump_ifo) && $device_is_hardware) {
			// Only toggle devices if passed more than one
			// Otherwise, just re-poll the original.
			// This is useful in cases where --wait is called
			// on two separate devices, so two drives can
			// be accessed at the same time
			if(count($devices) > 1) {
				$device = toggle_device($device);
			}
			// If there is only one device, then wait until the tray is
			// closed.
			else {
				$drive->close(false);
			}

			if($debug)
				echo "! Going to start position\n";

			goto start;
		}

	}

	require 'dart.queue.php';
	require 'dart.encode.php';
	require 'dart.ftp.php';

?>
