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
	require_once 'class.matroska.php';
	
	require_once 'class.drip.series.php';
	require_once 'class.drip.disc.php';
	require_once 'class.drip.track.php';
	require_once 'class.drip.audio.php';
	require_once 'class.drip.chapter.php';
	require_once 'class.drip.episode.php';
	
	$db =& DB::connect("pgsql://steve@charlie/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
// 	PEAR::setErrorHandling(PEAR_ERROR_DIE);
	
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
		shell::msg("  --rip\t\t\tRip DVD");
		shell::msg("  --nosub\t\t\tDon't rip subtitles");
		shell::msg("  --encode\t\tEncode episodes in queue");
		shell::msg("  --season <int>\tSet season #");
		shell::msg("  --volume <int>\tSet volume #");
		shell::msg("  --disc <int>\t\tSet disc # for season");
		shell::msg("  --series <int>\tPass TV Series ID");
		shell::msg("  --skip <int>\t\tSkip # of episodes");
		shell::msg("  --max <int>\t\tMax # of episodes to rip and/or encode");
		shell::msg("  --v, -verbose\t\tVerbose output");
		shell::msg("  --debug\t\t\tEnable debugging");
	
		die;
	}
	
//   	print_r($args);

	if($args['p'] || $args['pretend'])
		$pretend = true;
	
	if($args['update'])
		$update = true;
	
	if($args['q'] || $args['queue'])
		$queue = true;
		
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
	
	$raw = true;
	if($args['noraw'])
		$raw = false;
	
	if($ini['eject'] || $args['eject'])
		$eject = true;
		
	if($args['v'] || $args['verbose'] || $ini['verbose'] || $debug) {
		$drip->verbose = true;
		$verbose =& $drip->verbose;
	}
	
	// Closed Captioning
	if($ini['rip_cc'] || $args['cc'])
		$rip_cc = true;
	if($args['cc'] || $ini['mux_cc'])
		$mux_cc = true;
	if($args['nocc'])
		$rip_cc = $mux_cc = false;
	$min_cc_filesize = 15;
	
	// DVD Subs (VobSubs)
	if($ini['rip_subs'] || $args['subs'])
		$rip_subs = true;
	if($args['subs'] || $ini['mux_subs'])
		$mux_subs = true;
	if($args['nosubs'])
		$rip_subs = $mux_subs = false;
		
	if($args['device'])
		$device = $args['device'];
	elseif($ini['device'])
		$device = $ini['device'];
	else
		$device = "/dev/dvd";
		
	$dvd = new DVD($device);
	
	if($ini['mount'] && ($archive || $rip || $update)) {
		$mount = true;
  		$dvd->mount();
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
		
// 		die;
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
// 			$drip->title();
			
			shell::msg('');
			shell::msg("Disc Title: ".$dvd->getTitle());
			$title = shell::ask("What is the title of this series? [TV Show]", 'TV Show');
			$min_len = shell::ask("What is the minimum episode length (in minutes)? [20]", 20);
			$max_len = shell::ask("What is the maximum episode length (in minutes)? [60]", 60);
			$cartoon = shell::ask("Is this series animated? [y/N]", 0);
			
			$series = new DripSeries();
			$series->setTitle($title);
			$series->setSortingTitle($title);
			$series->setMinLength($min_len);
			$series->setMaxLength($max_len);
			$series->setCartoon($cartoon);
			$series_id = $series->getID();
			
// 			$series_id = $drip->newSeries($title, $min_len, $max_len, $cartoon);
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
		if(is_null($volume) && $series->hasVolumes()) {
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
			$disc->setSeason($season);
			$disc->setVolume($volume);
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
				
				// Add audio tracks
				$num_audio_tracks = $dvd_track->getNumAudioTracks();
				
				for($a = 0; $a < $num_audio_tracks; $a++) {
				
					$audio_index = $a + 1;
				
					$audio = new DripAudio();
					$audio->setTrackID($drip_track->getID());
					$audio->setIndex($audio_index);
					
					$dvd_track->setAudioIndex($audio_index);
					
					$audio->setLanguage($dvd_track->getAudioLangCode());
					$audio->setNumChannels($dvd_track->getAudioChannels());
					$audio->setFormat($dvd_track->getAudioFormat());
					
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
				$dir = $storage_dir."/dvds/$dir";
				
				if(!file_exists($dir))
					mkdir($dir);
			
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
					
					if($debug) {
 						$msg = "mplayer dvd://$track_number";
 						if($starting_chapter)
 							$msg .= " -chapter $starting_chapter-$ending_chapter";
 						shell::msg($msg);
					}
					
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
					
					if(!$rip_subs && $vobsub) {
						shell::msg("[DVD] Ignoring Subtitles");
					} elseif(!$vobsub) {
						shell::msg("[DVD] No Subtitles");
					}
					
					if($vobsub && $rip_subs && !file_exists($sub)) {
						if($pretend) {
							shell::msg("[DVD] $sub");
						} else {
							shell::msg("[DVD] Ripping Subtitles (VobSub)");
							$dvd_track->dumpSubtitles();
							$num_ripped['vobsub']++;
						}
					}
				
				} elseif(file_exists($idx) && $rip_subs && $verbose) {
					shell::msg("[DVD] Subtitles Ripped");
				}
				
 				// Metadata
 				if(!file_exists($xml) && !file_exists($mkv)) {
 				
//  					shell::msg("[MKV] Metadata");
 				
 					$matroska = new Matroska();
 					
 					$matroska->setFilename($mkv);
 					if($episode_title)
 						$matroska->setTitle("TITLE", $episode->getTitle());
 					$matroska->setAspectRatio($dvd_track->getAspectRatio());
 					
 					$matroska->addTag();
					
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
					if($episode->getSeason()) {
					
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
		
		if($eject)
			$dvd->eject();
	
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
		
//  		print_r($arr);
		
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
				$episode_title = $episode->getTitle();
				$export_title = $episode->getExportTitle();
				$episode_number = $episode->getEpisodeNumber();
				$episode_index = $episode->getEpisodeIndex();
				
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
					
						if($raw) {
							shell::msg("[Episode] \"$episode_title\" ($x/$count)");
							if($episode_number)
								shell::msg("[Episode] Number $episode_number");
							if($episode->getPart())
								shell::msg("[Episode] Part ".$episode->getPart());
							if(count($arr_todo)) {
								shell::msg("[Episode] ".implode(", ", $arr_todo));
							}
						
							if(!file_exists($mpg)) {
								shell::msg("[VOB] Demuxing Raw Video");
								$dvd_vob->rawvideo($mpg);
							}
							
							if(!file_exists($ac3)) {
								shell::msg("[VOB] Demuxing Raw Audio");
								// atrack will always be at least 1
								$dvd_vob->rawaudio($ac3);
							}
							
						} else {
							$mpg = $ac3 = $vob;
						}
						
						if(!file_exists($srt) && $rip_cc && $series->hasCC()) {
							shell::msg("[SRT] Ripping Closed Captioning");
							$dvd_vob->dumpSRT();
						}
						
						shell::msg("[MKV] Muxing to Matroska");
					
						$matroska = new Matroska($mkv);
						
						$matroska->addVideo($mpg);
						$matroska->addAudio($ac3);
						
						if(file_exists($idx) && $mux_subs)
							$matroska->addSubtitles($idx);
						if(file_exists($srt) && filesize($srt) > 25 && $mux_cc)
							$matroska->addSubtitles($srt);
						if(file_exists($txt))
							$matroska->addChapters($txt);
						if(file_exists($xml))
							$matroska->addGlobalTags($xml);
						
						$matroska->setTitle($episode_title);
						
						if($drip_track->getAspectRatio())
							$matroska->setAspectRatio($drip_track->getAspectRatio());
						
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
						if(file_exists($idx) && $mux_subs)
							unlink($idx);
						if(file_exists($sub) && $mux_subs)
							unlink($sub);
						if(file_exists($srt) && $mux_cc)
							unlink($srt);
						if(file_exists($xml))
							unlink($xml);
						if(file_exists($txt))
							unlink($txt);
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
	if(($eject && !$queue && $rip) || $args['eject'])
		$dvd->eject();
		
?>