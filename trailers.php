<?

	require_once 'class.shell.php';
	require_once 'class.dvd.php';
	require_once 'class.dvdtrack.php';
	
	$dvd = new DVD();
	
	$dvd->mount();
	
	$num_tracks = $dvd->getNumTracks();
	
	for($x = 1; $x < $num_tracks + 1; $x++) {
	
	
		$dvd_track = new DVDTrack($x);
		
		$length = $dvd_track->getLength();
		$hms = $dvd_track->secondsToHMS($length);
		
		// Between 1 and 6 minutes
		if($length > 60 && $length < 360)
			shell::msg("Track $x \t@ $hms");
		
	}
	

?>