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
	 */

	require_once 'inc.pgsql.php';
	require_once 'class.dvd.php';

	$stdout = fopen('php://stdout', 'w');
	$stdin = fopen('php://stdin', 'r');
	$stderr = fopen('php://stderr', 'w');

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
	 * Parse CLI arguments
	 *
	 * If a value is unset, it will be set to 1
	 *
	 * @param $argc argument count (system variable)
	 * @param $argv argument array (system variable)
	 * @return array
	 */
	function parseArguments($argc, $argv) {
		if($argc > 1) {
			array_shift($argv);

			for($x = 0; $x < count($argv); $x++) {
				if(preg_match('/^(-\w$|--\w+)/', $argv[$x]) > 0) {
					$argv[$x] = preg_replace('/^-{1,2}/', '', $argv[$x]);
					$args[$argv[$x]] = 1;
				}
				else {
					if(in_array($argv[($x-1)], array_keys($args))) {
						$args[$argv[($x-1)]] = $argv[$x];
					}
				}
			}

			return $args;
		}
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
				trigger_error("Not a directory: $dir", E_WARNING);
				return false;
			}
		}
	}

	// TODO: write this for php4 users
	if(!function_exists('simplexml_load_string')) {
		trigger_error("Sorry, you need PHP5 with SimpleXML support to run bend / dvd2mkv", E_ERROR);
	}

	// Read the config file
	$home = getHomeDirectory();
	$bendrc = "$home/.bendrc";

	// Default configuration
	$arr_config = array(
		'bitrate' => 2200,
		'export_dir' => "$home/dvd/",
		'home_dir' => "$home/",
		'queue' => 3,
		'device' => '/dev/dvd'
	);

	if(file_exists($bendrc)) {
		#$arr_config = parseConfigFile($bendrc);
		$arr_config = parse_ini_file($bendrc);
	}
	else {
		trigger_error("No config file found, using defaults", E_WARNING);
	}

	#print_r($arr_config);
	#die;

	// Create the DVD object
	$dvd =& new DVD();

	if(substr($argv[0], -7, 7) == 'dvd2mkv')
		$dvd2mkv = true;
	else
		$dvd2mkv = false;

	// Set the configuration flags
	$dvd->config = $arr_config;
	
	// Grab the commandline arguments
	$dvd->args = parseArguments($argc, $argv);
	
	#print_r($dvd->args);
	#die;

	// Set min, max length
	if(isset($dvd->args['min']))
		$dvd->min_len = $dvd->args['min'];
	if(isset($dvd->args['max']))
		$dvd->max_len = $dvd->args['max'];

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

	// Clear the queue
	if($dvd->args['clear'] == 1) {
		$dvd->emptyQueue();
	}

	// List the queue
	if($dvd->args['queue'] == 1 || $dvd->args['q'] == 1) {
		$dvd->displayQueue();
	}

	// If the disc is in the drive, for archiving or ripping, then get some basic disc info
	// Otherwise (encoding) everything is already in the database
	if($dvd->args['archive'] == 1 || $dvd->args['rip'] == 1) {

		$dvd->lsdvd();

		$matches = $dvd->getMatches();
		
		if(count($matches) == 1) {
		 	echo "Found an exact match!!\n";
		 	$dvd->tv_show = $matches[0];
		}
		elseif(count($matches) > 1 && !isset($dvd->args['tv_show'])) {
			die("There is more than one matching title.  Pass --id <id> to continue.\n");
		}
		elseif(count($matches) > 1 && isset($dvd->args['id']) && is_numeric($dvd->args['id'])) {
			$dvd->tv_show = $dvd->args['id'];
		}
		elseif(count($matches) == 0 && !isset($dvd->args['title'])) {
			die("This is a new tv_show.  Pass --title <title> to create a new record, or --id <id> to use an existing.\n");
		}

		if($dvd->args['archive'] == 1) {

			// Archive the title
			if(count($matches) == 0 && isset($dvd->args['title']) && !empty($dvd->args['title'])) {
				decho("Archiving title.");
				$dvd->archiveTitle();
			}

			// Archive the disc
			$disc = $dvd->getDisc();
			if($disc === false) {
				$dvd->archiveDisc();
				echo "Archived disc: {$dvd->disc_title}\n";
				$disc = $dvd->getDisc();
			}

			// Archive the episodes
			if($disc === true) {
				$sql_episodes = "SELECT 1 FROM episodes WHERE disc = {$dvd->disc} AND ignore = FALSE;";
				$rs_episodes = pg_query($sql_episodes) or die(pg_last_error());
				$dvd->num_episodes = pg_num_rows($rs_episodes);

				if($dvd->num_episodes == 0) {
					$dvd->archiveEpisodes();
				}
			}
		}
	}

	// Rip DVD tracks to the harddrive
	if($dvd->args['rip'] == 1) {

		@exec("mount /mnt/dvd 2> /dev/null;");
		$dvd->getDisc();

		// Set the export directory (where to save ripped files)
		$dvd->export_dir = $dvd->getExportDir($dvd->title, $dvd->season);

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
		$sql_rip = "SELECT track, len FROM episodes WHERE disc = {$dvd->disc} AND ignore = FALSE ORDER BY track;";
		$rs_rip = pg_query($sql_rip) or die(pg_last_error());
		$num_rips = pg_num_rows($rs_rip);

		if($num_rips > 0) {

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