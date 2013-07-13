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
			$drive->close(false);
		}
	}
	
	if(!count($devices) && ($rip || $info || $dump_iso || $import))
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

		if($verbose) {
			if(!$first_run)
				echo "\n";
			shell::stdout("[Initialization]");
			shell::stdout("* Device: $device");
		}
		
		clearstatcache();

		$dvd = new DVD($device);
		$dvds_model = new Dvds_Model;
		$dvd_episodes = array();

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

		// Does the device tray have media
		$has_media = false;

		// Making a run for it! :)
		$first_run = false;
		
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
		// Note that since the next steps are so dependent upon
		// whether 'wait' is true or not, it's easier to just
		// create a whole new block
		if(!$wait && !$device_is_iso && $access_device) {
			
			if($debug)
				shell::stdout("! Wait is off, device is not an ISO, and access device is enabled");

			$drive = new DVDDrive($device);
			$drive->set_debug($debug);

			// **ALWAYS** send a call to the close() function
			// regardless of whether it's open or not.  This can
			// catch situations when the device is closing and
			// polling as well as the expected situation where the
			// tray is just open.  In short, this will avoid headaches
			// and race conditions.
			if($verbose)
				shell::stdout("* Sleepy time . . .");
			$drive->close();

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

		}

		// Adding a block if wait is enabled is better for
		// readability and structure.
		if($wait && !$device_is_iso && $access_device) {

			if($debug)
				shell::stdout("! Wait is on, device is not an ISO, and access device is enabled");

			$drive = new DVDDrive($device);
			$drive->set_debug($debug);
			
			// If the drive is closed, then check for media
			// Since the wait command is given, in this case,
			// do *not* open the tray.  We'll just go to the next
			// device (later in the code) and start all over,
			// leaving the job of adding media to the user.
			if($drive->is_closed()) {
				$has_media = $drive->has_media();
				if($has_media)
					$access_drive = true;
			} else {
				$access_device = false;
				$access_drive = false;
			}
		}
		
		if($access_device) {

			$display_device = $device;
			if($device_is_iso)
				$display_device = basename($device);

			if($verbose) {
				shell::msg("[Access Device]");
				shell::msg("* Reading $display_device");
			}

			// Decrypt the CSS to avoid disc access errors
			if($verbose)
				shell::msg("* Decrypting CSS");
			$dvd->load_css();

			$device_filesize = $dvd->getSize();
			$display_filesize = number_format($device_filesize);
			if(!$device_filesize) {
				shell::msg("* DVD size reported as zero! Aborting");
				exit(1);
			}
			
			if($verbose)
				shell::msg("* $display_filesize MB");
			
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
		require 'dart.rip.php';	
		
		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($wait && ($rip || $import || $dump_iso)) {
			$device = toggle_device($device);
			sleep(1);
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
			return '/dev/dvd';
	}
	
?>
