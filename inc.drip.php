<?

	require_once 'class.shell.php';
	require_once 'class.drip.php';

	require_once 'DB.php';
	
	// New OOP classes
	require_once 'class.dvd.php';
	require_once 'class.dvdvob.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.dvdaudio.php';
	require_once 'class.dvdsubs.php';
	require_once 'class.matroska.php';
	require_once 'class.handbrake.php';
	
	require_once 'class.drip.series.php';
	require_once 'class.drip.disc.php';
	require_once 'class.drip.track.php';
	require_once 'class.drip.audio.php';
	require_once 'class.drip.subtitles.php';
	require_once 'class.drip.chapter.php';
	require_once 'class.drip.episode.php';
	
	/** PEAR **/
	$db =& DB::connect("pgsql://steve@charlie/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	function pear_error($obj) {
		die($obj->getMessage() . "\n" . $obj->getDebugInfo());
	}
	PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'pear_error');
	
	$drip = new drip();

	function display_help() {
	
		shell::msg("Options:");
		shell::msg("  -i, --info\t\tList episodes on DVD");
		
		shell::msg("  --rip\t\t\tRip everything on DVD");
		shell::msg("  --nosub\t\tDon't rip VobSub subtitles");
		shell::msg("  --encode\t\tEncode episodes in queue");
		
		shell::msg("  --archive\t\tAdd DVD to database");
		shell::msg("  --season <int>\tSet season #");
		shell::msg("  --volume <int>\tSet volume #");
		shell::msg("  --disc <int>\t\tSet disc # for season");
		shell::msg("  --series <int>\tPass TV Series ID");
		
		shell::msg("  --demux\t\tUse MEncoder to demux audio and video streams into separate files");
		
		shell::msg("  --skip <int>\t\tSkip # of episodes");
		shell::msg("  --max <int>\t\tMax # of episodes to rip and/or encode");
		shell::msg("  -v, -verbose\t\tVerbose output");
		shell::msg("  --debug\t\tEnable debugging");
		shell::msg("  --update\t\tUpdate DVD specs in database");
		shell::msg("  -q, --queue\t\tList episodes in queue");
		
		shell::msg("Subtitles:");
		shell::msg("  --vobsub\t\tRip and mux VobSub subtitles");
		shell::msg("  --cc\t\t\tRip and mux Closed Captioning subtitles");
		
		shell::msg("Handbrake:");
		shell::msg("  --handbrake\t\tUse Handbrake to reencode video");
		shell::msg("  --preset\t\tEncoding preset to use");
		
		shell::msg("Movies:");
		shell::msg("  --movie\t\tUse some settings to archive as a movie");
		shell::msg("  --title\t\tMovie Title");
	
	}
	
	function display_info() {
	
		global $db;
		
		// Get the series ID
		$sql = "SELECT id FROM view_discs WHERE disc_id = '$dvd_id';";
		$drip_disc = new DripDisc($db->getOne($sql));
		$series = new DripSeries($drip_disc->getSeriesID());
		
		$series_title = $series->getTitle();
		
		shell::msg($series_title);
		$disc_number = $drip_disc->getDiscNumber();
		$side = $drip_disc->getSide();
		
		$disc_season = $drip_disc->getSeason();
		$disc_volume = $drip_disc->getVolume();
		if($disc_season)
			shell::msg("Season $disc_season");
		if($disc_volume)
			shell::msg("Volume $disc_volume");
		
		shell::msg("Disc: $disc_number$side");
		
		$sql = "SELECT episode_id FROM view_episodes WHERE bad_track = FALSE AND episode_title != '' AND disc_id = ".$drip_disc->getID()." ORDER BY track_order, season, episode_order, episode_title, track, episode_id $offset;";
		$arr = $db->getCol($sql);
		
		$num_episodes = $count = count($arr);
		
		shell::msg("Episodes: $num_episodes");
		
		$x = 0;
		
		foreach($arr as $episode_id) {
			
			$episode = new DripEpisode($episode_id);
			$episode_number = $episode->getEpisodeNumber();
			$episode_title = $episode->getTitle();
			$episode_part = $episode->getPart();
			if($episode_part > 1)
				$episode_title .= ", Part $episode_part";
				
			$track = new DripTrack($episode->getTrackID());
			$track_number = $track->getTrackNumber();
			$starting_chapter = $episode->getStartingChapter();
			$ending_chapter = $episode->getEndingChapter();
			
			if($starting_chapter && $ending_chatper)
				$display_chapter = " Chapter $starting_chapter-$ending_chapter";
				
			shell::msg("Track $track_number$display_chapter \"$episode_title\"");
		}
		
	}