#!/usr/bin/php
<?

	ini_set('include_path', ini_get('include_path').":/home/steve/git/dart");

	require_once 'Console/ProgressBar.php';

	require_once 'includes/class.shell.php';
	require_once 'includes/pgconn.php';
	require_once 'includes/mdb2.php';
	
	require_once 'class.dvd.php';
	require_once 'class.dvddrive.php';
	require_once 'class.dvdvob.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.dvdaudio.php';
	require_once 'class.dvdsubs.php';
	require_once 'class.matroska.php';
	require_once 'class.handbrake.php';
	
	require_once 'models/dvds.php';
	require_once 'models/episodes.php';
	require_once 'models/series_dvds.php';
	require_once 'models/series.php';
	require_once 'models/tracks.php';
	require_once 'models/queue.php';
	
	require_once 'dart.parser.php';
	require_once 'includes/prefs.php';
	
	/** Start everything **/
	$all_devices = array('/dev/dvd', '/dev/dvd1');

	// Base URL to access DVD admin frontend
	// Override in preferences
	if(empty($baseurl))
		$baseurl = '';

	if($eject_trays) {
		foreach($all_devices as $str) {
			$drive = new DVDDrive($str);
			$drive->open();
		}
	}
	
	if($close) {
		foreach($all_devices as $str) {
			$drive = new DVDDrive($str);
			$drive->close();
		}
	}
	
	if(!count($devices))
		$devices = $all_devices;
	
	if($all)
		$devices = $all_devices;
	
	// Process request to reset the queue
	if($reset_queue) {
		$queue_model = new Queue_Model;
		$queue_model->reset();
	}
		
	foreach($devices as $device) {

		start:
		
		clearstatcache();

		$dvd = new DVD($device);
		$dvds_model = new Dvds_Model;
		$queue_model = new Queue_Model;
		$dvd_episodes = array();
		$export_dir = getenv('HOME').'/dvds/';

		// If disc has an entry in the database
		$disc_indexed = false;
		
		// If all the disc metadata is in the database
		$disc_archived = false;
	
		// Is the device an ISO file
		$device_is_iso = false;

		// Is the device a symlink
		$is_symlink = false;
		
		// Can we poll the file or device
		$access_device = false;
		
		// If it's DVD drive, can it be accessed
		$access_drive = false;
		
		// Get the dirname
		$dirname = dirname($device);
		
		// File is an ISO (or a non-block device) if
		// it is not found in /dev
		if($dirname != "/dev") {
			$device_is_iso = true;
			$is_symlink = is_link($device);
		}

		// Verify file exists
		if(!file_exists($device)) {
			shell::stdout("* Couldn't find $device");
			exit(1);
		}
			
		// Determine whether we are reading the device
		if($rip || $info || $import || $dump_iso)
			$access_device = true;

		// Determine whether we need physical access to a disc.
		if(!$device_is_iso && $access_device) {
			$access_drive = true;
			$drive = new DVDDrive($device);
		}
		
		// Override any eject preference if we can't
		// access the drive.
		if($access_drive) {

			// Only close the tray if not already told to wait
			if($drive->is_open() && !$wait) {
				$drive->close();
			}

			// If set to wait and there is no media in the device,
			// then go to the next device and start over.
			if($wait && !$drive->has_media()) {
				$device = toggle_device($device);
				goto start;
			}

			// Take a nap to allow time for devices to load
			if($wait && $drive->has_media()) {
				sleep(5);
			}

			// Expecting media, so open the tray if
			// there is none, and move to the next device
			// Only perform if not told to wait
			if(!$drive->has_media() && !$wait) {
				$drive->open();
				$device = toggle_device($device);
				goto start;
			}
		}
		else
			$eject = false;
		
		if($access_device) {

			$device_filesize = $dvd->getSize();
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				shell::msg("* DVD size reported as zero! Aborting");
				exit(1);
			}
			
			if($verbose) {
				$display_device = $device;
				if($device_is_iso)
					$display_device = basename($device);
				shell::msg("[Access Device]");
				shell::msg("* Reading $display_device");
				shell::msg("* $display_filesize MB");
			}
			
			// Decrypt the CSS to avoid disc access errors
			if($verbose)
				shell::msg("* Decrypting CSS");
			$dvd->load_css();
			
			// Get the uniq ID for the disc
			if($verbose) {
				shell::msg("* Title: ".$dvd->getTitle());
				shell::stdout("* Disc ID: ", false);
			}
			$uniq_id = $dvd->getID();

			if($verbose)
				shell::stdout($uniq_id, true);
			
			// Get the serial ID for the disc
			if($verbose)
				shell::stdout("* Serial ID: ", false);
			$serial_id = $dvd->getSerialID();
			if($verbose)
				shell::stdout($serial_id, true);
			
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
					shell::stdout("* DVD ID: $dvds_model_id");
					shell::stdout("* Series: $series_title");
				}
				
				$disc_indexed = true;
				
				$dvds_model->load($dvds_model_id);
				
				/** Metadata **/
				/** Fix any missing database values **/

				// Update the longest track
				if(is_null($dvds_model->longest_track)) {
					if($verbose)
						shell::msg("* Updating longest track in DB");
					$dvds_model->longest_track = $dvd->getLongestTrack();
				}

				// Update disc size
				if(is_null($dvds_model->filesize)) {
					if($verbose)
						shell::msg("* Updating filesize in DB");
					$dvds_model->filesize = $dvd->getSize() ;
				}

				// Update serial ID
				if(!$dvds_model->serial_id) {
					$serial_id = trim($dvd->getSerialID());
					if($verbose) {
						shell::msg("* Serial ID: $serial_id");
						shell::msg("* Updating serial id in DB");
					}
					$dvds_model->serial_id = $serial_id;
				}

				$disc_archived = true;
			
			} else {
				if(!$info)
					$import = true;
			}
			
			if($verbose) {
				if($disc_indexed) {
					shell::msg("* Indexed");
					if($disc_archived)
						shell::msg("* Archived");
					else
						shell::msg("* Unarchived");
						
				} else
					shell::msg("* Unindexed");
			}
			
		}
		
		require 'dart.info.php';
		require 'dart.import.php';
		require 'dart.iso.php';
		require 'dart.queue.php';
		require 'dart.rip.php';	
		require 'dart.encode.php';
		require 'dart.ftp.php';
		
		if($eject)
			$drive->open();
		
		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($rip || $import || $dump_iso)) {
			$device = toggle_device($device);
			goto start;
		}
	
	}
	
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
		$series_dir = $export_dir.formatTitle($series_title)."/";
		
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
			return '/dev/dvd';
	}
	
?>
