#!/usr/bin/php
<?php

	require_once 'config.local.php';
	require_once 'config.pgconn.php';
	require_once 'inc.mdb2.php';
	require_once 'dart.functions.php';

	require_once 'Console/ProgressBar.php';

	require_once 'class.dvd.php';
	require_once 'class.dvddrive.php';
	require_once 'class.dvdvob.php';
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
	require_once 'models/encodes.php';
	require_once 'models/queue.php';

	require_once 'dart.parser.php';

	/** Start everything **/
	$all_devices = array('/dev/dvd');
	$export_dir = getenv('HOME').'/dvds/';
	$ifo_export_dir = $export_dir.'ifos/';
	$hostname = php_uname('n');


	// Handle parser arguments and options
	if($debug)
		$verbose = 10;

	if($opt_dry_run)
		$dry_run = true;
	else
		$dry_run = false;

	// Dumping the ISO may be necessary at some point in the encode process,
	// so handle the request and the action separately.
	if($opt_dump_iso)
		$dump_iso = true;
	else
		$dump_iso = false;

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

	if(!count($devices) && ($opt_rip || $opt_info || $dump_iso || $opt_dump_ifo || $opt_import || $opt_archive))
		$devices = $all_devices;

	// Manage queue
	$queue_model = new Queue_Model;
	$queue_model->set_hostname($hostname);
	if($skip)
		$queue_model->skip_episodes($skip);
	if($max)
		$queue_model->set_max_episodes($max);
	if($queue_episode_id)
		$queue_model->set_episode_id($queue_episode_id);
	if($queue_track_id)
		$queue_model->set_track_id($queue_track_id);
	if($queue_dvd_id)
		$queue_model->set_dvd_id($queue_dvd_id);
	if($queue_series_id)
		$queue_model->set_series_id($queue_series_id);
	if($opt_random)
		$queue_model->set_random();
	if($remove_queue)
		$queue_model->remove();
	if($reset_queue)
		$queue_model->reset();
	if($queue_episode_id)
		$queue_episode_id = abs(intval($queue_episode_id));

	// General boolean for various items
	$first_run = true;

	$arr_queue_status = array('ready', 'in progress', 'passed', 'failed');

	$num_encoded = 0;

	foreach($devices as $device) {

		start:

		if($debug) {
			echo "[Initialization]\n";
			echo "* Device: $device\n";
		}

		clearstatcache();

		$dvds_model = new Dvds_Model;
		$dvd_episodes = array();

		// Internal flags if there are issues impossible to circumvent
		$broken_dvd = false;

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
			goto next_device;
		}

		// Device name to display to stdout
		$display_device = $device;
		if($device_is_iso)
			$display_device = basename($device);

		// Determine whether we are reading the device
		if($opt_rip || $opt_info || $opt_import || $opt_archive || $dump_iso || $opt_dump_ifo || $qa) {
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

			/**
			 * Writing all these logic checks independently makes it so my head
			 * doesn't hurt while trying to parse multiple conditions.  However, each
			 * conditional check needs to see if access to the device is disabled at
			 * that point, since it's the one variable that is changed by the previous
			 * checks.
			 */

			// What happens in a scenario where the user passes a command (rip, info,
			// iso, etc. but there is no media in the tray.  Should the program assume
			// that the user wants to put something in there, and so it opens up?  Or
			// should it only quietly continue and ignore the device?  I'm going to go
			// with the assumption for now that if dart is specifically told to access
			// *this device*, then it is intended to have media in it.  If there's none
			// in there, do the courtesy of opening the tray so that the user doesn't
			// have to eject it manually.  A possible option is also to check for the
			// --open option given, and only eject the tray in that case.
			if(!$wait && !$tray_open && !$tray_has_media && $access_device) {

				// The device was included in the main program call, so eject
				// the tray if there is no media in there.
				$drive->open();
				$access_device = false;
			}

			// If waiting and the drive is closed and has no media, go to the next device
			if($wait && !$tray_open && !$tray_has_media && $access_device) {
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
					echo "* Expected media, didn't find any!?\n";
					$tray_open = $drive->open();
					$access_device = false;

					// This is *possibly* a case where the DVD drive is closed,
					// with media physically present, but the drive doesn't see
					// it for whatever reason.  If that's the case, alert the
					// user to the anomaly.
					beep_error();

				}

			}

		}

		if($access_device) {

			echo "[DVD]\n";

			$dvd = new DVD($device, $debug);

			if(!$dvd->opened) {
				echo "* Opening $device FAILED\n";
				goto next_device;
			}

			$device_filesize = $dvd->size;
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				echo "* DVD size reported as zero! Aborting\n";
				$access_device = false;

				// This is a interruption to the workflow, so annoy the user
				beep_error();

				goto next_device;
			}

			echo "* Filesize:\t$display_filesize MB\n";

			// Get the uniq ID for the disc
			$dvdread_id = $dvd->dvdread_id;
			$dvd_title = $dvd->title;
			echo "* Title:\t$dvd_title\n";
			echo "* dvdread id:\t$dvdread_id\n";

			// Lookup the database dvds.id
			echo "[Database]\n";
			$dvds_model_id = $dvds_model->find_dvdread_id($dvdread_id);

			// Found a new disc if it's not in the database!
			if(!$dvds_model_id) {
				echo "* DVD not found, ready to import!";
			}

			if($dvds_model_id) {

				$dvds_model->load($dvds_model_id);

				$series_title = $dvds_model->get_series_title();

				echo "* DVD ID:\t$dvds_model_id\n";
				echo "* Series:\t$series_title\n";

				$disc_indexed = true;

			}

			// Only need to display if it's imported if requesting import or
			// getting DVD info.
			if($opt_import || $opt_info) {
				if($disc_indexed) {
					echo "* Imported:\tYes\n";
				} else {
					echo "* Imported:\tNo\n";
				}
			}

		}

		require 'dart.info.php';
		require 'dart.import.php';
		require 'dart.ifo.php';
		require 'dart.iso.php';
		require 'dart.rip.php';

		// goto point for next device -- skip here for any reason that the initial
		// dart calls failed to acess the DVD or device, and it needs to move on.
		next_device:

		// If archiving, everything would have happened by now,
		// so eject the drive.
		if($opt_archive && $device_is_hardware && $drive->is_closed()) {
			echo "* Ready to archive next disc, opening tray!\n";
			$drive->open();
		}

		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($opt_rip || $opt_import || $opt_archive || $dump_iso || $opt_dump_ifo) && $device_is_hardware) {

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
				echo "* Going to start position\n";

			goto start;
		}

	}

	require 'dart.queue.php';
	require 'dart.encode.php';
	require 'dart.qa.php';
	// require 'dart.ftp.php';

?>
