#!/usr/bin/php
<?php

	// Defaults
	$all_devices = array('/dev/dvd');
	$export_dir = getenv('HOME').'/dvds/';
	$hostname = php_uname('n');
	$container = "mkv";
	$extension = ".mkv";
	$batch_mode = false;

	// Overrides to defaults
	require_once 'config.local.php';

	require_once 'dart.functions.php';

	require_once 'class.dvd.php';
	require_once 'class.bluray.php';
	require_once 'class.dvddrive.php';
	require_once 'class.matroska.php';
	require_once 'class.mkvmerge.php';
	require_once 'class.handbrake.php';
	require_once 'class.dvd_copy.php';
	require_once 'class.bluray_copy.php';
	require_once 'class.ffmpeg.php';

	require_once 'models/dbtable.php';
	require_once 'models/dvds.php';
	require_once 'models/blurays.php';
	require_once 'models/episodes.php';
	require_once 'models/series_dvds.php';
	require_once 'models/series.php';
	require_once 'models/tracks.php';
	require_once 'models/audio.php';
	require_once 'models/subp.php';
	require_once 'models/chapters.php';
	require_once 'models/cells.php';

	require_once 'dart.parser.php';

	/** Start everything **/

	// Handle parser arguments and options
	if($debug)
		$verbose = 10;

	$skip = 0;

	// --backup is basically a shortcut for ripping an ISO and importing it plus
	// setting it in batch mode so it will eject the disc after finished.
	// Designed for use with a udev trigger so that discs will auto-rip
	if($opt_backup) {
		$opt_dump_iso = true;
		$opt_import = true;
		$batch_mode = true;
		$access_device = true;
	}

	if($opt_iso_filename) {

		foreach($devices as $device) {
			echo get_dvd_iso_filename($device);
			echo "\n";
		}

		exit(0);

	}

	if($opt_rename_iso) {

		foreach($devices as $device) {
			rename_iso($device);
		}

		exit(0);

	}

	if($opt_encode_info || $opt_copy_info)
		$batch_mode = true;

	if(!count($devices) && ($opt_info || $opt_encode_info || $opt_copy_info || $opt_dump_iso || $opt_import || $opt_archive))
		$devices = $all_devices;

	// Default max length for QA operations
	if(!isset($qa_max))
		$qa_max = 60;

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

		// Assume media is a DVD
		$disc_type = 'dvd';
		$disc_name = 'DVD';

		// Check if source filename is a block device or not
		$device_dirname = dirname(realpath($device));
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
		if($opt_info || $opt_encode_info || $opt_copy_info || $opt_import || $opt_archive || $opt_dump_iso) {
			if($debug)
				echo "* Info / Import / Archive / ISO: Enabling device access\n";
			$access_device = true;
			if(!$batch_mode) {
				echo "[Access Device]\n";
				echo "* Reading $display_device\n";
			}
			if($debug)
				echo "* Reading ".realpath($device)."\n";
		}

		// Look for any conditions where we there is access to the device, but
		// we need to skip over it because there is no media. Also open the tray
		// based on the wait switch that's passed.
		if($device_is_hardware && $access_device) {

			if($debug) {
				echo "[Device Hardware]\n";
				echo "* Drive $device\n";
			}

			$drive = new DVDDrive($device);
			$drive->set_debug($debug);

			// Check for basic access
			$device_access = $drive->access_device();
			if($device_access == false) {
				echo "* Access device failed\n";
				goto next_device;
			}

			// Poll the devices as few times as necessary to avoid hardware kernel
			// complaints. Set defaults.
			$tray_open = false;
			$tray_has_media = false;

			// Check if drive is open.
			$tray_open = $drive->is_open();

			if($debug)
				echo "* Tray open: ".($tray_open ? "yes" : "no" )."\n";

			// Check if drive has media if it's closed
			if(!$tray_open) {
				$tray_has_media = $drive->has_media();
			}

			if($debug)
				echo "* Has media: ".($tray_has_media ? "yes" : "no" )."\n";

			/**
			 * Writing all these logic checks independently makes it so my head
			 * doesn't hurt while trying to parse multiple conditions. However, each
			 * conditional check needs to see if access to the device is disabled at
			 * that point, since it's the one variable that is changed by the previous
			 * checks.
			 */

			// What happens in a scenario where the user passes a command (rip, info,
			// iso, etc.) but there is no media in the tray? Should the program assume
			// that the user wants to put something in there, and so it opens up? Or
			// should it only quietly continue and ignore the device? I'm going to go
			// with the assumption for now that if dart is specifically told to access
			// *this device*, then it is intended to have media in it. If there's none
			// in there, do the courtesy of opening the tray so that the user doesn't
			// have to eject it manually.
			if(!$tray_open && !$tray_has_media && $access_device) {

				// The device was included in the main program call, so eject
				// the tray if there is no media in there.
				$drive->eject();
				$access_device = false;
				if($debug)
					echo "* Opening drive: Disabling device access\n";
			}

			// Close the tray if not waiting
			if($tray_open && $access_device) {

				if(!$batch_mode)
					echo "* Drive is open, closing tray\n";
				if($drive->close())
					$tray_open = false;

				$tray_has_media = $drive->has_media();

				if($tray_has_media) {
					$access_drive = true;
					if(!$batch_mode)
						echo "* Found a $disc_name, ready to nom!\n";
				} else {
					if(!$batch_mode)
						echo "* Expected media, didn't find any!?\n";
					$tray_open = $drive->eject();
					$access_device = false;

					// This is *possibly* a case where the DVD drive is closed,
					// with media physically present, but the drive doesn't see
					// it for whatever reason. If that's the case, alert the
					// user to the anomaly.
					beep_error();

				}

			}

		}

		if($access_device) {

			// Check if source is a Blu-ray
			if($device_is_iso) {
				if(is_dir("$device/BDMV")) {
					$disc_type = 'bluray';
					$disc_name = 'Blu-ray';
				} elseif(is_dir("$device/VIDEO_TS")) {
					$disc_type = "dvd";
					$disc_name = "DVD";
				}
			} else {
				$disc_type = $drive->disc_type();
				if($disc_type == "dvd")
					$disc_name = "DVD";
				elseif($disc_type == "bluray")
					$disc_name = "Blu-ray";
			}

			if(!$batch_mode)
				echo "[$disc_name]\n";

			if($disc_type == "dvd")
				$dvd = new DVD($device, $debug);
			elseif($disc_type == "bluray")
				$dvd = new Bluray($device, $debug);

			if(!$dvd->opened) {
				echo "* Opening $device FAILED\n";
				goto next_device;
			}

			// Get the uniq ID for the disc
			$dvdread_id = $dvd->dvdread_id;
			$dvd_title = $dvd->title;

			if(!$batch_mode) {
				echo "* Title:\t$dvd_title\n";
				echo "* dvdread id:\t$dvdread_id\n";

				// Lookup the database dvds.id
				echo "[Database]\n";
			}
			$dvds_model_id = $dvds_model->find_dvdread_id($dvdread_id);

			// Found a new disc if it's not in the database!
			if(!$dvds_model_id && !$batch_mode) {
				echo "* $disc_name not found, ready to import!\n";
			}

			if($dvds_model_id) {

				$dvds_model->load($dvds_model_id);

				$series_title = $dvds_model->get_series_title();

				if(!$batch_mode) {
					echo "* $disc_name ID:\t$dvds_model_id\n";
					echo "* Series:\t$series_title\n";
				}

				$disc_indexed = true;

			}

			// Only need to display if it's imported if requesting import or
			// getting DVD info.
			if($opt_import || $opt_info || $opt_encode_info || $opt_copy_info) {
				if(!$batch_mode) {
					if($disc_indexed) {
						echo "* Imported:\tYes\n";
					} else {
						echo "* Imported:\tNo\n";
					}
				}
			}

		}

		require 'dart.import.php';
		require 'dart.info.php';
		require 'dart.encode_info.php';
		require 'dart.iso.php';

		// goto point for next device -- skip here for any reason that the initial
		// dart calls failed to acess the DVD or device, and it needs to move on.
		next_device:

		// If archiving, everything would have happened by now,
		// so eject the drive.
		if((($opt_archive) || ($opt_import && $new_dvd)) && $device_is_hardware && $drive->is_closed()) {
			if(!$batch_mode)
				echo "* Ready to archive next disc, opening tray!\n";
			$drive->eject();
		}

	}

?>
