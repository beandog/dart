#!/usr/bin/php
<?

	require_once 'Console/ProgressBar.php';

	require_once 'class.shell.php';
	require_once 'class.dart.php';

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
	
	start:
	
	/** Start everything **/
	$dvd = new DVD($device);
	$dvds_model = new Dvds_Model;
	$queue_model = new Queue_Model;
	$dvd_episodes = array();
	$dart = new dart();
	
	if(substr($device, -4, 4) == ".iso")
		$device_is_iso = true;
	
	// Determine whether we are reading the device
	if($rip || $info || $import)
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
		
		if($dvds_model_id)
			$disc_archived = true;
		
		$dvds_model->load($dvds_model_id);
		
		$dvd_episodes = $dvds_model->get_episodes();
		
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
		
	}
	
	require 'dart.info.php';
	require 'dart.iso.php';
	require 'dart.queue.php';
	require 'dart.rip.php';	
	require 'dart.encode.php';
	
	if($eject)
		$dvd->eject();
	
	// If polling for a new disc, check to see if one is in the
	// drive.  If there is, start over.
	if($poll && $rip) {

		$notice = false;
		
		while(true) {

			if($dvd->cddetect()) {
				shell::msg("Found a disc, starting over!");
				goto start;
			} else {
				if(!$notice)
					shell::msg("Waiting for a new disc on $device");
				$notice = true;
				sleep(60);
			}

		}

	}
	
?>
