<?

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
	
// 	require_once 'class.dart.series.php';
//  	require_once 'class.dart.dvd.php';
// 	require_once 'class.dart.track.php';
// 	require_once 'class.dart.audio.php';
// 	require_once 'class.dart.subtitles.php';
// 	require_once 'class.dart.chapter.php';
// 	require_once 'class.dart.episode.php';
	
	$dart = new dart();

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
	
	function display_info($dvd_id) {
	
		die("FIXME Unwritten function, display_info()");
	
		// Get the series ID
		$dvd = dvds::find_by_uniq_id($dvd_id);
		$dvds_model = new Dvds_Model($dvd->id);
		die;
		$series = new dartSeries($dart_disc->getSeriesID());
		
		$series_title = $series->getTitle();
		
		shell::msg("Title: $series_title");
		shell::msg("Series ID: $series");
		$disc_number = $dart_disc->getDiscNumber();
		$side = $dart_disc->getSide();
		
		$disc_season = $dart_disc->getSeason();
		$disc_volume = $dart_disc->getVolume();
		if($disc_season)
			shell::msg("Season $disc_season");
		if($disc_volume)
			shell::msg("Volume $disc_volume");
		
		shell::msg("Disc: $disc_number$side");
		
		$sql = "SELECT episode_id FROM view_episodes WHERE bad_track = FALSE AND episode_title != '' AND disc_id = ".$dart_disc->getID()." ORDER BY track_order, season, episode_order, episode_title, track, episode_id $offset;";
		$arr = $db->getCol($sql);
		
		$num_episodes = $count = count($arr);
		
		shell::msg("Episodes: $num_episodes");
		
		$x = 0;
		
		foreach($arr as $episode_id) {
			
			$episode = new dartEpisode($episode_id);
			$episode_number = $episode->getEpisodeNumber();
			$episode_title = $episode->getTitle();
			$episode_part = $episode->getPart();
			if($episode_part > 1)
				$episode_title .= ", Part $episode_part";
				
			$track = new dartTrack($episode->getTrackID());
			$track_number = $track->getTrackNumber();
			$starting_chapter = $episode->getStartingChapter();
			$ending_chapter = $episode->getEndingChapter();
			
			if($starting_chapter && $ending_chatper)
				$display_chapter = " Chapter $starting_chapter-$ending_chapter";
				
			shell::msg("Track $track_number$display_chapter \"$episode_title\"");
		}
		
	}
	
	// Update audio tracks
	function update_audio_tracks($dart_track_id) {
	
		global $device;
		
		$dart_track = new dartTrack($dart_track_id);
		$dvd_track = new DVDTrack($dart_track->getTrackNumber(), $device);
		
		// Delete the old audio streams
		
		$sql = "DELETE FROM audio_tracks WHERE track = $dart_track_id;";
		$db->query($sql);
		
		// Fetch all the audio streams, and store them
		// in the database.
		$audio_streams = $dvd_track->getAudioStreams();
		
		// Get the # of audio tracks
		$num_audio_tracks = count($audio_streams);
		
		// Pass the lsdvd XML output to DVDAudio class
		$lsdvd_xml = $dvd_track->getXML();
		
		foreach($audio_streams as $stream_id) {
		
			$dvd_audio = new DVDAudio($lsdvd_xml, $stream_id);
			
			$dart_audio = new dartAudio();
			
			// Set the stream ID
			$dart_audio->setStreamID($stream_id);
			
			// Set the parent track ID
			$dart_audio->setTrackID($dart_track->getID());
			
			// Set the index, the sequential # of order for the track
			$dart_audio->setIndex($dvd_audio->getIX());
			
			// Set the 2-char langcode
			$dart_audio->setLanguage($dvd_audio->getLangcode());
			
			// Set the # of channels
			$dart_audio->setNumChannels($dvd_audio->getChannels());
			
			// Set the codec
			$dart_audio->setFormat($dvd_audio->getFormat());
		
		}
		
	}

	function getQueue() {

		global $rip;
		global $max;
		global $dart;

		// If you pass --max and --rip, chances are you want *those* exact
		// episodes to be ripped *and* encoded.  So, skip over the queue
		// and just touch those.
		if($rip && $max) {
			$arr = $dart->getQueue();
 			$arr = array_slice($arr, count($arr) - $max);
		}
		else
			$arr = $dart->getQueue($max);

		return $arr;

	}
