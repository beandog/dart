#!/usr/bin/php
<?
	ini_set('max_execution_time', 0);
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
	/**

		todo:
		- fix functions so they dont use file_exists on LARGE files (use scandir instead)
		- update functions to use $this->variable less
		- break up functions into smaller chunks
		- functions shouldnt check to see if file_exists, it should just do its job
		- lsdvd XML
		- longest track, test arr_tracks

		v2.0
		- Multiple audio tracks
		- subtitles

		bugs:
		- cancelling the script doesn't kill transcode :(
		- do I need -B 4,4,4,4?
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
	 
	// Check for system requirements
	
	exec("which mencoder", $foo, $return_var);
	
	if($return_var == 1) {
		die('You must have mencoder installed to use this program.');
	}
	exec("which lsdvd", $foo, $return_var);
	if($return_var == 1)
		die('You must have lsdvd v0.16 installed to use this program.');
	
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

	// TODO: write this for php4 users
	if(!function_exists('simplexml_load_string')) {
		trigger_error("Sorry, you need PHP5 with SimpleXML support to run bend", E_USER_ERROR);
		die;
	}

	// Read the config file
	$home = getHomeDirectory();
	$bendrc = "$home/.bend";

	// Default configuration
	$arr_config = array(
		'bitrate' => 2200,
		'export_dir' => "$home/dvd/",
		'home_dir' => "$home/",
		'queue' => 3,
		'device' => '/dev/dvd'
	);

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
		'a' => array('archive', 'Archive a disc and title in the database'),
		'r' => array('rip', 'Rip the current DVD in the drive to VOBs'),
		'e' => array('encode', 'Encode the first ripped VOB in my queue'),
		'd' => array('daemon', 'Run in the background, encoding everything in my queue'),
		'q' => array('queue', 'Display the titles in my queue'),
		'c' => array('clear', 'Clear my queue')
	);


	// Display help if no arguments are passed
	if($argc == 1 || $dvd->args['h'] == 1 || $dvd->args['help'] == 1) {

		$dvd->msg("Usage: bend [OPTIONS]");
		$dvd->msg(' ');
		$dvd->msg("\t-h, --help\tThis help message");
		$dvd->msg("\t-a, --archive\tArchive a disc in the database");
		$dvd->msg("\t-r, --rip\tRip tracks to the harddrive");
		$dvd->msg("\t-e, --encode\tEncode all files in my queue to Matroska");
		$dvd->msg("\t-q, --queue\tDisplay my queue");
		$dvd->msg("\t-d, --debug\tDisplay debugging output");
		
		$dvd->msg(' ');
		
		$dvd->msg('The standard way to rip a series is 1) archive the disc(s), 2) set the names in the frontend 3) rip the tracks to the harddrive and 4) finally encode them.');
		
		/*
		$output .= "Main\n";
		$output .= "====\n";
		$output .= "\n";
		$output .= "  -h, --help\t\tDisplay this help\n";
		$output .= "  -a, --archive\t\tArchive a title in the database\n";
		$output .= "  -r, --rip\t\tRip the current DVD in the drive to VOB files\n";
		$output .= "  -e, --encode\t\tEncode the first ripped VOB in my queue\n";
		$output .= "  -d, --daemon\t\tRun as a daemon in the background, encoding everything in my queue\n";
		$output .= "  -q, --queue\t\tDisplay the titles, episodes in my queue\n";

		$output .= "\n";

		$output .= "Archiving (TV Shows)\n";
		$output .= "====================\n";
		$output .= "\n";
		$output .= "  --season n\t\tTV Season # (Ex: --season 2)\n";
		$output .= "  --disc n\t\tDisc # (Ex: --disc 1)\n";
		$output .= "  --title 'TV Show'\tTitle of TV show (Ex: --title 'Mary Tyler Moore'\n";
		$output .= "  --min n\t\tOptional: minimum track length, in minutes\n";
		$output .= "  --max n\t\tOptional: maximum track length, in minutes\n";


		$output .= "\n";

		$output .= "Archiving (Movies)\n";
		$output .= "==================\n";
		$output .= "\n";
		$output .= "  --movie\t\tArchive DVD as a movie, not a TV show\n";
		$output .= "  --track\t\tOptional: Rip track # as movie, instead of longest track found\n";
		*/

		$dvd->msg($output);
		die;
		
	}
	
	// $arr = $dvd->getTrackStats($dvd->arr_tracks);

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
						$sql = "SELECT id, title, min_len, max_len, fps, cartoon FROM tv_shows WHERE id = $show;";
						$rs = pg_query($sql) or die(pg_last_error());
						if(pg_num_rows($rs) == 1)
							$dvd->tv_show = pg_fetch_assoc($rs);
					}
				}
				
				if(!isset($dvd->tv_show['id'])) {
				
					// Get the current TV show titles
					$sql = "SELECT id, title, min_len, max_len, fps, cartoon FROM tv_shows ORDER BY title;";
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
						// TODO:
						// Ask for the framerate (PAL, NTSC, Autodetect);
						$fps = 0;
						// Ask "is this correct"
						
						$dvd->addTVShow($title, $min_len, $max_len, $fps, $cartoon);
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
			@exec("mount {$dvd->config['dvd_device']} 2> /dev/null;");
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
		$sql = "SELECT tv.title, d.season, d.disc, e.track, d.id AS disc_id FROM episodes e INNER JOIN discs d ON e.disc = d.id AND d.id = {$dvd->disc['id']} INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE ignore = FALSE ORDER BY track;";
		$rs = pg_query($sql) or die(pg_last_error());
		$num_rows = pg_num_rows($rs);
		
		if($num_rows > 0) {
		
			$dvd->msg("$num_rows track(s) total to rip and enqueue.");

			$count = $q = 0;
			
			for($x = 0; $x < $num_rows; $x++)
				$arr[$x] = pg_fetch_assoc($rs);
				
			$title = $dvd->formatTitle($arr[0]['title']);
			
			$dir = $dvd->config['export_dir'].$title;
			
			// TODO die with $dvd->msg
			if(!is_dir($dir))
				mkdir($dir) or die("Can't create export directory!");
				
			
			/** Rip the tracks */
			foreach($arr as $tmp) {
						
				extract($tmp);
				
				$file = "season_{$season}_disc_{$disc}_track_{$track}";
				$avi = "$file.avi";
				$mkv = "$file.mkv";
				$vob = "$file.vob";
				
				$efn = $dvd->getEpisodeFilename($disc_id, $track);
				
				$count++;

				// See if we've reached our total or not
				if($q === $total) {
					$dvd->msg("Reached total of $total episodes to rip.");
					break;
				}
				
				// Get the directory list each time, so that you can rip the same disc
				// in two sessions at once.  Possible, but definately not advised.  It
				// slows down the ripping horribly.
				
				// Check to see if file exists, if not, rip it
				if(!in_dir($vob, $dir) && !in_dir($avi, $dir) && !in_dir($mkv, $dir) && !in_dir($efn, $dir)) {
				
					$episode_title = $dvd->getEpisodeTitle($dvd->disc['id'], $track);
					
					if(!$episode_title)
						$episode_title = "season $season, disc $disc, track $track";
					
					$filename = "$dir/$vob";
					
					$dvd->msg("[$count/$num_rows] Ripping $title: $episode_title");
					$dvd->ripTrack($track, $filename);
					
					// Put the episodes in the queue
					$sql = "UPDATE episodes SET queue = {$dvd->config['queue_id']} WHERE disc = {$dvd->disc['id']} AND track = $track AND ignore = FALSE;";
					pg_query($sql) or die(pg_last_error());
				
					$q++;
					
				}
				else
					$dvd->msg("[$count/$num_rows] Skipping track $track, file already exists.");
			}

			if($q > 0) {
				$dvd->msg("Finished ripping files to $dir");
				$dvd->msg("Added $q episodes to the queue.");
			}
			
			if($dvd->config['eject'] === true) {
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

		while($num_encode > 0) {
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
	
#	if($dvd->config['eject'])
#		system("eject {$dvd->config['dvd_device']};");

?>
