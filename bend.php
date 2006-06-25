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
	 * Echo debugging info
	 *
	 * @param $mixed debug string
	 * @return string
	 */
	function decho($mixed) {
		global $dvd;
		if($dvd->args['debug'] == 1)
			if(is_array($mixed) || is_object($mixed))
				print_r($mixed);
			else
				print_r("Debug: $mixed\n");
	}

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

	/**
	 * Parse a config file
	 *
	 * Format is in param=value
	 *
	 * @param string $file configuration file
	 * @return array
	 */
	function parseConfigFile($file) {
		$readfile = file($file);
		$readfile = preg_grep('/^\w+=(\w|\/)+$/', $readfile);

		foreach($readfile as $key => $value) {
			$arr_split = preg_split('/\s*=\s*/', $value);
			$arr_config[trim($arr_split[0])] = trim($arr_split[1]);
		}

		return $arr_config;
	}

	/**
	 * scandir function, backwards compatible for php4
	 *
	 * @param string $dir directory
	 * @return array
	 */
	if(!function_exists('scandir')) {
		function scandir($dir = './') {
			if(is_dir($dir)){
				if($opendir = opendir($dir)) {
					while (($file = readdir($opendir)) !== false) {
						$arr[] = $file;
					}
					closedir($opendir);
					return $arr;
				}
				else
					return false;
			}
			else {
				trigger_error("Not a directory: $dir", E_USER_WARNING);
				return false;
			}
		}
	}

	// TODO: write this for php4 users
	if(!function_exists('simplexml_load_string')) {
		trigger_error("Sorry, you need PHP5 with SimpleXML support to run bend / dvd2mkv", E_USER_ERROR);
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
		#$arr_config = parseConfigFile($bendrc);
		$arr_config = parse_ini_file($bendrc);
	}
	else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
	}
	
	// Create the DVD object
	$dvd =& new DVD($dvd2mkv);

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
	/*
	if(($argc == 1 && $dvd2mkv == false) || $dvd->args['h'] == 1 || $dvd->args['help'] == 1) {

		$output = "bend is a DVD archiving, ripping, queueing and encoding tool.\n\n";
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

		echo $output;
		die;
	}
	*/
	
	// $arr = $dvd->getTrackStats($dvd->arr_tracks);

	// Clear the queue
	if($dvd->args['clear'] == 1 || $dvd->args['c'] == 1) {
		$dvd->emptyQueue();
	}

	// List the queue
	if($dvd->args['queue'] == 1 || $dvd->args['q'] == 1) {
		$dvd->displayQueue();
	}

	// If the disc is in the drive, for archiving or ripping, then get some basic disc info
	// Otherwise (encoding) everything is already in the database
	if($dvd->args['archive'] == 1 || $dvd->args['rip'] == 1) {

		$dvd->disc_id = $dvd->getDiscID($dvd->config['dvd_device']);
		
		$query_disc = $dvd->queryDisc($dvd->disc_id);
		
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
						$dvd->lsdvd();
						
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
					if($dvd->debug)
						$dvd->msg("[Debug] Season: $season");
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
					if($dvd->debug)
						$dvd->msg("[Debug] Disc: $disc");
				}
				
				// Archive the disc
				$dvd->msg("Archiving your DVD ...");
				$dvd->lsdvd();
				
				$dvd->addDisc($dvd->tv_show['id'], $season, $disc, $dvd->disc_id, $dvd->disc_title, $start);
				
				
			}
			// Just exit gracefully if they don't want to archive it
			else
				die;
		}
	}
	
	die;
	
	// Rip DVD tracks to the harddrive
	if($dvd->args['rip'] == 1) {

		@exec("mount /mnt/dvd 2> /dev/null;");

		// Create the export directory if it doesn't already exist
		if(!is_dir($dvd->export_dir)) {
			@mkdir($dvd->dir, 755); # or die("I couldn't create the export directory: {$dvd->export_dir}");

			// If PHP can't create it, try with `mkdir -p`
			if($bool == false) {
				@exec("mkdir -p {$dvd->export_dir};", $output, $return);
				// TODO: Die on bad return code
				unset($output, $return);
			}
		}

		// Pull out the tracks that haven't been flagged to ignore in the database frontend
		// This query has nothing to do with what has / hasn't been encoded
		$sql = "SELECT track, len FROM episodes WHERE disc = {$dvd->disc['id']} AND ignore = FALSE ORDER BY track;";
		$rs = pg_query($sql) or die(pg_last_error());
		$num_rows = pg_num_rows($rs);

		if($num_rows > 0) {

			// By passing the --tracks flag, you can rip certain tracks only
			if(isset($dvd->args['tracks'])) {
				$tmp = explode('-', $dvd->args['tracks']);

				// This is a little excessive
				foreach($tmp as $key => $value)
					if(!is_int($key))
						unset($tmp['key']);

				if(count($tmp) == 1)
					$tmp[] = $tmp[0];
			}


			#decho($dvd);
			$count = 0;
			while($arr_rip = pg_fetch_assoc($rs_rip)) {
				#decho($arr_rip);
				if($dvd->arr_disc['chapters'] == 't') {
					$track = key($dvd->arr_tracks);
					$chapter = $arr_rip['track'];
				}
				else
					$track = $arr_rip['track'];

				if( !isset($dvd->args['tracks']) || ($track >= $tmp[0] && $track <= $tmp[1]) ) {
					if(isset($chapter))
						$vob = "season_".$dvd->arr_disc['season']."_disc_".$dvd->disc_number."_track_{$track}_chapter_{$chapter}.vob";
					else
						$vob = "season_".$dvd->arr_disc['season']."_disc_".$dvd->disc_number."_track_$track.vob";
					$export_vob = $dvd->export_dir.$vob;

					// If we haven't ripped the disc's VOB already, do so now

					if(!file_exists($export_vob)) {
						echo "Ripping track #$track (Length: {$arr_rip['len']}) to $vob ...\n";
						if(isset($chapter))
							$chapter_flags = "-chapter $chapter-$chapter";
						else
							$chapter_flags = '';
						#$exec = "mplayer dvd://$track $chapter_flags -dumpstream -dumpfile $export_vob ";
						#$dvd->executeCommand($exec);
						$dvd->ripTrack($track, $export_vob, $chapter_flags);
					}

					// Put the episodes in the queue (even if they are already ripped)
					if(isset($chapter))
						$track = $chapter;
					$sql_queue = "UPDATE episodes SET queue = {$dvd->config['queue']} WHERE disc = {$dvd->disc} AND track = $track AND ignore = FALSE;";
					#decho($sql_queue);
					pg_query($sql_queue) or die(pg_last_error());
				}

				$count++;

			}

			if($count > 0)
				echo "Adding $count episodes to the queue.\n";

			system('eject /dev/dvd');
		}
		else {
			die("There aren't any archived tracks to rip for this disc.  You might want to try running --archive instead.\n");
		}
	}




	if($dvd->args['encode'] == 1) {

		$num_encode = $dvd->getQueueTotal($dvd->config['queue']);
		echo "$num_encode episode(s) total to encode.\n";

		if($num_encode > 0) {
			$dvd->encodeMovie();
		}
	}



	// Daemon mode
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

	if($dvd->args['movie'] == 1 || $dvd2mkv === true) {

		if(empty($dvd->args['title'])) {
			echo "Enter a movie title: ";
			$title = fgets($stdin, 255);

			echo "Is this movie an animated cartoon? [y/N] ";
			$cartoon = fgets($stdin, 2);
		}
		else {
			$title = $dvd->args['title'];
			$cartoon = $dvd->args['cartoon'];
		}

		$title = $dvd->escapeTitle($title);
		$vob = "$title.vob";
		$txt = "$title.txt";
		$avi = "$title.avi";
		$mkv = "$title.mkv";

		if(strtolower($cartoon) == 'y' || $cartoon == 1)
			$dvd->arr_encode['cartoon'] = 't';

		$scandir = preg_grep('/(avi|mkv|vob)$/', scandir('./'));

		// Mount/read DVD contents if we need to
		if(!file_exists($txt) || !in_array($vob, $scandir)) {

			$dvd->executeCommand('mount /mnt/dvd');
			$dvd->lsdvd();

			if(!file_exists($txt)) {
				$dvd->arr_encode['chapters'] = $dvd->getChapters($dvd->longest_track);
				$dvd->writeChapters($txt);
			}

			// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
			// so we use scandir and in_array instead
			if(!in_array($vob, $scandir)) {
				echo("Ripping movie track to VOB...\n");
				$dvd->ripTrack($dvd->longest_track, $vob);
				#$exec = "mencoder dvd://{$dvd->longest_track} -ovc copy -oac copy -ofps 24000/1001 -o $vob";
				$dvd->executeCommand('eject');
			}
		}

		$midentify = $dvd->midentify($vob);
		#print_r($midentify);

		switch($midentify['ID_VIDEO_ASPECT']) {
			case '1.7778':
				$arr_ratio = array('640x352', '512x288', '384x208', '256x144');
			break;
		}

		if(count($arr_ratio) > 0) {
			echo "Select an aspect ratio to encode to:\n";
			foreach($arr_ratio as $key => $value) {
				echo " [$key] $value\n";
			}
			echo "Your choice: ";
		}

#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1 -x vob -y xvid4,null $flags -o /dev/null";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -x vob -y xvid4,null $flags -o /dev/null";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -Z 640x360,fast -x vob -y xvid4 $flags -o $avi";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -Z 854x480,fast -x vob -y xvid4 $flags -o $avi";
		#$dvd->executeCommand($exec);
		if(!file_exists($avi) && !file_exists($mkv)) {
			$dvd->transcode($vob, $avi, '-Z 640x360,fast', $mkv);
		}

		if(!file_exists($mkv) && file_exists($avi)) {
			$dvd->createMatroska($avi, $mkv, $txt);
		}

		if(file_exists($mkv)) {
			unlink($vob);
			unlink($avi);
			unlink($txt);
		}

		#print_r($dvd);
		#print_r($chapters);

	}
?>