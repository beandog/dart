#!/usr/bin/php
<?
	ini_set('max_execution_time', 0);
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
	/**

		todo:
		- update functions to use $this->variable less
		- break up functions into smaller chunks
		- functions shouldnt check to see if file_exists, it should just do its job
		- longest track, test arr_tracks

		v2.0
		- Multiple audio tracks
		- subtitles

		bugs:
		- cancelling the script doesn't kill transcode :(
		- export ratio for 16:9 (so widescreen TV doesn't get confused)

	**/
	
	/**
	 * Requirements:
	 * - dvdxchap (part of ogmtools)
	 * - PHP 5 with psql support
	 * - lsdvd >= v.0.16
	 * - transcode >= 1.0.2
	 * - eject
	 */
	 
	function which($binary) {
		exec("which $binary", $foo, $return_var);
		if($return_var == 0)
			return true;
		else
			return false;
	}
	 
	require_once 'inc.pgsql.php';
	require_once 'class.dvd.php';

	/**
	 * Hack to find the user's home directory
	 *
	 * @return string
	 */
	function getHomeDirectory() {
		$whoami = exec('whoami');
		if($whoami == 'root')
			$home = '/root';
		else
			$home = "/home/$whoami";

		return $home;
	}

	// Read the config file
	$home = getHomeDirectory();
	$bendrc = "$home/.bend";

	/** Get the configuration options */
	if(file_exists($bendrc)) {
		$arr_config = parse_ini_file($bendrc);
	}
	else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
	}
	
	
	// Create the DVD object
	$dvd =& new DVD();

	// Set the configuration options
	$dvd->setConfig($argc, $argv, $arr_config);
	
	$arr_cmd = array(
		'h' => array('help', 'Display this help'),
		'a' => array('archive', 'Save disc details in the database'),
		'x' => array('admin', 'Edit disc details, set episode titles, etc.'),
		'r' => array('rip', 'Copy DVD tracks from disc to the harddrive'),
		'e' => array('encode', 'Encode everything in my queue'),
		'q' => array('queue', 'Display the titles in my queue'),
		'c' => array('clear', 'Clear my encoding queue'),
		'v' => array('debug', 'Display debugging information')
	);

	// Display help if no arguments are passed
	if($argc == 1 || $dvd->args['h'] == 1 || $dvd->args['help'] == 1) {

		$dvd->msg("Usage: bend [OPTIONS]");
		$dvd->msg(' ');
		
		foreach($arr_cmd as $key => $arr) {
			$dvd->msg("\t-{$key}, --{$arr[0]}\t{$arr[1]}");
		}
		
		$dvd->msg(' ');
		
		$dvd->msg('You can pass more than one option at a time:');
		$dvd->msg(' ');
		$dvd->msg("\t$ bend --archive --rip --encode");
		$dvd->msg(' ');
		
		$dvd->msg("The simplest way to rip a series is:\n\t1) archive the disc(s),\n\t2) set the episode titles in the frontend\n\t3) rip the tracks to the harddrive and\n\t4) finally encode them.");
	
		$dvd->msg($output);
		// Goodbye! :)
		die;
		
	}
	
	// Clear the queue
	if($dvd->args['clear'] == 1 || $dvd->args['c'] == 1) {
		$dvd->emptyQueue();
	}

	// List the queue
	if($dvd->args['queue'] == 1 || $dvd->args['q'] == 1) {
		$dvd->displayQueue();
	}

	/** Archive Disc */
	if($dvd->args['archive'] == 1 || $dvd->args['rip'] == 1) {
	
		$need_deps = true;
	
		// Check for system requirements on archiving / ripping
		if(!function_exists('simplexml_load_string')) {
			trigger_error("Sorry, you need PHP5 with SimpleXML support to run bend", E_USER_ERROR);
		}
		elseif(!function_exists('preg_grep')) {
			trigger_error("PHP must be compiled with PREG support for bend to run correctly", E_USER_ERROR);
		}
		elseif(!function_exists('bcscale')) {
			trigger_error("PHP must be compiled with BCMath support for bend to run correctly", E_USER_ERROR);
		}
		elseif(!which('mplayer')) {
			trigger_error("You need MPlayer with DVD support installed to rip or archive DVDs", E_USER_ERROR);
		}
		elseif(!which('transcode')) {
			trigger_error("You need Transcode installed to encode DVDs", E_USER_ERROR);
		}
		elseif(!which('lsdvd')) {
			trigger_error("You need lsdvd v0.16 or higher installed to rip or archive discs", E_USER_ERROR);
		}
		elseif(!which('mkvmerge')) {
			trigger_error("You need mkvtoolnix (mkvmerge) installed to make Matroska files", E_USER_ERROR);
		}
		elseif($dvd->args['archive'] == 1 && !which('dvdxchap')) {
			trigger_error("You need ogmtools installed to archive discs", E_USER_ERROR);
		}
		elseif($dvd->args['encode'] == 1 && !which('mkvmerge')) {
			trigger_error("You need mkvmerge installed for encoding", E_USER_ERROR);
		}
		else
			$need_deps = false;
			
		if($need_deps)
			die("One or more script dependencies failed.");

		$dvd->disc_id = $dvd->getDiscID($dvd->config['dvd_device']);
		
		$query_disc = $dvd->queryDisc($dvd->disc_id);
		
		// Check for a max # to rip / encode
		if(isset($dvd->args['total'])) {
			$dvd->args['total'] = intval($dvd->args['total']);
			if($dvd->args['total'] > 0)
				$total = $dvd->args['total'];
			else
				$total = null;
		}

		// If disc is not in the database, it needs to be archived
		if($query_disc === false) {
			$dvd->msg("Your DVD is not in the database.");
			
			if(!isset($dvd->args['archive'])) {
				$archive = $dvd->ask("Would you like to archive it now? [Y/n]", 'y');
				$archive = strtolower($archive);
			}
			
			if($archive == 'y' || $archive == 'yes' || isset($dvd->args['archive'])) {
			
				if(isset($dvd->args['show'])) {
					$show = intval($dvd->args['show']);
					
					if($show > 0) {
						$sql = "SELECT id, title, min_len, max_len, cartoon FROM tv_shows WHERE id = $show;";
						$rs = pg_query($sql) or die(pg_last_error());
						if(pg_num_rows($rs) == 1)
							$dvd->tv_show = pg_fetch_assoc($rs);
					}
				}
				
				if(!isset($dvd->tv_show['id'])) {
				
					// Get the current TV show titles
					$sql = "SELECT id, title, min_len, max_len, cartoon FROM tv_shows ORDER BY title;";
					$rs = pg_query($sql) or die(pg_last_error());
					$num_rows = pg_num_rows($rs);
					
					// If no rows, then we are creating a new title
					if($num_rows == 0) {
						$new_title = true;
						$dvd->msg("There aren't any TV shows in the database.");
					}
					
					// Otherwise, display menu, let them pick the show
					else {
						
						// Build associative array
						for($x = 0; $x < $num_rows; $x++)
							$arr[$x] = pg_fetch_assoc($rs);
							
						// Split the output into pages for the terminal (24 lines per display)
						$arr_chunk = array_chunk($arr, 22, true);
						
						// Keep looping through the selection until they pick one
						do {
							// Display only 24 lines per selection at a time:
							for($x = 0, $y = 1; $x < count($arr_chunk); $x++) {
							
								$dvd->msg("Current TV shows:");
								for($z = 0; $z < count($arr_chunk[$x]); $z++) {
									$dvd->msg("\t$y. {$arr_chunk[$x][($y - 1)]['title']}");
									$y++;
								}
								
								$msg = '';
								if(count($arr_chunk) > 1)
									$msg = "[Page ".($x + 1)."/".count($arr_chunk)."]  Select TV show [NEXT PAGE/#/new]:";
								else
									$msg = "Select TV show [#/new]:";
									
								$input = $dvd->ask($msg, '');
								
								if(strtolower(trim($input)) != 'new')
									$input = intval($input);
								else {
									$new_title = true;
									break 2;
								}
								
								// Break out once they have their selection
								if($input > 0) {
									if($input > $num_rows) {
										$dvd->msg("Please enter a valid selection.", true);
										$input = 0;
									}
									else
										break 1;
								}
							}
						} while($input == 0);
						
						// Put the selected TV show array into the object
						$dvd->tv_show = $arr[($input - 1)];
					}
					
					// Create a new TV show record
					if($new_title === true) {
						$dvd->lsdvd($dvd->config['dvd_device']);
						
						$dvd->msg('');
						if($dvd->debug == false)
							$dvd->msg("Disc Title: ".$dvd->disc_title);
						$title = $dvd->ask("What is the title of this TV show?");
						$min_len = $dvd->ask("What is the minimum TV show length (in minutes)? [20]", 20);
						$max_len = $dvd->ask("What is the maximum TV show length (in minutes)? [60]", 60);
						$cartoon = $dvd->ask("Is this series animated? [y/N]", 0);
						
						$dvd->addTVShow($title, $min_len, $max_len, $cartoon);
					}
				
				}
				
				
				$dvd->msg('');
				$dvd->msg("New disc for '".$dvd->tv_show['title']."'");
				
				/** Disc Season */
				
				// TODO
				// Query old seasons to guess which one this is
				
				// If they didn't pass the CLI argument, ask for it
				if(!isset($dvd->args['season']) || intval($dvd->args['season'] == 0)) {
					do {
						$season = $dvd->ask("What season is this disc? [1]", 1);
						$season = intval($season);
					} while($season == 0);
				}
				// Use the CLI variable if provided
				else {
					$dvd->disc['season'] = $season = intval($dvd->args['season']);
					$dvd->msg("Season: $season", false, true);
				}
				
				/** Disc # */
				
				// Find out which other discs they already have archived
				$sql = "SELECT disc FROM discs WHERE tv_show = {$dvd->tv_show['id']} AND season = $season ORDER BY disc;";
				$rs = pg_query($sql) or die(pg_last_error());
				
				$arr = array();
				for($x = 0; $x < pg_num_rows($rs); $x++)
					$arr[] = current(pg_fetch_row($rs));
				
				$list = implode(', ', $arr);
				
				// Display currently archived discs, if any
				if(count($arr) > 0)
					$dvd->msg("Discs archived for Season $season: $list");
				
				// See if they passed it in the CLI
				if(!isset($dvd->args['disc']) || intval($dvd->args['disc'] == 0)) {
					// First, see if there are any other discs in the database.
					// If there are, assume this new one is the next in line
					// and default the answer to the incremented value.
					
					$sql = "SELECT MAX(disc) FROM discs WHERE tv_show = {$dvd->tv_show['id']} AND season = $season;";
					$rs = pg_query($sql) or die(pg_last_error());
					if(pg_num_rows($rs) == 1) {
						$max = current(pg_fetch_row($rs));
					}
					else
						$max = 0;
					
					// Increment by one -- our starting point
					$max++;
					
					do {
						$disc = $dvd->ask("What number is this disc? [$max]", $max);
						$dvd->disc['number'] = $disc = intval($disc);
						
						if(in_array($disc, $arr)) {
							$dvd->msg("Disc #$disc is already archived.  Choose another number.");
							$disc = 0;
						}
						
					} while($disc == 0);
					
				}
				else {
					$dvd->disc['number'] = $disc = intval($dvd->args['disc']);
					$dvd->msg("Disc: $disc", false, true);
				}
				
				// Archive the disc
				$dvd->msg("Archiving your DVD ...");
				$dvd->lsdvd($dvd->config['dvd_device']);
				
				$dvd->addDisc($dvd->tv_show['id'], $season, $disc, $dvd->disc_id, $dvd->disc_title);
			}
			// Just exit gracefully if they don't want to archive it
			else
				die;
		}
	}
	
	/** Rip DVD tracks to the harddrive */
	if($dvd->args['rip'] == 1) {
	
		// Get some disc information
		if(!isset($dvd->disc)) {
			if($dvd->getDisc() === false) {
				$dvd->msg("I couldn't find your disc in the database.  You need to run --archive first.", true);
			}
		}
		
		// Mount disc (decreases readtime)
		// TODO see if its already mounted ... somehow?
		if($dvd->config['mount'] === true) {
			$dvd->msg("Attempting to mount disc.", true, true);
			$dvd->mount();
		}

		// Create the export directory if it doesn't already exist
		if(!is_dir($dvd->config['export_dir'])) {
			@mkdir($dvd->config['export_dir'], 755); # or die("I couldn't create the export directory: {$dvd->export_dir}");

			// If PHP can't create it, try with `mkdir -p`
			if($bool == false) {
				@exec("mkdir -p {$dvd->config['export_dir']};", $output, $return);
				// TODO: Die on bad return code
				unset($output, $return);
			}
		}

		// Pull out the tracks that haven't been flagged to ignore in the database frontend
		// This query has nothing to do with what has / hasn't been encoded
		
		$sql = "SELECT tv.title, tv.unordered, d.season, d.disc, t.id AS track_id, t.track, t.multi, d.id AS disc_id, COALESCE(e.starting_chapter, tv.starting_chapter) AS starting_chapter, e.id AS episode_id, e.chapter FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id AND d.id = {$dvd->disc['id']} INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND t.bad_track = FALSE ORDER BY t.track, e.episode_order;";
		$rs = pg_query($sql) or die(pg_last_error());
		$num_rows = pg_num_rows($rs);
		
		if($num_rows) {
		
			$dvd->msg("$num_rows track(s) total to rip and enqueue.");

			$count = $q = 0;
			
			for($x = 0; $x < $num_rows; $x++)
				$arr[$x] = pg_fetch_assoc($rs);
				
			$title = $dvd->formatTitle($arr[0]['title']);
			
			$dir = $dvd->config['export_dir'].$title;
			
			// TODO die with $dvd->msg
			if(!is_dir($dir))
				mkdir($dir) or die("Can't create export directory!");
			
			$dvd->msg("Ripping {$arr[0]['title']}: Season {$arr[0]['season']}, Disc {$arr[0]['disc']}");
			
			/** Rip the tracks */
			foreach($arr as $id => $tmp) {
						
				extract($tmp);
				
				$file = "season_{$season}_disc_{$disc}_track_{$track}";
				
				if($multi == 't')
					$file .= "_chapter_{$chapter}";
					
				$avi = "$file.avi";
				$mkv = "$file.mkv";
				$vob = "$file.vob";
				
				$efn = $dvd->getEpisodeFilename($disc_id, $track, $episode_id, $unordered);
				
				$count++;
				
				$display_count = str_pad($count, strlen($num_rows), 0, STR_PAD_LEFT);

				// See if we've reached our total or not
				if($q === $total) {
					$dvd->msg("Reached total of $total tracks + episodes to rip.");
					break;
				}
				
				// See if it was flagged to ignore after starting
				$sql = "SELECT ignore, bad_track FROM episodes e INNER JOIN tracks t ON e.track = t.id WHERE e.id = $episode_id;";
				$rs = pg_query($sql);
				$row = pg_fetch_assoc($rs);
				
				if($row['ignore'] == 't' || $row['bad_track'] == 't') {
					$dvd->msg("[$display_count/$num_rows] - Track $track: Updated to ignore / bad track.");
				} else {
				
					// Get the directory list each time, so that you can rip the same disc
					// in two sessions at once.  Possible, but definately not advised.  It
					// slows down the ripping horribly.
					
					$filename = "$dir/$vob";
					
					$episode_title = $dvd->getEpisodeTitle($episode_id);
						
					if(!$episode_title)
						$episode_title = "season $season, disc $disc, track $track";
					
					// Check to see if file exists, if not, rip it
					if(!in_dir($vob, $dir) && !in_dir($avi, $dir) && !in_dir($mkv, $dir) && !in_dir($efn, $dir)) {
					
						// Delete the episode from the queue
						$sql = "DELETE FROM queue WHERE episode = $episode_id;";
						pg_query($sql) or die(pg_last_error());
					
						$dvd->msg("[$display_count/$num_rows] + Track $track: \"$episode_title\"");
						
						// On multiple episodes per track, we need to know the starting
						// and ending chapters.
						if($multi == 't') {
							
							// See if the next episode is on the same track
// 							if($arr[$id + 1]['track'] == $track) {
// 								
// 							}

							// The starting and stopping track are the same
							$dvd->ripTrack($track_id, $track, $filename, $multi, $chapter, $chapter);
							
						// Otherwise, just the starting chapter (if set) for the track
						} else {
							$dvd->ripTrack($track_id, $track, $filename, $multi, $starting_chapter);
						}
						
						$q++;
						
					} else {
						
						$display_file_exists = '';
						
						if(in_dir($vob, $dir))
							$display_file_exists = 'MPEG-2 (VOB)';
						elseif(in_dir($avi, $dir))
							$display_file_exists = 'encoded AVI';
						elseif(in_dir($mkv, $dir))
							$display_file_exists = 'Matroska';
						elseif(in_dir($efn, $dir))
							$display_file_exists = 'final Matroska';
					
						$dvd->msg("[$display_count/$num_rows] - Track $track: \"$episode_title\" $display_file_exists file exists.");
					}
						
					// Put the episodes in the queue
					if(!in_dir($efn, $dir) && !in_dir($mkv, $dir) && in_dir($vob, $dir))
						$dvd->archiveAudioVideoTracks($episode_id, $filename);
					
					if(!in_dir($efn, $dir)) {
						if($dvd->enqueue($episode_id)) {
							$enqueue++;
						} else {
							$in_queue++;
						}
					}
				}
			}

			if($q > 0) {
				$dvd->msg("Finished ripping files to $dir");
			}
			
			if($enqueue) {
				$dvd->msg("Added $enqueue episodes to the queue.");
			}
			
			if($in_queue) {
				$dvd->msg("$in_queue episodes already in your queue", false, true);
			}
			
			if($dvd->config['eject']) {
				$dvd->msg("Attempting to eject disc.", true, true);
				system('eject '.$dvd->config['dvd_device']);
			}
		}
		else {
			$dvd->msg("There aren't any archived tracks to rip for this disc.  You might want to try running --archive instead.", true);
		}
	}

	/** Encoding */
	if($dvd->args['encode'] == 1) {

		$num_encode = $dvd->getQueueTotal($dvd->config['queue_id']);
		$dvd->msg("$num_encode episode(s) total to encode.");
		
		$q = 0;

		while($num_encode) {
			$dvd->arr_encode = $dvd->getQueue($dvd->config['queue_id']);

			// See if we've reached our total or not
			if($q === $total) {
				$dvd->msg("Reached total of $total episodes to encode.");
				break 2;
			}
			
			foreach($dvd->arr_encode as $arr) {
			
				$dvd->encodeMovie($arr);
				$q++;
			}
			
			$num_encode = $dvd->getQueueTotal($dvd->config['queue']);
		}
	}

	/** Encoding daemon mode */
	// This will sleep while there is nothing to encode, waiting for something to
	// be updated to the queue.
	while($dvd->args['daemon'] == 1) {

		$num_encode = $dvd->getQueueTotal($dvd->config['queue']);

		if($num_encode == 0) {
			#echo "I'm out of things to encode, and I'm running in daemon mode, so I'm going to sleep ...\n";
			sleep(200);
		}
		else {
			$dvd->encodeMovie();
		}
	}
	
	function in_dir($file, $dir) {
		$arr = scandir($dir);
		if(in_array($file, $arr))
			return true;
		else
			return false;
	}

?>
