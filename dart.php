#!/usr/bin/php
<?php

	ini_set('include_path', ini_get('include_path').":/home/steve/git/dart");

	require_once 'Console/ProgressBar.php';

	require_once 'includes/class.shell.php';
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
	$all_devices = array('/dev/dvd', '/dev/dvd1', '/dev/dvd2');
	$export_dir = getenv('HOME').'/dvds/';

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

	if(!count($devices) && ($rip || $info || $dump_iso || $import || $archive))
		$devices = $all_devices;

	// Process request to reset the queue
	if($reset_queue) {
		$queue_model = new Queue_Model;
		$queue_model->reset();
	}

	// General boolean for various items
	$first_run = true;

	// Only allow overrding naptime in debug mode
	if($debug && $nonap)
		$naptime = 0;
	else
		$naptime = null;

	foreach($devices as $device) {

		start:

		if($verbose) {
			if(!$first_run)
				echo "\n";
			if($debug) {
				shell::stdout("[Initialization]");
				shell::stdout("* Device: $device");
			}
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

		// Is the device an ISO file
		$device_is_iso = false;

		// Is the device a symlink
		$device_is_symlink = false;

		// Can we poll the file or device
		$access_device = false;

		// If it's DVD drive, can it be accessed
		$access_drive = false;

		// Are we missing any data in the database
		$missing_import_data = false;
		$missing_audio_streams = false;

		// Change the device name to include the full path
		// if it's a filename and not a block device
		if($device_is_iso)
			$device = realpath($device);

		// Does the device tray have media
		$has_media = false;

		// Making a run for it! :)
		$first_run = false;

		// File is an ISO (or a non-block device) if
		// it is not found in /dev
		$device_dirname = dirname($device);
		if($device_dirname != "/dev") {
			$device_is_iso = true;
			$device_is_symlink = is_link($device);
		}

		// Verify file exists
		if(!file_exists($device)) {
			shell::stdout("* Couldn't find $device");
			exit(1);
		}

		// Device name to display to stdout
		$display_device = $device;
		if($device_is_iso)
			$display_device = basename($device);

		// Determine whether we are reading the device
		if($rip || $info || $import || $archive || $dump_iso) {
			$access_device = true;

			if($verbose) {
				shell::msg("[Access Device]");
				shell::msg("* Reading $display_device");
			}
		}

		// Determine whether we need physical access to a disc.
		// Note that since the next steps are so dependent upon
		// whether 'wait' is true or not, it's easier to just
		// create a whole new block
		// if(!$wait && !$device_is_iso && $access_device) {
		if(!$device_is_iso && $access_device) {

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
					if($verbose)
						shell::stdout("* Found a DVD, ready to nom!");
				} else {
					if($verbose)
						shell::stdout("* No media, so out we go!");
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
			/** Testing ignoring this after adding tray_status
			 * properly knows when a drive is ready to poll.  I'm guessing
			 * that a lot of the problems were caused by a race condition.
			 */
			/*
			if($verbose)
				shell::msg("* Decrypting CSS");
			if(!$device_is_iso)
				$dvd->load_css();
			*/


			if($verbose);
				echo "[DVD]\n";

			$device_filesize = $dvd->getSize();
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				shell::msg("* DVD size reported as zero! Aborting");
				exit(1);
			}

			if($verbose)
				shell::msg("* Filesize:\t$display_filesize MB");

			// Get the uniq ID for the disc
			if($verbose) {
				shell::msg("* Title:\t".$dvd->getTitle());
				shell::stdout("* Disc ID:\t", false);
			}
			$uniq_id = $dvd->getID();

			if($verbose)
				shell::stdout($uniq_id, true);

			// Get the serial ID for the disc
			if($verbose)
				shell::stdout("* Serial ID:\t", false);
			$serial_id = $dvd->getSerialID();
			if($verbose)
				shell::stdout($serial_id, true);

			if($verbose)
				echo "[Database]\n";

			// Lookup the database dvds.id
			$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);

			// Use the serial ID as a unique identifer as well
			if($device_is_iso && !$dvds_model_id) {

				shell::stdout("* Lookup on serial ID and disc title: ", false);

				$tmp_dvds_model = new Dvds_Model;
				$find_serial_id = $tmp_dvds_model->find_id('serial_id', $serial_id);

				if(!$find_serial_id)
					shell::stdout("none found; marking as new disc");
				else {
					$dvds_model->load($find_serial_id);
					if($dvd->getTitle() == $tmp_dvds_model->title) {
						shell::stdout("match found");
						$dvds_model_id = $find_serial_id;
						unset($tmp_dvds_model);
					}
				}
			}

			if($dvds_model_id) {

				$series_title = $dvds_model->get_series_title();

				if($verbose) {
					shell::stdout("* DVD ID:\t$dvds_model_id");
					shell::stdout("* Series:\t$series_title");
				}

				$disc_indexed = true;

				$dvds_model->load($dvds_model_id);

			}

			if($verbose) {
				if($disc_indexed) {
					shell::msg("* Indexed:\tYes");
				} else {
					shell::msg("* Unindexed:\tNo");
				}
			}

		}

		require 'dart.info.php';
		require 'dart.archive.php';
		require 'dart.import.php';
		require 'dart.iso.php';
		require 'dart.rip.php';

		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($rip || $import || $archive || $dump_iso) && !$device_is_iso) {
			// Only toggle devices if passed more than one
			// Otherwise, just re-poll the original.
			// This is useful in cases where --wait is called
			// on two separate devices, so two drives can
			// be accessed at the same time
			if(count($devices) > 1) {
				$device = toggle_device($device);
				sleep(1);
			}
			// If there is only one device, then wait until the tray is
			// closed.
			else {
				$drive->close(false);
			}

			if($debug)
				shell::stdout("! Going to start position");

			goto start;
		}

	}

	require 'dart.queue.php';
	require 'dart.encode.php';
	require 'dart.ftp.php';

	/**
	 * Format a title for saving to filesystem
	 *
	 * @param string original title
	 * @return new title
	 */
	function formatTitle($str = 'Title', $underlines = true) {
		$str = preg_replace("/[^A-Za-z0-9 \-,.?':!_]/", '', $str);
		$underlines && $str = str_replace(' ', '_', $str);
		return $str;
	}

	function get_episode_filename($episode_id) {

		// Class instatiation
		$episodes_model = new Episodes_Model($episode_id);
		$episode_title = $episodes_model->title;
		$track_id = $episodes_model->track_id;
		$episode_number = $episodes_model->get_number();
		$display_episode_number = str_pad($episode_number, 2, 0, STR_PAD_LEFT);
		$episode_part = $episodes_model->part;
		$episode_season = $episodes_model->get_season();
		$series_model = new Series_Model($episodes_model->get_series_id());
		$episode_prefix = '';
		$episode_suffix = '';

		// FIXME Take into account 10+seasons
		if($series_model->indexed == 't') {
			if(!$episode_season)
				$display_season = 1;
			else
				$display_season = $episode_season;

			$episode_prefix = "${display_season}x${display_episode_number}._";
		}

		$series_model = new Series_Model($episodes_model->get_series_id());
		$series_title = $series_model->title;
		$series_dir = formatTitle($series_title)."/";

		if($episode_part)
			$episode_suffix = ", Part $episode_part";

		/** Filenames **/
		$episode_filename = $series_dir.formatTitle($episode_prefix.$episode_title.$episode_suffix);

		return $episode_filename;

	}

	// Switch to the next device
	function toggle_device($device) {
		if($device == '/dev/dvd')
			return '/dev/dvd1';
		if($device == '/dev/dvd1')
			return '/dev/dvd2';
		if($device == '/dev/dvd2')
			return '/dev/dvd';
	}

?>
