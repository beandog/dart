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

	require_once 'class.media.file.php';
	require_once 'class.media.iso.php';
	require_once 'class.media.episode.php';

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

	/** Start everything **/
	$all_devices = array('/dev/dvd');
	$export_dir = getenv('HOME').'/dvds/';
	$ifo_export_dir = $export_dir.'ifos/';
	$hostname = php_uname('n');

	require_once 'includes/prefs.php';

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
			$drive->close();
		}
	}

	if(!count($devices) && ($rip || $info || $dump_iso || $dump_ifo || $import || $archive))
		$devices = $all_devices;

	// Process request to reset the queue
	if($reset_queue) {
		$queue_model = new Queue_Model;
		$queue_model->reset($hostname);
	}

	// General boolean for various items
	$first_run = true;

	$arr_queue_status = array('ready', 'in progress', 'passed', 'failed');

	foreach($devices as $device) {

		start:

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
			if(!$wait) {
				echo "[Access Device]\n";
				echo "* Reading $display_device\n";
				if($debug)
					echo "* Reading $device_realpath\n";
			}
		}


		// Look for any conditions where we there is access to the device, but
		// we need to skip over it because there is no media.  Also open the tray
		// based on the wait switch that's passed.
		if($device_is_hardware && $access_device) {

			if($debug) {
				echo "[Device Hardware]\n";
				echo "* Drive $device\n";
			}

			$drive = new DVDDrive($device);
			$drive->set_debug($debug);

			// Poll the devices as few times as necessary to avoid hardware kernel
			// complaints.  Set defaults.
			$tray_open = false;
			$tray_has_media = false;

			// Check if drive is open.
			$tray_open = $drive->is_open();

			// Check if drive has media if it's closed
			if(!$tray_open) {
				$tray_has_media = $drive->has_media();
			}

			if($debug) {
				if($wait)
					echo "* Wait for media requested by user\n";
				else
					echo "* No waiting requested\n";
			}

			// If waiting and the drive is closed and has no media, go to the next device
			if($wait && !$tray_open && !$tray_has_media) {
				echo "* Drive is closed, without media\n";
				echo "* No media, so out we go!\n";
				$tray_open = $drive->open();
				$access_device = false;
			}

			// If waiting, and the drive is open, move along to the next device
			if($wait && $tray_open && $access_device) {
				if($debug)
					echo "* Drive is open, skipping device\n";
				$access_device = false;
			}

			// Close the tray if not waiting
			if(!$wait && $tray_open && !$open_trays && $access_device) {

				echo "* Drive is open, closing tray\n";
				if($drive->close())
					$tray_open = false;

				$tray_has_media = $drive->has_media();

				if($tray_has_media) {
					$access_drive = true;
					echo "* Found a DVD, ready to nom!\n";
				} else {
					echo "* No media, so out we go!\n";
					$tray_open = $drive->open();
					$access_device = false;
				}

			}

		}

		if($access_device) {

			echo "[DVD]\n";

			$device_filesize = $dvd->getSize();
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				echo "* DVD size reported as zero! Aborting\n";
				exit(1);
			}

			echo "* Filesize:\t$display_filesize MB\n";

			// Get the uniq ID for the disc
			$dvdread_id = $dvd->getID();
			echo "* Title:\t".$dvd->getTitle()."\n";
			echo "* Disc ID:\t$dvdread_id\n";

			// Get the serial ID for the disc
			$serial_id = $dvd->getSerialID();
			echo "* Serial ID:\t$serial_id\n";

			// Lookup the database dvds.id
			echo "[Database]\n";
			$dvds_model_id = $dvds_model->find_id('dvdread_id', $dvdread_id);

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
				echo "* Indexed:\tNo\n";
			}

		}

		require 'dart.info.php';
		require 'dart.import.php';
		require 'dart.ifo.php';
		require 'dart.iso.php';
		require 'dart.rip.php';

		// Starting goto point for next DVD
		next_device:

		// If archiving, everything would have happened by now,
		// so eject the drive.
		if($archive && $device_is_hardware && $drive->is_closed()) {
			echo "* Ready to archive next disc, opening tray!\n";
			$drive->open();
		}

		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($rip || $import || $archive || $dump_iso || $dump_ifo) && $device_is_hardware) {

			// Only toggle devices if passed more than one
			// Otherwise, just re-poll the original.
			// This is useful in cases where --wait is called
			// on two separate devices, so two drives can
			// be accessed at the same time
			// Otherwise, if there is only one device, then wait until
			// the tray is closed manually.
			if(count($devices) > 1) {
				$device = toggle_device($devices, $device);
			}

			if($debug)
				echo "! Going to start position\n";

			goto start;
		}

	}

	require 'dart.queue.php';
	require 'dart.encode.php';
	// require 'dart.ftp.php';

?>
