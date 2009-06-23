#!/usr/bin/php
<?

	$start = time();

	require_once 'class.shell.php';
	require_once 'class.drip.php';
	require_once 'DB.php';

	$db =& DB::connect("pgsql://steve@willy/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	
	$dvd =& new drip();
	
//   	$dvd->disc_id();
//   	$dvd->title();
//  	$dvd->tracks();
//   	$dvd->chapters(); 
//  	$dvd->disc();
// 	$dvd->series();

	$options = shell::parseArguments();
	
	$ini = array();
	$config = getenv('HOME').'/.drip/config';
	if(file_exists($config))
		$ini = parse_ini_file($config);
		
	if($options['h'] || $options['help']) {
	
		shell::msg("Options:");
		shell::msg("  --rip\t\t\tRip DVD");
		shell::msg("  --nosub\t\t\tDon't rip subtitles");
		shell::msg("  --encode\t\tEncode episodes in queue");
		shell::msg("  --season <int>\tSet season #");
		shell::msg("  --disc <int>\t\tSet disc # for season");
		shell::msg("  --series <int>\tPass TV Series ID");
		shell::msg("  --skip <int>\t\tSkip # of episodes");
		shell::msg("  --max <int>\t\tMax # of episodes to rip and/or encode");
		shell::msg("  --v, -verbose\t\tVerbose output");
		shell::msg("  --debug\t\t\tEnable debugging");
	
		die;
	}
		
//  	print_r($options);

	if($options['p'] || $options['pretend'])
		$pretend = true;
	
	if($options['q'] || $options['queue'])
		$queue = true;
		
	if($options['skip'])
		$skip = abs(intval($options['skip']));
	else
		$skip = 0;
		
	if($options['max'])
		$max = abs(intval($options['max']));
	
	if($options['debug']) {
		$dvd->debug = $dvd->verbose = true;
		$debug =& $dvd->debug;
		$verbose =& $dvd->verbose;
		$eject = false;
	}
	
	if($options['encode'])
		$encode = true;
	
	if($options['rip'])
		$rip = true;
	
	if($options['archive'])
		$archive = true;
	
	$raw = true;
	if($options['noraw'])
		$raw = false;
	
	if($ini['eject'] || $options['eject'])
		$eject = true;
		
	if($options['v'] || $options['verbose'] || $ini['verbose'] || $debug) {
		$dvd->verbose = true;
		$verbose =& $dvd->verbose;
	}
	
	if($ini['mount'] && ($archive || $rip)) {
		$mount = true;
		$dvd->mount();
	}
	
	// Archive disc if not in the db
	
	// Some series may span seasons across one disc, by accident or design (complete series)
	// Normally the schema should prefer one entry per disc ID, but the simplest way to override
	// the season number is just to add another entry for the disc.
	// So, this statement will check to see if the disc is in the database OR if it is and
	// we are manually passing a season #.
	
	if(($archive || $rip) && (!$dvd->inDatabase() || ($dvd->inDatabase() && $options['season']))) {
	
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
		foreach(array('series', 'season', 'disc') as $x) {
			$tmp = abs(intval($options[$x]));
			
			// Allow disc side for discs
			if($x == "disc") {
				$side = strtoupper(substr($options[$x], -1, 1));
				if($side == "A" || $side == "B")
					$tmp .= $side;
			}
			
			if($tmp)
				$$x = $tmp;
		}
		
		
		
		
		// See if series passed is in the DB
		if($series) {
			$sql = "SELECT COUNT(1) FROM tv_shows WHERE id = $series;";
			$num_rows = $db->getOne($sql);
			if(!$num_rows)
				unset($series);
			else {
				$sql = "SELECT title FROM tv_shows WHERE id = $series;";
				$title = $db->getOne($sql);
				shell::msg("[DVD] Series: $title");
			}
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
						$new_title = true;
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
						
			$series = $arr[($input - 1)]['id'];
		}
				
		// Create a new series
		if($new_title && !$series) {
			$dvd->title();
			
			shell::msg('');
			shell::msg("Disc Title: ".$dvd->dvd['title']);
			$title = shell::ask("What is the title of this series? [TV Show]", 'TV Show');
			$min_len = shell::ask("What is the minimum episode length (in minutes)? [20]", 20);
			$max_len = shell::ask("What is the maximum episode length (in minutes)? [60]", 60);
			$cartoon = shell::ask("Is this series animated? [y/N]", 0);
			
			$series = $dvd->newSeries($title, $min_len, $max_len, $cartoon);
		}
		
		// Get the season
		if(!$season) {
		
			do {
				$season = shell::ask("What season is this disc? [1]", 1);
				$season = intval($season);
			} while($season == 0);
		
		}
		
		// Get the disc
		if($series && !$disc) {
			// Find out which other discs they already have archived
			// Set the default to the next one in line
			if($series) {
				$sql = "SELECT disc, TRIM(side) AS side, id FROM discs WHERE tv_show = $series AND season = $season ORDER BY disc, side;";
				$arr = $db->getAll($sql);
				
				$arr_archives = array();
				
				foreach($arr as $row) {
					if($row['side'])
						$arr_discs[$row['disc']][$row['side']] = $row['id'];
					else
						$arr_discs[$row['disc']] = $row['id'];

					$arr_archives[] = $row['disc'].$row['side'];
				}
				
				if(count($arr_archives)) {
					$str = implode(', ', $arr_archives);
					shell::msg("Discs archived for Season $season: $str");
					
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
				$disc = shell::ask("What number is this disc? [$next_disc$next_disc_side]", $next_disc.$next_disc_side);
				
				$side = strtoupper(substr($disc, -1));
				if(!($side == "A" || $side == "B"))
					$side = "";
				$disc = intval($disc);
				
				if(in_array($disc.$side, $arr_archives)) {
					shell::msg("Disc $disc$side is already archived.  Choose another number.");
					$disc = 0;
				} elseif(is_numeric($disc) && empty($side) && (in_array($disc."A", $arr_archives) || in_array($disc."B", $arr_archives))) {
					shell::msg("Need to specify a valid disc # and side.");
					$disc = 0;
				}
				
			} while($disc == 0);
		}
		
		if($series && $season && $disc) {
			$dvd->newDisc($series, $season, $disc, $side);
			
			$dvd->tracks();
			
			foreach($dvd->dvd['tracks'] as $track => $arr) {
				$dvd->newTrack($track, $arr['len'], $arr['aspect'], $arr['audio']);
			}
			
			// Populate IDs
			$dvd->trackIDs();
			
			// Getting chapters into the database
			// is currently incomplete in design.
			// I can't remember where I was going with it,
			// since dvdxchap is buggy if you select start / ending
			// positions.
			// It seems like I was looking to recreate them, somewhere,
			// for some reason, by getting the distance between chapters
			// and storing that.
			// For now, I'm just going back to the old method: store it
			// with the track (in episodes table) in raw format, and
			// pass that to mkvmerge. I can clean it up later.
			$dvd->chapters();
			
			// Get max and minimum length requirements
			$sql = "SELECT min_len, max_len FROM tv_shows WHERE id = $series;";
			$arr_len = $db->getRow($sql);
			
			foreach($dvd->dvd['tracks'] as $track => $arr) {
			
				if(($arr['len'] > $arr_len['max_len']) || ($arr['len'] < $arr_len['min_len']))
					$ignore = true;
				else
					$ignore = false;
					
				// Episodes originate as one track + one chapter,
				// and can be expanded upon in the frontend admin
 				$dvd->newEpisode($season, $arr['id'], $ignore, $dvd->dvd['tracks'][$track]['dvdxchap']);
			}
			
			// I don't remember where I was going with this.
			// FIXME Single chapter episodes don't need chapters in the MKV
			foreach($dvd->dvd['chapters'] as $track => $arr_chapter) {
				foreach($arr_chapter as $chapter => $arr) {
				
					$track_len =& $dvd->dvd['tracks'][$track]['len'];
					$chapter_len = $arr['len'];
				
					if($dvd->dvd['tracks'][$track]['id'] && $track_len && $chapter_len) {
						$dvd->newChapter($dvd->dvd['tracks'][$track]['id'], $arr['start'], $chapter, $chapter_len);
					}
				}
			}
			
		}
	}
	
	// Set the limit and starting points
	if($rip || $encode) {
		if($skip)
			$offset = " OFFSET $skip";
		else
			$offset = '';
		
		if($max)
			$limit = " LIMIT $max";
		else
			$limit = '';
	}
	
	if($rip) {
	
		$dvd->disc();
		$dvd->series();
		$dvd->tracks();
		
//  		print_r($dvd->series);
		
		// Create export dir
		if(!is_dir($dvd->export))
			mkdir($dvd->export, 0755);
			
		// Pull out the tracks that haven't been flagged to ignore in the database frontend
		// This query has nothing to do with what has / hasn't been encoded
		
		// Rip in sequential order by season, episode order, then title
		$sql = "SELECT e.id, tv.title AS series_title, e.title, d.season, d.disc, d.side, t.track, tv.unordered, t.multi, e.chapter AS starting_chapter, e.ending_chapter, e.episode_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id AND d.disc_id = '{$dvd->dvd['disc_id']}' INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND t.bad_track = FALSE AND e.title != '' ORDER BY t.track_order, d.season, e.episode_order, e.title, t.track, e.id $offset $limit;";
		$arr = $db->getAssoc($sql);
		
		if(count($arr)) {
		
			$x = 1;
			
			// Get the first episode number.
			// After this, we auto-increment it ourselves
			$e = $dvd->episodeNumber(key($arr));
			
			$episode_number = $dvd->episodeNumber(key($arr), false);
			
			$series_title = $arr[key($arr)]['series_title'];
			$season = $arr[key($arr)]['season'];
			$disc = $arr[key($arr)]['disc'];
			$side = $arr[key($arr)]['side'];
			$num_episodes = $count = count($arr);
			
			$export = $dvd->export.$dvd->formatTitle($series_title).'/';
 			if(!is_dir($export))
 				mkdir($export, 0755) or die("Can't create export directory $export");
		
			shell::msg("[Disc] Ripping \"$series_title\"");
			shell::msg("[Disc] Season $season, Disc $disc$side, Episodes $episode_number - ".($episode_number + $count - 1)."");
			
			foreach($arr as $episode => $tmp) {
			
				$rip_episode = false;
			
				$e = $dvd->episodeNumber($episode);
				$episode_number = $dvd->episodeNumber($episode, false);
			
				$title =& $tmp['title'];
				$track =& $tmp['track'];
				$season =& $tmp['season'];
				
				// Select the chapter(s) to rip
				// Two possibilities:
				// one episode = one track w/ one chapter
				// one episode = one track w/ multiple chapters
				if(!$tmp['ending_chapter']) {
					$tmp['ending_chapter'] = $tmp['starting_chapter'];
				}
			
				$basename = $dvd->formatTitle($title);
				
				if($dvd->series['unordered'] == 'f')
					$basename = $e.'._'.$basename;
					
				$basename = $export.$basename;
				
				$vob = "$basename.vob";
				$sub = "$basename.sub";
				$idx = "$basename.idx";
				$xml = "$basename.xml";
				$mkv = "$basename.mkv";
				
				$arr_todo = array();
				
				if(!file_exists($mkv)) {
 					
 					if(!file_exists($vob))
						$arr_todo[] = "Video";
					if((!file_exists($sub) || !file_exists($idx)) && !$options['nosub'])
						$arr_todo[] = "Subtitles";
					
					$arr_todo[] = "Matroska";
				}
				
				// Check to see if file exists, if not, rip it 				
				if((!file_exists($vob) && !file_exists($mkv)) || $pretend)
					$rip_episode = true;
					
				if($rip_episode || $verbose) {
					echo "\n";
					shell::msg("[Episode] \"$title\" ($x/$num_episodes)");
					if(count($arr_todo)) {
						shell::msg("[Episode] ".implode(", ", $arr_todo));
					}
				}
				
				// Actually start ripping
				if($rip_episode) {
				
					// FIXME Display MPEG2 + Codec + Num. Channels
					shell::msg("[DVD] Ripping DVD Video (MPEG-2)");
					
					$msg = "[DVD] Ripping Episode $x/$count";
					
					if($verbose) {
						$msg = "[DVD] Track $track";
					
						if($tmp['starting_chapter'])
							$msg .= "\tChapters ".$tmp['starting_chapter']."-".$tmp['ending_chapter'];
				
 						shell::msg($msg);
					}
					
					if($debug) {
						$msg = "mplayer dvd://$track";
						if($tmp['starting_chapter'])
							$msg .= " -chapter ".$tmp['starting_chapter']."-".$tmp['ending_chapter'];
						shell::msg($msg);
					}
					
					if($pretend) {
						shell::msg("[VOB] $vob");
					} else {
  						$dvd->rip($vob, $tmp['track'], $tmp['starting_chapter'], $tmp['ending_chapter']);
					}
				
				} else {
					if($verbose) {
						shell::msg("[DVD] Video Ripped");
					}
				}
				$x++;
				
				// Rip VobSub
				if((!file_exists($sub) && !file_exists($mkv)) || $pretend) {
				
					$vobsub = false;
					
					// See if we have an English VOBSUB for the track
					if($dvd->dvd['tracks'][$tmp['track']]['vobsub']) {
						
						foreach($dvd->dvd['tracks'][$tmp['track']]['vobsub'] as $arr) {
							if($arr['lang'] == 'en' || $arr['language'] == 'English') {
								$vobsub = true;
								break;
							}
						}
					}
					
					if($options['nosub'] && $vobsub) {
						$vobsub = false;
						shell::msg("[DVD] Ignoring Subtitles");
					} elseif(!$vobsub) {
						shell::msg("[DVD] No Subtitles");
					}
						
					
					if($vobsub && !file_exists($sub)) {
						if($pretend) {
							shell::msg("[DVD] $sub");
						} else {
							// Pass the basename, since mencoder dumps to
							// <basename>.idx, .sub
							shell::msg("[DVD] Ripping Subtitles (VobSub)");
 							$dvd->sub($basename, $tmp['track'], $tmp['starting_chapter'], $tmp['ending_chapter']);
						}
					}
				
				} elseif(file_exists($idx) && $verbose) {
					shell::msg("[DVD] Subtitles Ripped");
				}
				
				// Add episode to queue
				if(file_exists($vob)) {
					$dvd->queue($episode);
				}
				
				// Increment episode number
				$e++;
				$episode_number++;
				
			}
		
		}
		
		if($eject)
			$dvd->eject();
	
	}
	
	if($encode || $queue) {
	
		$arr = $dvd->getQueue($max);
		
		$todo = $count = count($arr);
		
		if($count) {
		
			shell::msg("$count episode(s) total to encode.");
			
			foreach($arr as $episode => $tmp) {
			
				$series_title =& $tmp['series_title'];
				$export = $dvd->export.$dvd->formatTitle($series_title).'/';
				$title =& $tmp['title'];
				$aspect =& $tmp['aspect'];
				$atrack =& $tmp['atrack'];
				$chapters =& $tmp['chapters'];
				
				$basename = $dvd->formatTitle($title);
				
				if($tmp['unordered'] == 'f') {
					$e = $dvd->episodeNumber($episode);
					$basename = $e.'._'.$basename;
				}
					
				$basename = $export.$basename;
				
				$vob = "$basename.vob";
				$idx = "$basename.idx";
				$sub = "$basename.sub";
				$mkv = "$basename.mkv";
				$xml = "$basename.xml";
				$txt = "$basename.txt";
				$mpg = "$basename.mpg";
				$ac3 = "$basename.ac3";
				
				if(!$raw) {
					$mpg = $ac3 = $vob;
				}
				
				// Check to see if file exists, if not, encode it
				if(file_exists($vob) && !file_exists($mkv)) {
				
					if($encode && !file_exists($xml)) {
						$tags = $dvd->globalTags($episode);
						if($tags)
							file_put_contents($xml, $tags);
					}
					
					var_dump($raw);
					var_dump($mpg);
					var_dump($encode);
					var_dump(file_exists($mpg));
					
					if($encode && $raw) {
					
						if(!file_exists($mpg)) {
							shell::msg("[MPG] $series_title: $e $title");
							$dvd->rawvideo($vob, $mpg);
						}
						
						if(!file_exists($ac3)) {
							shell::msg("[AC3] $series_title: $e $title");
							$dvd->rawaudio($vob, $ac3);
						}
						
					}
					
					shell::msg("[MKV] $series_title: $e $title");
					
					if($encode)
 						$dvd->mkvmerge($mpg, $ac3, $mkv, $title, $aspect, $chapters, $atrack, $idx, $xml);
					
					// Delete old files
					if($encode && file_exists($mkv) && !$debug) {
						if(file_exists($vob))
 							unlink($vob);
						if(file_exists($mpg))
 							unlink($mpg);
 						if(file_exists($ac3))
 							unlink($ac3);
						if(file_exists($idx))
							unlink($idx);
						if(file_exists($sub))
	 						unlink($sub);
 						if(file_exists($xml))
 							unlink($xml);
 						if(file_exists($txt))
 							unlink($txt);
					}
				
				} else {
				
					// Could be a number of reasons we got here.
					$dirname = dirname($vob);
					
					if(!file_exists($dirname)) {
						shell::msg("Directory $dirname doesn't exist for ripping ... file is probably deleted, removing episode from queue.");
						$sql = "DELETE FROM queue WHERE episode = $episode;";
 						$db->query($sql);
					} else {
 						shell::msg("Partial file exists for $title");
 					}
				}
				
				// Remove episode from queue
				if($encode && file_exists($mkv)) {
					$sql = "DELETE FROM queue WHERE episode = $episode;";
 					$db->query($sql);
				}
				
			}

			
		}
		
	}
	
	$finish = time();
	
	if($verbose) {
// 		$exec_time = shell::executionTime($start, $finish);
// 		shell::msg("Total execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
	}
	
	
	if($mount && ($archive || $rip) && !$queue)
		$dvd->unmount();
		
	if($eject && !$queue)
		$dvd->eject();
	

?>