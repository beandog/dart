#!/usr/bin/php
<?

	require_once 'class.shell.php';
	require_once 'class.drip.php';
	require_once 'DB.php';

	$db =& DB::connect("pgsql://steve@willy/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	
	$dvd =& new drip();
	
//   	$dvd->disc_id();
//   	$dvd->title();
//  	$dvd->tracks();
//  	$dvd->chapters();
//  	$dvd->disc();
// 	$dvd->series();

	$options = shell::parseArguments();
	
	$ini = array();
	$config = getenv('HOME').'/.bend';
	if(file_exists($config))
		$ini = parse_ini_file($config);
		
// 	print_r($options);

	if($options['p'])
		$options['pretend'] = true;

	
	// Archive disc if not in the db
	if(($options['archive'] || $options['rip']) && !$dvd->inDatabase()) {
	
		// Bypass archive confirmation if --new is passed
		if(!$options['archive']) {
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
				shell::msg("$title");
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
				$sql = "SELECT disc FROM discs WHERE tv_show = $series AND season = $season ORDER BY disc;";
				$arr = $db->getCol($sql);
				if(count($arr)) {
					$str = implode(', ', $arr);
					shell::msg("Discs archived for Season $season: $str");
					$max = max($arr);
					$max++;
				} else
					$max = 1;
				
			} else {
				$max = 1;
			}
			
			do {
				$disc = shell::ask("What number is this disc? [$max]", $max);
				$disc = intval($disc);
				
				if(in_array($disc, $arr)) {
					shell::msg("Disc #$disc is already archived.  Choose another number.");
					$disc = 0;
				}
				
			} while($disc == 0);
			
		}
		
		if($series && $season && $disc) {
			$dvd->newDisc($series, $season, $disc);
			
			$dvd->tracks();
			
			foreach($dvd->dvd['tracks'] as $track => $arr) {
				$dvd->newTrack($track, $arr['len'], $arr['aspect'], $arr['audio']);
			}
			
			// Populate IDs
			$dvd->trackIDs();
			
			// Get max and minimum length requirements
			$sql = "SELECT min_len, max_len FROM tv_shows WHERE id = $series;";
			$arr_len = $db->getRow($sql);
			
			foreach($dvd->dvd['tracks'] as $track => $arr) {
				if(($len > $arr_len['max_len']) || ($len < $arr_len['min_len']))
					$ignore = true;
				else
					$ignore = false;
				
				// Episodes originate as one track + one chapter,
				// and can be expanded upon in the frontend admin
				$dvd->newEpisode($arr['id'], $ignore);
			}
			
			$dvd->chapters();
			
			print_r($dvd['chapters']); die;
			
			foreach($dvd->dvd['chapters'] as $track => $arr_chapter) {
				foreach($arr_chapter as $chapter => $arr) {
				
					$len =& $dvd->dvd['tracks'][$track]['len'];
				
					if($dvd->dvd['tracks'][$track]['id'] && $len) {
						$dvd->newChapter($dvd->dvd['tracks'][$track]['id'], $arr['start'], $chapter);
					}
				}
			}
			
		}
	}
	
	if($options['rip']) {
	
		if($ini['mount'])
			$dvd->mount();
	
		$dvd->disc();
		$dvd->series();
		$dvd->tracks();
		
//  		print_r($dvd->series);
		
		// Create export dir
		if(!is_dir($dvd->export))
			mkdir($dvd->export, 0755);
			
		// Pull out the tracks that haven't been flagged to ignore in the database frontend
		// This query has nothing to do with what has / hasn't been encoded
		
		// Rip in sequential order by episode order, then title
		$sql = "SELECT e.id, tv.title AS series_title, e.title, d.season, d.disc, t.track, tv.unordered,  t.multi, tv.starting_chapter AS series_starting_chapter, e.chapter AS starting_chapter, e.ending_chapter, e.episode_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id AND d.id = {$dvd->disc['id']} INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND t.bad_track = FALSE AND e.title != '' ORDER BY t.track_order, e.episode_order, e.title, t.track, e.id;";
		$arr = $db->getAssoc($sql);
		
//  		print_r($arr);
		
		if(count($arr)) {
		
			$x = 1;
			
			// Get the first episode number.
			// After this, we auto-increment it ourselves
			$e = $dvd->episodeNumber(key($arr));
			
			if($options['pretend'] || $options['debug'] || $options['verbose']) {
				shell::msg("Starting episode number: $e");
			}
		
			$series_title = $arr[key($arr)]['series_title'];
			$season = $arr[key($arr)]['season'];
			$disc = $arr[key($arr)]['disc'];
			$count = count($arr);
		
			$export = $dvd->export.$dvd->formatTitle($series_title).'/';
 			if(!is_dir($export))
 				mkdir($export, 0755) or die("Can't create export directory $export");
		
			shell::msg($series_title);
			shell::msg("Season: $season  Disc: $disc  Episodes: $count");
			
			foreach($arr as $episode => $tmp) {
			
				$title =& $tmp['title'];
				$track =& $tmp['track'];
				
				// Select the chapter(s) to rip
				// Three possibilities:
				// starting chapter for series
				// one episode = one track w/ one chapter
				// one episode = one track w/ multiple chapters
				if($tmp['series_starting_chapter']) {
					$tmp['starting_chapter'] = $tmp['series_starting_chapter'];
				} elseif(!$tmp['ending_chapter']) {
					$tmp['ending_chapter'] = $tmp['starting_chapter'];
				}
			
				$basename = $dvd->formatTitle($title);
				
				if($dvd->series['unordered'] == 'f')
					$basename = $e.'._'.$basename;
					
				$basename = $export.$basename;
				
				$vob = "$basename.vob";
				$sub = "$basename.sub";
				$idx = "$basename.idx";
				$mkv = "$basename.mkv";
				
				// Check to see if file exists, if not, rip it
				if((!shell::in_dir($vob, $export) && !shell::in_dir($mkv, $export)) || $options['pretend']) {
				
					$msg = "[DVD] ($x/$count) Track $track";
					if($tmp['starting_chapter'])
						$msg .= ", chapter(s) ".$tmp['starting_chapter']."-".$tmp['ending_chapter'];
					$msg .= ": $title";
				
					shell::msg($msg);
					
					if($options['pretend']) {
						shell::msg("[VOB] $vob");
					} else {
						$dvd->rip($vob, $tmp['track'], $tmp['starting_chapter'], $tmp['ending_chapter']);
					}
				
				} else {
					shell::msg("Partial file exists for $series_title: $title");
				}
				$x++;
				
				// Rip VobSub
				if((!shell::in_dir($sub, $export) && !shell::in_dir($mkv, $export)) || $options['pretend']) {
				
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
					
					if($vobsub && !shell::in_dir($sub, $export)) {
						if($options['pretend']) {
							shell::msg("[SUB] $sub");
						} else {
							// Pass the basename, since mencoder dumps to
							// <basename>.idx, .sub
							shell::msg("[SUB] Extracting Subtitles");
							$dvd->sub($basename, $tmp['track'], $tmp['starting_chapter'], $tmp['ending_chapter']);
						}
					}
				
				} else {
					shell::msg("Partial subtitles exists for $series_title: $title");
				}
				
				// Add episode to queue
				if(shell::in_dir($vob, $export)) {
					$dvd->queue($episode);
				}
				
				// Increment episode number
				$e++;
				
			}
		
		}
		
		if($ini['eject'])
			$dvd->eject();
	
	}
	
	if($options['encode']) {
	
		$arr = $dvd->getQueue();
		
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
				
				// Check to see if file exists, if not, rip it
				if(shell::in_dir($vob, $export) && !shell::in_dir($mkv, $export)) {
				
					shell::msg("[MKV] $series_title: $e $title");
 					$dvd->mkvmerge($vob, $mkv, $title, $aspect, $chapters, $atrack, $idx);
					
					if(shell::in_dir($vob, $export) && shell::in_dir($mkv, $export)) {
 						unlink($vob);
					}
					
					if(shell::in_dir($idx, $export) && shell::in_dir($sub, $export) && shell::in_dir($mkv, $export)) {
 						unlink($idx);
 						unlink($sub);
					}
				
				} else {
 					shell::msg("Partial file exists for $title");
				}
				
				// Remove episode from queue
				if(shell::in_dir($mkv, $export)) {
					$sql = "DELETE FROM queue WHERE episode = $episode;";
 					$db->query($sql);
				}
				
			}

			
		}
		
	}
	

?>
