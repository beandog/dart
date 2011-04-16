#!/usr/bin/php
<?

	require_once 'Console/ProgressBar.php';

	require_once 'class.shell.php';

	require_once 'ar/pg.dvds.php';
	
	require_once 'class.dvd.php';
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
	
	/** Start everything **/
	$all_devices = array('/dev/dvd', '/dev/dvd1');

	if($eject_trays)
		foreach($all_devices as $str) {
			$dvd = new DVD($str);
			$dvd->eject();
		}
	
	if(is_null($device))
		$devices = array("/dev/dvd");
	if($alt_device)
		$devices = array("/dev/dvd1");
	
	if($all)
		$devices = $all_devices;
	
	foreach($devices as $device) {

		start:
		
		$dvd = new DVD($device);
		$dvds_model = new Dvds_Model;
		$queue_model = new Queue_Model;
		$dvd_episodes = array();
		$dart = new dart();
		$num_empty_polls = 0;
		$export_dir = getenv('HOME').'/dvds/';
		
		if($reset_queue)
			$queue_model->reset();
		
		if(substr($device, -4, 4) == ".iso")
			$device_is_iso = true;
			
		// Determine whether we are reading the device
		if($rip || $info || $import || ($argc == 1 && $dvd->cddetect(true)) || ($argc == 2 && $alt_device && $dvd->cddetect(true)))
			$access_device = true;
			
		// Determine whether we need physical access to a disc.
		if(!$device_is_iso && $access_device)
			$access_drive = true;
		else {
			$access_drive = false;
			$mount = false;
		}
		
		if($access_drive)
			$dvd->close_tray();
		else
			$eject = false;
		
		if($mount)
			$dvd->mount();
		
		if($access_device) {
			
			$dvd->load_css();
			$uniq_id = $dvd->getID();
			
			$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);
			
			if($dvds_model_id) {
				
				$dvds_model->load($dvds_model_id);
				
				$dvd_episodes = $dvds_model->get_episodes();
				
				if(!is_null($dvds_model->longest_track))
					$disc_archived = true;
				else
					$disc_archived = false;
				
				$num_episodes = count($dvd_episodes);
				
				// Update disc size
				/** Set the filesize of the DVD disc **/
				if(is_null($dvds_model->filesize)) {
				
					// FIXME Kind of pointless if only checks if mounted..
					// FIXME reads udev amount
					if($mount)
						$dvds_model->filesize = $dvd->getSize();
					
					if($device_is_iso && file_exists($device)) {
						$filesize = sprintf("%u", filesize($device)) / 1024;
						$dvds_model->filesize = $filesize;
						unset($filesize);
					}
				}
			
			} else
				$disc_archived = false;
			
		}
		
		require 'dart.info.php';
		require 'dart.import.php';
		require 'dart.iso.php';
		require 'dart.queue.php';
		require 'dart.rip.php';	
		require 'dart.encode.php';
		
		if($eject)
			$dvd->eject();
		
		// If polling for a new disc, check to see if one is in the
		// drive.  If there is, start over.
		if($poll && ($rip || $import)) {
	
			// If nothing given for 30 minutes, then bail.
			$sleepy_time = 12;
			while(true && $num_empty_polls < ((60 / $sleepy_time) * 30)) {
	
				if($dvd->cddetect(true)) {
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
		$str = preg_replace("/[^A-Za-z0-9 \-,.?':!]/", '', $str);
		$underlines && $str = str_replace(' ', '_', $str);
		return $str;
	}
	
	function get_episode_filename($episode_id) {
		
		// Class instatiation
		$episodes_model = new Episodes_Model($episode_id);
		$episode_title = $episodes_model->title;
		$track_id = $episodes_model->track_id;
		$episode_number = $episodes_model->get_number();
		$episode_part = $episodes_model->part;
		$episode_season = $episodes_model->get_season();
		
		$series_model = new Series_Model($episodes_model->get_series_id());
		$series_title = $series_model->title;
		$series_dir = $export_dir.formatTitle($series_title)."/";
		
		/** Build the episode filename **/
		if($series_model->indexed == 't' && $episode_season)
				$episode_prefix = "${episode_season}x${episode_number}._";
		
		if($episode_part > 1)
			$episode_suffix = ", Part $episode_part";
		
		/** Filenames **/
		$episode_filename = $series_dir.formatTitle($episode_prefix.$episode_title.$episode_suffix);
		
		return $episode_filename;
	
	}
	
?>
