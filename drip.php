#!/usr/bin/php
<?

	$start = time();

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
	
	$db =& DB::connect("pgsql://steve@charlie/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	
	function pear_error($obj) {
		die($obj->getMessage() . "\n" . $obj->getDebugInfo());
	}
	
	PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'pear_error');
	
	$drip = new drip();
	
	$storage_dir = "/var/media";
	
	$args = shell::parseArguments();
	
	$ini = array();
	$config = getenv('HOME').'/.drip/config';
	if(file_exists($config))
		$ini = parse_ini_file($config);
		
	if($args['h'] || $args['help']) {
	
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
		shell::msg("  --preset\t\tEncoding preset to use [$handbrake_preset]");
		
		shell::msg("Movies:");
		shell::msg("  --movie\t\tUse some settings to archive as a movie");
		shell::msg("  --title\t\tMovie Title");
		
	
		die;
	}
	
	if($args['p'] || $args['pretend'])
		$pretend = true;
	
	if($args['update'])
		$update = true;
	
	if($args['q'] || $args['queue'])
		$queue = true;
	
	if($args['i'] || $args['info'])
		$info = true;
	
	if($args['skip'])
		$skip = abs(intval($args['skip']));
	else
		$skip = 0;
		
	if($args['max'])
		$max = abs(intval($args['max']));
	
	if($args['debug']) {
		$drip->debug = $drip->verbose = true;
		$debug =& $drip->debug;
		$verbose =& $drip->verbose;
		$eject = false;
	}
	
	if($args['encode'])
		$encode = true;
	
	if($args['rip'])
		$rip = true;
	
	if($args['archive'])
		$archive = true;
		
	if($args['title'])
		$title = $args['title'];
	
	$demux = false;
	if($args['demux'])
		$demux = true;
	
	if($ini['eject'] || $args['eject'])
		$eject = true;
		
	if($args['v'] || $args['verbose'] || $ini['verbose'] || $debug) {
		$drip->verbose = true;
		$verbose =& $drip->verbose;
	}
	
	// Closed Captioning
	if($args['cc'] || $ini['rip_cc'])
		$rip_cc = true;
	if($args['cc'] || $ini['mux_cc'])
		$mux_cc = true;
	if($args['nocc'])
		$rip_cc = $mux_cc = false;
	$min_cc_filesize = 25;
	
	// DVD Subs (VobSubs)
	if($args['vobsub'] || $ini['rip_vobsub'])
		$rip_vobsub = true;
	if($args['vobsub'] || $ini['mux_vobsub'])
		$mux_vobsub = true;
	if($args['novobsub'])
		$rip_vobsub = $mux_vobsub = false;
	
	if($args['movie'])
		$movie = true;
		
	if($args['device'])
		$device = $args['device'];
	elseif($ini['device'])
		$device = $ini['device'];
	else
		$device = "/dev/dvd";
		
	$dvd = new DVD($device);
	
	if($ini['mount'] && ($archive || $rip || $update || $info)) {
		$mount = true;
  		$dvd->mount();
	}
	
	
	// Display info about disc
	if($info) {
		
		if(!$drip->inDatabase($dvd->getID())) {
			shell::msg("Disc is not archived");
			break;
		}
		
		// Get the series ID
		$sql = "SELECT id FROM view_discs WHERE disc_id = '".$dvd->getID()."';";
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
	
	// Update audio tracks
	function update_audio_tracks($drip_track_id) {
	
		global $db;
		global $device;
	
		$drip_track = new DripTrack($drip_track_id);
		$dvd_track = new DVDTrack($drip_track->getTrackNumber(), $device);
		
		// Delete the old audio streams
		
		$sql = "DELETE FROM audio_tracks WHERE track = $drip_track_id;";
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
			
			$drip_audio = new DripAudio();
			
			// Set the stream ID
			$drip_audio->setStreamID($stream_id);
			
			// Set the parent track ID
			$drip_audio->setTrackID($drip_track->getID());
			
			// Set the index, the sequential # of order for the track
			$drip_audio->setIndex($dvd_audio->getIX());
			
			// Set the 2-char langcode
			$drip_audio->setLanguage($dvd_audio->getLangcode());
			
			// Set the # of channels
			$drip_audio->setNumChannels($dvd_audio->getChannels());
			
			// Set the codec
			$drip_audio->setFormat($dvd_audio->getFormat());
		
		}
		
	}
	
	// Re-archive disc
	// Generally called if you want to update the webif
	if($update && $drip->inDatabase($dvd->getID())) {
		
		$sql = "SELECT id FROM discs WHERE disc_id = '".$dvd->getID()."';";
		$drip_disc_id = $db->getOne($sql);
		
		$drip_disc = new DripDisc($drip_disc_id);
		
		$arr_track_ids = $drip_disc->getTrackIDs();
		
		// Update disc number
		if($args['disc']) {
			if($verbose) {
				shell::msg("Updating disc");
			}
			$drip_disc->setDiscNumber($args['disc']);
		}
		
		// Update side
		if($args['side']) {
			if($verbose) {
				shell::msg("Updating side");
			}
			$drip_disc->setSide($args['side']);
		}
		
		foreach($arr_track_ids as $track_id) {
			$drip_track = new DripTrack($track_id);
			$dvd_track = new DVDTrack($drip_track->getTrackNumber(), $device);
			
			// Update aspect ratio
			if($drip_track->getAspectRatio() != $dvd_track->getAspectRatio()) {
				if($verbose) {
					shell::msg("Updating aspect ratio");
				}
				$drip_track->setAspectRatio($dvd_track->getAspectRatio());
			}
			
			// Convert to minutes to check against max/min length
			$track_length = bcdiv($dvd_track->getLength(), 60, 3);
			
			if($track_length != $drip_track->getLength()) {
				if($verbose) {
					shell::msg("Updating track length");
				}
				$drip_track->setLength($track_length);
			}
			
			// Insert the audio tracks if they are missing from the new table
			$sql = "SELECT COUNT(1) FROM audio_tracks WHERE track = $track_id;";
			$num_db_audio_tracks = $db->getOne($sql);
		
 			if(!$num_db_audio_tracks)
				update_audio_tracks($track_id);
			
			// Add chapters
			// This will only add chapters for the track, not set them for the episodes.
			$num_chapters = $dvd_track->getNumChapters();
 			if($num_chapters != $drip_track->getNumChapters()) {
			
				// Delete the old records (if any)
 				if($drip_track->getNumChapters()) {
					$sql = "DELETE FROM chapters WHERE track = ".$drip_track->getID().";";
					$db->query($sql);
 				}
				
				if($verbose) {
					shell::msg("Chapters: $num_chapters");
				}
				
				for($i = 0; $i < $num_chapters; $i++) {
					$chapter_number = $i + 1;
					
					if($debug)
						shell::msg("new DripChapter()");
					
					$chapter = new DripChapter();
					$chapter->setTrackID($drip_track->getID());
					$chapter->setNumber($chapter_number);
					$chapter->setLength($dvd_track->getChapterLength($chapter_number));
					
				}
 			}
			
		}
		
	}
	
	// Archive disc if not in the db
	
	// Some series may span seasons across one disc, by accident or design (complete series)
	// Normally the schema should prefer one entry per disc ID, but the simplest way to override
	// the season number is just to add another entry for the disc.
	// So, this statement will check to see if the disc is in the database OR if it is and
	// we are manually passing a season #.
	
	if(($archive || $rip) && !$drip->inDatabase($dvd->getID())) {
	
		// Bypass archive confirmation if --new is passed
		if(!$archive) {
			shell::msg("Your DVD is not in the database.");
			$q = shell::ask("Would you like to archive it now? [Y/n]", 'y');
			$q = strtolower($q);
			
			if($q != 'y')
				exit(0);
		}
		
		// Check for shell arguments to pass optionally
		// series, season, disc #
		foreach(array('series', 'season', 'disc', 'side', 'volume') as $x) {
			$tmp = abs(intval($args[$x]));
			
			switch($x) {
				case 'series':
					$series_id = $args[$x];
					break;
				case 'disc':
					$disc_number = $args[$x];
					if(!$args['side']) {
						$side = strtoupper(substr($args[$x], -1, 1));
					}
					break;
				case 'side':
				case 'season':
				case 'volume':
					$$x = $args[$x];
					break;
			}
			
			if(!($side == "A" || $side == "B"))
				$side = "";
			
		}
		
		// See if series passed is in the DB
		if($series_id) {
 			$series = new DripSeries($series_id);
		}
		
		if(!$series) {
			// Get the current TV show titles
			$sql = "SELECT id, title, min_len, max_len, cartoon FROM tv_shows ORDER BY title;";
			$arr = $db->getAll($sql);
			$num_rows = count($arr);
			
			// Display menu, let user pick the show
					
			// Split the output into pages for the terminal (24 lines per display)
			$arr_chunk = array_chunk($arr, 22, true);
				
			// Keep looping through the selection until they pick one
			do {
				// Display only 24 lines per selection at a time:
				for($x = 0, $y = 1; $x < count($arr_chunk); $x++) {
				
					shell::msg("Current TV shows:");
					for($z = 0; $z < count($arr_chunk[$x]); $z++) {
						shell::msg("\t$y. {$arr_chunk[$x][($y - 1)]['title']}");
						$y++;
					}
					
					$msg = '';
					if(count($arr_chunk) > 1)
						$msg = "[Page ".($x + 1)."/".count($arr_chunk)."]  Select TV show [NEXT PAGE/#/new]:";
					else
						$msg = "Select TV show [#/new]:";
						
					$input = shell::ask($msg, '');
					
					if(strtolower(trim($input)) != 'new') {
						$input = intval($input);
					} else {
						$new_series = true;
						break 2;
					}
					
					// Break out once they have their selection
					if($input > 0) {
						if($input > $num_rows) {
							shell::msg("Please enter a valid selection.", true);
							$input = 0;
						} else
							break 1;
					}
				}
			} while($input == 0);
			
			
		}
				
		// Create a new series
		if($new_series && !$series) {
			
			shell::msg('');
			shell::msg("Disc Title: ".$dvd->getTitle());
			if(!$title)
				$title = shell::ask("What is the title of this series? [TV Show]", 'TV Show');
			if($movie) {
				$min_len = 20;
				$max_len = 300;
				$cartoon = false;
			} else {
				$min_len = shell::ask("What is the minimum episode length (in minutes)? [20]", 20);
				$max_len = shell::ask("What is the maximum episode length (in minutes)? [60]", 60);
				$cartoon = shell::ask("Is this series animated? [y/N]", 0);
			}
			
			$series = new DripSeries();
			$series->setTitle($title);
			$series->setSortingTitle($title);
			$series->setMinLength($min_len);
			$series->setMaxLength($max_len);
			$series->setCartoon($cartoon);
			$series_id = $series->getID();
			
		} else {
			if(!$series_id)
 				$series_id = $arr[($input - 1)]['id'];
			$series = new DripSeries($series_id);
		}
		
		// Get the season
		// FIXME Check if there are other seasons
		if(!$season) {
		
			$last_season = $series->getLastSeasonNumber();
		
			if($last_season)
				$guess = $default = $last_season;
			else {
				$guess = 'None';
				$default = null;
			}
		
// 			$season = shell::ask("What season is this disc? [$guess]", $default);
			if(!is_numeric($season))
				$season = null;
		}
		
		// Get the volume
		if(is_null($volume) && $series->hasVolumes() && !$movie) {
			$volume = shell::ask("What volume is this disc? [None]", null);
			if(!is_numeric($volume))
				$volume = 0;
		} else
			$volume = 0;
		
		// Get the disc
		if($series && !$disc_number) {
		
			$arr_archives = array();
		
			// Find out which other discs they already have archived
			// Set the default to the next one in line
			if($series->getNumDiscs()) {
			
				if(is_null($season))
					$str_season = "NULL";
				else
					$str_season = $season;
			
				$sql = "SELECT DISTINCT disc_number, TRIM(side) AS side, disc_id FROM view_episodes WHERE tv_show_id = ".$series->getID()." AND season = $str_season AND volume = $volume ORDER BY disc_number, side;";
				$arr = $db->getAll($sql);
				
				foreach($arr as $row) {
					if($row['side'])
						$arr_discs[$row['disc']][$row['side']] = $row['id'];
					else
						$arr_discs[$row['disc']] = $row['id'];

					$arr_archives[] = $row['disc'].$row['side'];
				}
				
				if(count($arr_archives)) {
					$str = "Discs archived for Season $season";
					
					if($series->hasVolumes())
						$str .= ", Volume $volume";
					$str .= ": ".implode(', ', $arr_archives);
					
					shell::msg($str);
					
					$last_disc = max(array_keys($arr_discs));
					if(is_array($arr_discs[$last_disc])) {
						$last_disc = max(array_keys($arr_discs));
						$last_disc_side = max(array_keys($arr_discs[$last_disc]));
					} else {
						$last_disc_side = "";
						$next_disc_side = "";
					}
					
					if($last_disc_side == "A") {
						$next_disc = $last_disc;
						$next_disc_side = "B";
					} elseif($last_disc_side == "B") {
						$next_disc = $last_disc + 1;
						$next_disc_side = "A";
					} elseif(empty($last_disc_side)) {
						$next_disc = $last_disc + 1;
						$next_disc_side = "";
					}
					
				} else {
					$next_disc = 1;
					$next_disc_side = "";
				}
				
				
			} else {
				$next_disc = 1;
				$next_disc_side = "";
			}
			
			do {
				$disc_number = shell::ask("What number is this disc? [$next_disc$next_disc_side]", $next_disc.$next_disc_side);
				
				if(!$side)
					$side = strtoupper(substr($disc_number, -1));
				if(!($side == "A" || $side == "B"))
					$side = "";
				$disc_number = intval($disc_number);
				
				if(in_array($disc_number.$side, $arr_archives)) {
					shell::msg("Disc $disc_number$side is already archived.  Choose another number.");
					$disc_number = 0;
				} elseif(is_numeric($disc_number) && empty($side) && (in_array($disc_number."A", $arr_archives) || in_array($disc_number."B", $arr_archives))) {
					shell::msg("Need to specify a valid disc # and side.");
					$disc_number = 0;
				}
				
			} while($disc_number == 0);
		}
		
		
		if($series && $disc_number) {
		
			if($debug)
				shell::msg("new DripDisc()");
			
			$disc = new DripDisc();
			
			$disc->setSide($side);
			$disc->setDiscID($dvd->getID());
			$disc->setTitle($dvd->getTitle());
			$disc->setDiscNumber($disc_number);
			if($movie) {
				$series->setMovie();
				$series->setUnordered(false);
				$series->setVolumes(false);
				$series->setHandbrake();
			} else {
				$disc->setSeason($season);
				$disc->setVolume($volume);
			}
			$disc->setSeriesID($series->getID());
			
			$num_tracks = $dvd->getNumTracks();
			
			$min_length = $series->getMinLength();
			$max_length = $series->getMaxLength();
			
			for($x = 0; $x < $num_tracks; $x++) {
			
				$track_number = $x + 1;
				
				if($verbose)
					shell::msg("[Track $track_number]");
			
				if($debug)
					shell::msg("new DVDTrack()");
				$dvd_track = new DVDTrack($track_number, $device);
				
				// Convert to minutes to check against max/min length
				$track_length = bcdiv($dvd_track->getLength(), 60, 3);
				
				if($verbose)
					shell::msg("Length: $track_length");
				
				if($track_length < $max_length && $track_length > $min_length)
					$ignore = false;
				else
					$ignore = true;
				
				if($verbose) {
					$display_ignore = ($ignore ? "Yes" : "No");
					shell::msg("Ignoring: $display_ignore");
				}
					
				if($debug)
					shell::msg("new DripTrack()");
				
				$drip_track = new DripTrack();
				$drip_track->setDiscID($disc->getID());
				$drip_track->setTrackNumber($track_number);
				$drip_track->setLength($track_length);
				$drip_track->setAspectRatio($dvd_track->getAspectRatio());
				
				// Fetch all the audio streams, and store them
				// in the database.
				$audio_streams = $dvd_track->getAudioStreams();
				
				// Get the # of audio tracks
				$num_audio_tracks = count($audio_streams);
				
				// Pass the lsdvd XML output to DVDAudio class
				$lsdvd_xml = $dvd_track->getXML();
				
				foreach($audio_streams as $stream_id) {
				
					$dvd_audio = new DVDAudio($lsdvd_xml, $stream_id);
					
					$drip_audio = new DripAudio();
					
					// Set the stream ID
					$drip_audio->setStreamID($stream_id);
					
					// Set the parent track ID
					$drip_audio->setTrackID($drip_track->getID());
					
					// Set the index, the sequential # of order for the track
					$drip_audio->setIndex($dvd_audio->getIX());
					
					// Set the 2-char langcode
					$drip_audio->setLanguage($dvd_audio->getLangcode());
					
					// Set the # of channels
					$drip_audio->setNumChannels($dvd_audio->getChannels());
					
					// Set the codec
					$drip_audio->setFormat($dvd_audio->getFormat());
				
				}
				
				// Fetch all the subtitle streams, and store them
				// in the database.
				$subtitle_streams = $dvd_track->getSubtitleStreams();
				
				foreach($subtitle_streams as $stream_id) {
				
					$dvd_subs = new DVDSubs($lsdvd_xml, $stream_id);
					
					$drip_subtitles = new DripSubtitles();
					
					// Set the parent track ID
					$drip_subtitles->setTrackID($drip_track->getID());
					
					// Set the index, the sequential # of order for the track
					$drip_subtitles->setIndex($dvd_subs->getIX());
					
					// Set the stream ID
					// Set the index, the sequential # of order for the track
					$drip_subtitles->setStreamID($stream_id);
					
					// Set the 2-char langcode
					$drip_subtitles->setLangcode($dvd_subs->getLangcode());
					
					// Set the language
					$drip_subtitles->setLanguage($dvd_subs->getLanguage());
				
				}
				
				// Add chapters
				$num_chapters = $dvd_track->getNumChapters();
				if($num_chapters) {
					
					if($verbose) {
						shell::msg("Chapters: $num_chapters");
						echo "\n";
					}
					
					for($i = 0; $i < $num_chapters; $i++) {
						$chapter_number = $i + 1;
						
						if($debug)
							shell::msg("new DripChapter()");
						
						$chapter = new DripChapter();
						$chapter->setTrackID($drip_track->getID());
						$chapter->setNumber($chapter_number);
						$chapter->setLength($dvd_track->getChapterLength($chapter_number));
						
					}
				}
				
			}
		}
	}
	
	if($rip) {
	
		// Get the series ID
		$sql = "SELECT id FROM view_discs WHERE disc_id = '".$dvd->getID()."';";
		$drip_disc = new DripDisc($db->getOne($sql));
		$series = new DripSeries($drip_disc->getSeriesID());
		
		$movie = $series->isMovie();
		
		/** UPDATE DATABASE - NEW AUDIO TRACKS TABLE */
		
		$drip_track_ids = $drip_disc->getTrackIDs();
		
		foreach($drip_track_ids as $drip_track_id) {
			// Insert the audio tracks if they are missing from the new table
			$sql = "SELECT COUNT(1) FROM audio_tracks WHERE track = $drip_track_id;";
			$num_db_audio_tracks = $db->getOne($sql);
		
 			if(!$num_db_audio_tracks)
				update_audio_tracks($drip_track_id);
		}
		
		/** END MANDATORY UPDATE */
		
		// Create export dir
		if(!is_dir($drip->export))
			mkdir($drip->export, 0755);
			
		// Pull out the tracks that haven't been flagged to ignore in the database frontend
		// This query has nothing to do with what has / hasn't been encoded
		
		// Set the limit and starting points
		if($skip)
			$offset = " OFFSET $skip";
		else
			$offset = '';
		
		// Rip in sequential order by season, episode order, then title
		$sql = "SELECT episode_id FROM view_episodes WHERE bad_track = FALSE AND episode_title != '' AND disc_id = ".$drip_disc->getID()." ORDER BY track_order, season, episode_order, episode_title, track, episode_id $offset;";
		
		$arr = $db->getCol($sql);
		
		if(count($arr)) {
		
			$x = 1;
			
			$num_ripped = array();
			
			$series_title = $series->getTitle();
			$disc_number = $drip_disc->getDiscNumber();
			$side = $drip_disc->getSide();
			$num_episodes = $count = count($arr);
			
			$episode = new DripEpisode(current($arr));
			$starting_episode_number = $episode->getEpisodeNumber();
			$ending_episode_number = $starting_episode_number + $count - 1;
			$season = $episode->getSeason();
			$side = trim($side);
			
			shell::msg("[Series] $series_title");
			$str = "[Disc] Disc $disc_number$side";
			if($max)
				$str .= ", Max $max Episodes";
			elseif($series->isUnordered())
				$str .= ", $num_episodes Episodes";
			else
				$str .= ", Episodes $starting_episode_number - $ending_episode_number";
			shell::msg($str);
			
			foreach($arr as $episode_id) {
			
				$episode = new DripEpisode($episode_id);
				
				$dir = $drip->formatTitle($episode->getExportTitle());
				
				$export = $drip->export.$dir.'/';
 				if(!is_dir($export))
 					mkdir($export, 0755) or die("Can't create export directory $export");
 				
 				// Create the directory in the storage_dir
 				if($series->isCartoon())
					$target_dir = "$storage_dir/cartoons/$dir";
				else
					$target_dir = "$storage_dir/dvds/$dir";
				
				if(!file_exists($target_dir))
					mkdir($target_dir);
			
				$rip_episode = false;
			
				$episode_number = $episode->getEpisodeNumber();
				$episode_index = $episode->getEpisodeIndex();
				$episode_title = $episode->getTitle();
				$episode_part = $episode->getPart();
				
				$basename_title = $episode_title;
				if($episode_part > 1)
					$basename_title .= ", Part $episode_part";
				
				$basename = $drip->formatTitle($basename_title);
				if(!$series->isUnordered())
					$basename = $episode_index.'._'.$basename;
				$basename = $export.$basename;
				
 				$track = new DripTrack($episode->getTrackID());
 				$track_number = $track->getTrackNumber();
				$starting_chapter = $episode->getStartingChapter();
				$ending_chapter = $episode->getEndingChapter();
				
				$dvd_track = new DVDTrack($track_number, $device);
				
				if($debug)
					$dvd_track->setDebug(true);
				
				$dvd_track->setBasename($basename);
				$dvd_track->setStartingChapter($starting_chapter);
				$dvd_track->setEndingChapter($ending_chapter);
				
				$vob = "$basename.vob";
				$sub = "$basename.sub";
				$idx = "$basename.idx";
				$srt = "$basename.srt";
				$xml = "$basename.xml";
				$mkv = "$basename.mkv";
				$mpg = "$basename.mpg";
				$ac3 = "$basename.ac3";
				$txt = "$basename.txt";
				
				// Check to see if file exists, if not, rip it 				
				if((!file_exists($vob) && !file_exists($mkv)) || $pretend)
					$rip_episode = true;
					
				if($rip_episode || $verbose) {
					echo "\n";
					shell::msg("[Episode] \"$episode_title\" ($x/$num_episodes)");
					if($episode_number)
						shell::msg("[Episode] Number $episode_number");
					if($episode->getPart())
						shell::msg("[Episode] Part ".$episode->getPart());
				}
				
				// Actually start ripping
				if($rip_episode) {
				
					if($count > 1)
						shell::msg("[DVD] Ripping Episode $x/$count");
				
					if($verbose) {
						$msg = "[DVD] Track $track_number";
					
						if($episode->getStartingChapter())
							$msg .= "\tChapters $starting_chapter-$ending_chapter";
				
 						shell::msg($msg);
					}
					
					// FIXME Display MPEG2 + Codec + Num. Channels
					shell::msg("[DVD] Ripping DVD Video (MPEG-2)");
					
					if($pretend) {
						shell::msg("[VOB] $vob");
					} else {
						$dvd_track->dumpStream();
						$num_ripped['vob']++;
					}
					
				} else {
					if($verbose) {
						shell::msg("[DVD] Video Ripped");
					}
				}
				
				// Chapters
 				if(!file_exists($txt) && $episode->getNumChapters() > 1) {
					shell::msg("[DVD] Chapters");
					$dvd_track->dumpChapters();
					$num_ripped['chapters']++;
 				}
				
				// Rip VobSub
				if((!file_exists($sub) && !file_exists($mkv)) || $pretend) {
				
					$vobsub = false;
					
					// See if we have an English VOBSUB for the track
					
					if($dvd_track->hasSubtitles()) {
						$vobsub = true;
					}
					
					if(!$rip_vobsub && $vobsub) {
						shell::msg("[DVD] Ignoring Subtitles");
					} elseif($rip_vobsub && !$vobsub && !file_exists($vob)) {
						shell::msg("[DVD] No Subtitles");
					}
					
					if($vobsub && $rip_vobsub && !file_exists($sub)) {
						if($pretend) {
							shell::msg("[DVD] $sub");
						} else {
							shell::msg("[DVD] Ripping Subtitles (VobSub)");
							$dvd_track->dumpSubtitles();
							$num_ripped['vobsub']++;
						}
					}
				
				} elseif(file_exists($idx) && $rip_vobsub && $verbose) {
					shell::msg("[DVD] Subtitles Ripped");
				}
				
 				// Metadata
 				if(!file_exists($xml) && !file_exists($mkv)) {
 				
//  					shell::msg("[MKV] Metadata");
 				
 					$matroska = new Matroska();
 					
 					$matroska->setFilename($mkv);
 					if($episode_title)
 						$matroska->setTitle("TITLE", $episode->getTitle());
 					if(!$reencode)
 						$matroska->setAspectRatio($dvd_track->getAspectRatio());
 					
 					// FIXME this is a stray??
//  					$matroska->addTag();
					
					$matroska->addTag();
					$matroska->addTarget(70, "COLLECTION");
					$matroska->addSimpleTag("TITLE", $series->getTitle());
					$matroska->addSimpleTag("SORT_WITH", $series->getSortingTitle());
					if($series->getProductionStudio())
						$matroska->addSimpleTag("PRODUCTION_STUDIO", $series->getProductionStudio());
					if($series->getBroadcastYear())
						$matroska->addSimpleTag("DATE_RELEASE", $series->getBroadcastYear());
					$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");
					
					/** Season **/
					if($episode->getSeason() && !$movie) {
					
						$season = $episode->getSeason();
					
						$matroska->addTag();
						$matroska->addTarget(60, "SEASON");
						
						if($series->getBroadcastYear()) {
							$year = $series->getBroadcastYear() + $season - 1;
							$matroska->addSimpleTag("DATE_RELEASE", $year);
						}
						
						$matroska->addSimpleTag("PART_NUMBER", $season);
						
					}
					
					/** Episode **/
					if(!$movie) {
						$matroska->addTag();
						$matroska->addTarget(50, "EPISODE");
						if($episode_title)
							$matroska->addSimpleTag("TITLE", $episode_title);
						if($episode_number)
							$matroska->addSimpleTag("PART_NUMBER", $episode_number);
						$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
						$matroska->addSimpleTag("PLAY_COUNTER", 0);
						
						if($episode->getPart() > 1) {
							$matroska->addTag();
							$matroska->addTarget(40, "PART");
							$matroska->addSimpleTag("PART_NUMBER", $episode->getPart());
						}
					}
					
					$str = $matroska->getXML();
					
 					if($str)
						file_put_contents($xml, $str);
					
					$num_ripped['xml']++;
					
				}
				
				// Add episode to queue
				if(file_exists($vob)) {
					$drip->queue($episode_id);
				}
				
				$x++;
				
				foreach($num_ripped as $value)
					if($value == $max)
						break(2);
				
			}
		
		} else {
			shell::msg("The disc is archived, but there are no episodes to rip.");
			shell::msg("Check the frontend to see if titles need to be added.");
			$eject = false;
		}
		
		if($eject) {
			$dvd->eject();
			$ejected = true;
		}
	
	}
	
	if($encode || $queue) {
	
		// If you pass --max and --rip, chances are you want *those* exact
		// episodes to be ripped *and* encoded.  So, skip over the queue
		// and just touch those.
		if($rip && $max) {
			$arr = $drip->getQueue();
 			$arr = array_slice($arr, count($arr) - $max);
		}
		else
			$arr = $drip->getQueue($max);
		
		$todo = $count = count($arr);
		
		if($count) {
		
			$x = 1;
		
			foreach($arr as $episode_id) {
			
				$episode = new DripEpisode($episode_id);
				$track_id = $episode->getTrackID();
				$drip_track = new DripTrack($track_id);
				$drip_disc = new DripDisc($drip_track->getDiscID());
				$series = new DripSeries($drip_disc->getSeriesID());
				$series_title = $series->getTitle();
				$movie = $series->isMovie();
				$episode_title = $episode->getTitle();
				$export_title = $episode->getExportTitle();
				$episode_number = $episode->getEpisodeNumber();
				$episode_index = $episode->getEpisodeIndex();
				$reencode = $series->useHandbrake();
				
				$export = $drip->export.$drip->formatTitle($episode->getExportTitle()).'/';
	
				$basename_title = $episode_title;
				if($episode->getPart() > 1)
					$basename_title .= ", Part ".$episode->getPart();
				
				$basename = $drip->formatTitle($basename_title);
				if(!$series->isUnordered())
					$basename = $episode_index.'._'.$basename;
				$basename = $export.$basename;
				
				$vob = "$basename.vob";
				$idx = "$basename.idx";
				$sub = "$basename.sub";
				$srt = "$basename.srt";
				$mkv = "$basename.mkv";
				$xml = "$basename.xml";
				$txt = "$basename.txt";
				$mpg = "$basename.mpg";
				$ac3 = "$basename.ac3";
				$x264 = "$basename.x264";
				
				if($queue) {
					
					if(!file_exists($vob) || file_exists($mkv)) {
						$sql = "DELETE FROM queue WHERE episode = $episode_id;";
						$db->query($sql);
						shell::msg("[Queue] ($x/$todo) Removing $series_title: Episode $episode_index: $episode_title");
					} else
						shell::msg("[Queue] ($x/$todo) $series_title: Episode $episode_index: $episode_title");
				}
				
				if($encode) {
				
					$audio_index = $drip->getDefaultAudioTrack($track_id) - 1;
					$audio_aid = $drip->getDefaultAudioAID($track_id);
				
					// Check to see if file exists, if not, encode it
					if(file_exists($vob) && !file_exists($mkv)) {
					
						shell::msg("[Series] $series_title");
					
						$dvd_vob = new DVDVOB($vob);
						$dvd_vob->setDebug($debug);
						$dvd_vob->setAID($audio_aid);
						
						shell::msg("[Episode] \"$episode_title\" ($x/$count)");
						if($episode_number)
							shell::msg("[Episode] Number $episode_number");
						if($episode->getPart())
							shell::msg("[Episode] Part ".$episode->getPart());
						if(count($arr_todo)) {
							shell::msg("[Episode] ".implode(", ", $arr_todo));
						}
						
						$matroska = new Matroska($mkv);
						$matroska->setDebug($debug);
						$matroska->setTitle($episode_title);
					
						if($demux) {
						
							if(!file_exists($mpg)) {
								shell::msg("[VOB] Demuxing Raw Video");
								$dvd_vob->rawvideo($mpg);
								$matroska->addVideo($mpg);
							}
							
							if(!file_exists($ac3)) {
								shell::msg("[VOB] Demuxing Raw Audio");
								// atrack will always be at least 1
								$dvd_vob->rawaudio($ac3);
								$matroska->addAudio($ac3);
							}
							
						}
						
						if($reencode) {
						
							$handbrake = new Handbrake();
							
							if($debug)
								$handbrake->debug();
							elseif($verbose)
								$handbrake->verbose();
						
							if(!file_exists($x264)) {
								$stream_id = $drip_track->getDefaultStreamID();
							
								$handbrake->input_filename($vob);
								$handbrake->output_filename($x264);
								$handbrake->add_audio_stream($stream_id);
								$handbrake->autocrop();
								
								shell::msg("[x264] Encoding Video");
								$handbrake->encode();
							}
							
							if(file_exists($x264))
								$matroska->addFile($x264);
						
						}
						
						if(!$demux && !$reencode)
							$matroska->addFile($vob);
						
						if(!file_exists($srt) && $rip_cc && $series->hasCC()) {
							shell::msg("[SRT] Ripping Closed Captioning");
							$dvd_vob->dumpSRT();
						}
						
						$mux = array("Video", "Audio");
						
						if(file_exists($idx) && $mux_vobsub) {
							$matroska->addSubtitles($idx);
							$mux[] = "VobSub";
						}
						if(file_exists($srt) && (filesize($srt) > $min_cc_filesize) && $mux_cc) {
							$matroska->addSubtitles($srt);
							$mux[] = "Closed Captioning";
						}
						if(file_exists($txt)) {
							$matroska->addChapters($txt);
							$mux[] = "Chapters";
						}
						if(file_exists($xml))
							$matroska->addGlobalTags($xml);
						
						if($drip_track->getAspectRatio())
							$matroska->setAspectRatio($drip_track->getAspectRatio());
							
						$str_muxing = implode(", ", $mux);
							
						shell::msg("[MKV] Muxing to Matroska ($str_muxing)");
						
						$matroska->mux();
							
						
					} elseif (!file_exists($vob) && !file_exists($mkv)) {
					
						// At this point, it shouldn't be in the queue.
						$sql = "DELETE FROM queue WHERE episode = $episode_id;";
						$db->query($sql);
					
					}
					
					// Delete old files
					if(file_exists($mkv) && !$debug) {
						if(file_exists($vob))
							unlink($vob);
						if(file_exists($mpg))
							unlink($mpg);
						if(file_exists($ac3))
							unlink($ac3);
						if(file_exists($idx) && $mux_vobsub)
							unlink($idx);
						if(file_exists($sub) && $mux_vobsub)
							unlink($sub);
						if(file_exists($srt) && $mux_cc)
							unlink($srt);
						if(file_exists($xml))
							unlink($xml);
						if(file_exists($txt))
							unlink($txt);
						if(file_exists($x264) && $reencode)
							unlink($x264);
					}
					
					// Remove episode from queue
					if($encode && file_exists($mkv)) {
						$sql = "DELETE FROM queue WHERE episode = $episode_id;";
						$db->query($sql);
					}
				}
				
				$x++;
				
				if($encode)
					echo "\n";
				
			}
			
		}
		
	}
	
	$finish = time();
	
	if($verbose) {
// 		$exec_time = shell::executionTime($start, $finish);
// 		shell::msg("Total execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
	}
	
	
 	if($mount && ($archive || $rip) && !$queue && !$eject)
 		$dvd->unmount();
	
	// Don't eject if you are just checking the queue
	if(!$ejected && (($eject && !$queue && $rip) || $args['eject']))
		$dvd->eject();
		
?>