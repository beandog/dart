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

	if($eject_trays)
		foreach($all_devices as $str) {
			$drive = new DVDDrive($str);
			$drive->open();
		}
	
	if(!count($devices))
		$devices = $all_devices;
	
	if($alt_device)
		$devices = array("/dev/dvd1");
	
	if($all)
		$devices = $all_devices;
	
	// Process request to reset the queue
	if($reset_queue) {
		$queue_model = new Queue_Model;
		$queue_model->reset();
	}
		
	if(count($devices) > 1)
		$poll = false;
	
	next_device:
	
	foreach($devices as $device) {

		start:
		
		clearstatcache();

		if($verbose) {
			shell::stdout("[DVD]");
			shell::stdout("* Opening $device");
		}
		
		$dvd = new DVD($device);
		$dvds_model = new Dvds_Model;
		$queue_model = new Queue_Model;
		$dvd_episodes = array();
		$num_empty_polls = 0;
		$export_dir = getenv('HOME').'/dvds/';

		// If disc has an entry in the database
		$disc_indexed = false;
		
		// If all the disc metadata is in the database
		$disc_archived = false;
	
		// Is the device an ISO file
		$device_is_iso = false;
		
		// Can we poll the file or device
		$access_device = false;
		
		// If it's DVD drive, can it be accessed
		$access_drive = false;
		
		$pathinfo = pathinfo($device);
		
		if($pathinfo['extension'] == "iso") {
			$device_is_iso = true;
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
			if($drive->is_open()) {
				if($verbose)
					shell::stdout("* Closing tray ... ", false);
				$drive->close();
				if($verbose)
					shell::stdout("nom nom nom");
			}

			if($verbose) {
				shell::stdout("* Checking for media ... ", false);
				if($drive->has_media())
					shell::stdout("ok");
				else {
					shell::stdout("none found, exiting");
					array_shift($devices);
					goto next_device;
				}
			}
		}
		else
			$eject = false;
		
		if($access_device) {

			$filesize = number_format($dvd->getSize('MB'));
			if(!$filesize) {
				shell::msg("* DVD size reported as zero! Aborting");
				exit(1);
			}
			
			if($verbose) {
				$display_device = $device;
				if($device_is_iso)
					$display_device = basename($device);
				shell::msg("[Access Device]");
				shell::msg("* Opening $display_device");
				shell::msg("* $filesize MB");
			}
			
			// Decrypt the CSS to avoid disc access errors
			if($verbose)
				shell::msg("* Decrypting CSS");
			$dvd->load_css();
			
			// Get the uniq ID for the disc
			if($verbose)
				shell::stdout("* Disc ID: ", false);
			$uniq_id = $dvd->getID();
			if($verbose)
				shell::stdout($uniq_id, true);
			
			if($verbose)
				shell::stdout("* Searching for database record ... ", false);
			$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);
			
			if($dvds_model_id) {

				if($verbose) {
					shell::stdout("found $dvds_model_id", true);
					shell::stdout("* Admin: ${baseurl}index.php/dvds/new_dvd/$dvds_model_id");
				}
				
				$disc_indexed = true;
				
				$dvds_model->load($dvds_model_id);
				
				// A disc is archived if it meets the latest schema
				// In this case, just check to see if the longest track is set.
				if(!is_null($dvds_model->longest_track)) {
					$disc_archived = true;
					// Disable importing if it was turned on
					$import = false;
				} else {
					// Override previous import command: we have access
					// to the device, go ahead and import it into the database.
					$import = true;
				}
				
				// Update disc size
				/** Set the filesize of the DVD disc **/
				if(is_null($dvds_model->filesize)) {
				
					if($verbose)
						shell::msg("* Updating filesize in DB");
				
					$dvds_model->filesize = $dvd->getSize('MB') ;
				}
			
			} else {
				if($verbose)
					shell::stdout("none found", true);
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
			$drive->eject();
		
		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($poll && ($rip || $import)) {
	
			// If nothing given for 30 minutes, then bail.
			$sleepy_time = 12;
			while(true && $num_empty_polls < ((60 / $sleepy_time) * 30)) {
	
				// Check to see if a disc is present or being loaded
				if($drive->has_media()) {
					goto start;
				} else {
					sleep($sleepy_time);
					$num_empty_polls++;
				}
	
			}
	
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
	
?>
