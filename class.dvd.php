<?
	class DVD {

		/*
		 * DVD construct
		 *
		 * Creaates database connection, and default values
		 */
		function DVD($dvd2mkv = false) {
			if($dvd2mkv == false)
				$this->db = pg_connect('host=charlie dbname=movies user=steve') or die(pg_last_error());
			$this->min_len = 20;
			$this->max_len = 59;
		}
		
		function addTVShow($title = 'TV Show', $min_len = 30, $max_len = 60, $fps = 0, $cartoon = false) {
			$title = pg_escape_string(trim($title));
			$min_len = intval($min_len);
			$max_len = intval($max_len);
			$fps = intval($fps);
			if(substr(trim(strtolower($cartoon)), 0, 1) == 'y') {
				$pg_cartoon = 'TRUE';
				$cartoon = true;
			}
			else {
				$pg_cartoon = 'FALSE';
				$cartoon = false;
			}
			
			$sql = "SELECT NEXTVAL('public.tv_shows_id_seq');";
			$id = current(pg_fetch_row(pg_query($sql))) or die(pg_last_error());
			
			$sql = "INSERT INTO tv_shows (id, title, min_len, max_len, fps, cartoon) VALUES ($id, '$title', $min_len, $max_len, $fps, $pg_cartoon);";
			pg_query($sql) or die(pg_last_error());
			
			$this->tv_show = compact('id', 'title', 'min_len', 'max_len', 'fps', 'cartoon');
			return true;
		}
		
		function addDisc($tv_show, $season, $disc, $disc_id, $disc_title) {
		
			if(!isset($this->arr_tracks))
				$this->lsdvd($this->config['dvd_device']);
			
			$arr_tracks = $this->getValidTracks($this->arr_tracks, $this->tv_show['min_len'], $this->tv_show['max_len']);
			
			/*
			if($disc > 1) {
				$this->disc['start'] = $this->getStartingEpisode();
			}
			else
				$this->disc['start'] = 1;
			
			$start = $this->disc['start'];
			*/
				
			// Insert disc into database
			$sql = "SELECT NEXTVAL('public.discs_id_seq');";
			$id = current(pg_fetch_row(pg_query($sql))) or die(pg_last_error());
			
			$sql = "INSERT INTO discs (id, tv_show, season, disc, disc_id, disc_title) VALUES ($id, $tv_show, $season, $disc, '$disc_id', '$disc_title');";
			#echo $sql;
			pg_query($sql) or die(pg_last_error());
			
			// Rebuild disc object array
			$this->disc = compact('id', 'tv_show', 'season', 'disc', 'disc_id', 'disc_title', 'start');
			
			#print_r($this->disc);
			
			$episode = 0;
			
			foreach($arr_tracks as $track => $valid) {
				$chapters = $this->getChapters($track, $this->config['dvd_device']);
				
				if($valid)
					$episode++;
				
				// Don't insert tracks with zero length
				if($this->arr_tracks[$track] != '0.00')
					$this->archiveEpisode($this->disc['id'], $episode, $this->arr_tracks[$track], $chapters, $track, $valid);
			}
			
			if($this->num_episodes > 0)
				echo("Archived {$this->num_episodes} episodes, be sure to set the titles in the frontend.\n");
			
		}
		
		function getValidTracks($arr_tracks = null, $min_len = 20, $max_len = 60, $chapters = false) {
		
			if(!is_array($arr_tracks))
				return false;
			
			$min_len = intval($min_len);
			$max_len = intval($max_len);

			$add = $ignore = 0;

			// If we are ripping only one track as chapters, then ignore the length
			// FIXME, not used
			if($chapters === true) {
				/*
				foreach($this->arr_tracks as $key => $value) {
					if($key != $arr_disc['chapters_track'])
						unset($this->arr_tracks[$key]);
				}
				*/
			}
			else {
				// Trim the tracks that do not meet the min and max length criteria
				foreach($arr_tracks as $key => $value) {

					if($value > $max_len || $value < $min_len) {
						$this->msg("- Track $key ($value)", true, true);
						$arr_valid[$key] = false;
						$ignore++;
					}
					else {
						$this->msg("+ Track $key ($value)", true, true);
						$add++;
						$arr_valid[$key] = true;
					}
				}
			}

			decho("$add tracks will be added to the database");
			decho("$ignore tracks ignored because of length");
			
			return $arr_valid;
		}
		

		function getStartingEpisode() {
			// Calculate the starting episode # for this disc
			// Also, always update it each time, since the title orders may have changed
			echo $sql = "SELECT discs.disc, episodes.episode, episodes.title FROM episodes, discs WHERE discs.tv_show = {$this->tv_show['id']} AND season = {$this->disc['season']} AND discs.disc < {$this->disc['number']} AND episodes.disc = discs.id AND episodes.ignore = FALSE ORDER BY discs.disc, episodes.episode;";
			$rs = pg_query($sql) or die(pg_last_error());
			$start = pg_num_rows($rs);
			
			$start++;

			return($start);
		}

		function archiveEpisode($disc_id, $episode, $len, $chapters, $track, $valid = false) {
		
			$disc_id = intval($disc_id);
			$episode = intval($episode);
			$len = pg_escape_string($len);
			$chapters = pg_escape_string(trim($chapters));
			
			if(!is_null($queue))
				$queue = intval($queue);
				
			$track = intval($track);
			$valid = intval($valid);
			
			if($valid == 0) {
				$episode = 'NULL';
				$ignore = 't';
			}
			else
				$ignore = 'f';
				
			$sql = "INSERT INTO episodes (disc, episode, len, chapters, track, ignore) VALUES ($disc_id, $episode, $len, '$chapters', $track, '$ignore');";
			pg_query($sql) or die(pg_last_error());
			#die;
		
		}

		function ask($string, $default = false) {
			if(is_string($string)) {
				#$handle = fopen('php://stdin', 'r');
				fwrite(STDOUT, "$string ");
				$input = fread(STDIN, 255);
				#fclose($handle);
				
				if($input == "\n") {
					return $default;
				}
				else {
					$input = trim($input);
					return $input;
				}
			}
		}

		function mkvmerge($avi = 'movie.avi', $txt = 'chapters.txt', $mkv = 'movie.mkv') {
				$this->msg("Wrapping AVI and chapters into Matroska");
				$exec = "mkvmerge -o \"$mkv\" --chapters $txt $avi";
				decho($exec);
				$this->executeCommand($exec);
		}

		function createSnapshot($input, $output) {
			$exec = "mplayer $input -vo png:z=9 -ss 440 -frames 1 -vf scale=360:240 -ao null; mv 00000001.png $output";
		}

		function correctLength($len) {
			$hours = substr($len, 0, 2);
			$len = substr($len, 3);
			$len = ($hours * 60) + $len;

			return $len;
		}

		function displayQueue() {
			$sql_queue = "SELECT episodes.episode, episodes.title, episodes.len, tv_shows.title AS tv_show_title FROM episodes, discs, tv_shows WHERE queue = {$this->config['queue_id']} AND episodes.disc = discs.id AND discs.tv_show = tv_shows.id AND ignore = FALSE ORDER BY tv_shows.title, episodes.disc, episodes.id;";
			#decho($sql_queue);
			$rs_queue = pg_query($sql_queue) or die(pg_last_error());
			
			if($this->debug)
				$this->decho("Queue ID: {$this->config['queue_id']}", true);
			
			if(pg_num_rows($rs_queue) == 0)
				$this->msg("Your encoding queue is empty.", true);
			
			while($arr_queue = pg_fetch_assoc($rs_queue)) {
				echo "$i. ".$arr_queue['tv_show_title'].": ".$arr_queue['title']." (".$arr_queue['len'].")\n";
			}
		}
		
		function formatTitle($title = 'TV Show Title') {
			$title = preg_replace('/[^A-Za-z ]/', '', $title);
			$title = str_replace(' ', '_', $title);
			return $title;
		}
		
		function encodeMovie() {
			
			#$this->export_dir = $this->getExportDir($this->arr_encode['tv_show_title'], $this->arr_encode['season']);

			echo $trunk = $this->export_dir."season_".$this->arr_encode['season']."_disc_".$this->arr_encode['disc_number']."_track_".$this->arr_encode['track'];
			die;

			#if($this->arr_encode['one_chapter'] == 't')
			#	$trunk = $this->export_dir."season_".$this->arr_encode['season']."disc_".$this->arr_encode['disc_number']."_track_".$this->arr_encode['chapters_track']."_chapter_{$this->arr_encode['track']}";

			// Ripped DVD title
			$this->arr_encode['vob'] = $vob = "$trunk.vob";
			// Encoded AVI
			$this->arr_encode['avi'] = $avi = "$trunk.avi";
			// Title chapters
			$this->arr_encode['txt'] = $txt = "$trunk.txt";
			// transcode divx4.log
			$this->arr_encode['log'] = $log = "$trunk.log";

			$episode = $this->getEpisodeNumber();

			$mkv = $this->export_dir.$this->arr_encode['season'].$episode." ".$this->arr_encode['title'].".mkv";
			$this->arr_encode['mkv'] = $mkv = str_replace(' ', '_', $mkv);

			decho("Matroska file: $mkv");
			#print_r($this->arr_encode);

			$chapters = $this->arr_encode['chapters'];

			// Actually transcode the episode
			if(empty($this->arr_encode['title']))
				$this->arr_encode['title'] = "track_{$this->arr_encode['track']}";
			$output = "Transcoding {$this->arr_encode['tv_show_title']}: {$this->arr_encode['title']}\n";
			$strlen = strlen($output);
			echo str_repeat('=', $strlen)."\n$output".str_repeat('=', $strlen)."\n";

			// See if its flagged to encode as a cartoon
			if($this->arr_encode['cartoon'] == 't')
				$config_dir = "{$this->config['home_dir']}.transcode/cartoon";
			// Get the config dir flag (overrides cartoon flag)
			elseif(isset($this->args['config_dir']))
				$config_dir = $this->args['config_dir'];

			#echo $config_dir;

			#$this->transcode($vob, $avi, $mkv, $chapters, $txt, $log, $config_dir, $this->arr_encode['fps'], $this->args['debug']);
			$this->transcode($vob, $avi, '', $mkv, $config_dir, $this->arr_encode['fps']);

			$this->createMatroska($avi, $mkv, $txt);

			$this->tidyUp($vob, $avi, $mkv, $txt);

			// Empty the queue for this episode
			// Even if the transcode failed, we have to clear it, otherwise we'll get stuck in a loop
			// since the script will see which one is next in line.
			echo "Removing \"{$this->arr_encode['title']}\" from the queue.\n";
			$sql_update = "UPDATE episodes SET queue = NULL WHERE id = {$this->arr_encode['episode_id']};";
			pg_query($sql_update) or die(pg_last_error());

			/*
			if($args['daemon'] == 1) {
				$num_encode = $this->getQueueTotal($arr_drip['queue_id']);
				echo "$num_encode episode(s) total to encode.\n";
			}
			*/
		}

		function emptyQueue() {
			$sql_queue = "DELETE FROM episodes WHERE queue = {$this->config['queue_id']};";
			pg_query($sql_queue) or die(pg_last_error());
		}

		function escapeTitle($str) {
			$str = trim($str);
			$arr_pattern = array('/\s+/', '/\W/');
			$arr_replace = array('_', '');
			$str = preg_replace($arr_pattern, $arr_replace, $str);
			return $str;
		}

		function executeCommand($str) {

			$str = escapeshellcmd($str);

			if($this->args['debug'] == 1) {
				$str .= ';';
				$this->msg("Executing command: '$str'", false, true);
				exec($str);
			}
			elseif($this->args['debug'] == 2) {
				$str .= ';';
				$this->msg("Executing command: '$str'", false, true);
				system($str);
			}
			elseif($this->args['debug'] == 3) {
				$str .= ';';
				$this->msg("Executing command: '$str'", false, true);
				passthru($str);
			}
			else {
				$str .= ' 2> /dev/null;';
				exec($str);
			}
		}

		function getChapters($track, $dvd_device = '/dev/dvd') {

			$track = intval($track);
			if($track == 0) {
				$this->msg("Passed an invalid track # to get chapters from!", true);
				die;
			}
			else {
				$exec = "dvdxchap $dvd_device -t $track 2> /dev/null";
				if($this->verbose)
					$this->msg($exec, true);
				
				exec($exec, $arr);
				#decho($arr);

				$chapters = implode("\n", $arr);

				return $chapters;
			}
		}


		function getDisc() {
			if(!isset($this->disc_id))
				$this->getDiscID();
			$sql = "SELECT id, tv_show, season, disc, disc_title FROM discs WHERE disc_id = '{$this->disc_id}' LIMIT 1;";
			$rs = pg_query($sql) or die(pg_last_error());
			if(pg_num_rows($rs) == 1) {
				$this->disc = pg_fetch_assoc($rs);
				return true;
			}
			else
				return false;
		}
		
		function getDiscID($device = '/dev/dvd', $disc_id_binary = '/usr/bin/disc_id') {
			if(!empty($device)) {
				$disc_id = exec("$disc_id_binary $device");
				
				$this->msg("Disc ID: $disc_id", false, true);
				
				return $disc_id;
			}
			else
				return false;
		}

		function getEpisodeNumber() {
			$sql_start = "SELECT discs.disc, episodes.episode, episodes.title FROM episodes, discs WHERE discs.tv_show = {$this->arr_encode['tv_show']} AND season = {$this->arr_encode['season']} AND discs.disc < {$this->arr_encode['disc_number']} AND episodes.disc = discs.id AND episodes.ignore = FALSE ORDER BY discs.disc, episodes.episode;";
			$rs_start = pg_query($sql_start) or die(pg_last_error());
			$this->arr_encode['start'] = pg_num_rows($rs_start);

			$episode = $this->arr_encode['start'] + $this->arr_encode['episode'];
			// Pre-pad the files with 0 so that they will show in correct order by the filesystem
			$episode = str_pad($episode, 2, 0, STR_PAD_LEFT);

			return $episode;

		}

		function getExportDir($title, $season) {

			$export_dir = trim($this->config['export_dir']).preg_replace('/\W+/', '_', trim($title))."/";
			decho("Setting export directory to: $export_dir");

			return $export_dir;
		}

		function getQueue($id) {

			$sql = "SELECT episodes.id AS episode_id, episodes.title, episodes.chapters, episodes.track, discs.id AS disc_id, discs.tv_show AS tv_show, discs.season, discs.disc AS disc_number, discs.chapters AS one_chapter, discs.chapters_track, tv_shows.title AS tv_show_title, tv_shows.cartoon, tv_shows.fps FROM episodes, discs, tv_shows WHERE queue = $id AND episodes.disc = discs.id AND discs.tv_show = tv_shows.id AND ignore = FALSE ORDER BY tv_shows.title, episodes.disc, episodes.id;";

			$rs = pg_query($sql) or die(pg_last_error());
			for($x = 0; $x < pg_num_rows($rs); $x++)
				$arr[$x] = pg_fetch_assoc($rs);

			return $arr;
		}

		function getQueueTotal($queue_id) {

			$queue_id = intval($queue_id);

			$sql_encode = "SELECT 1 FROM episodes WHERE queue = $queue_id AND ignore = FALSE;";
			$num_encode = pg_num_rows(pg_query($sql_encode));

			return $num_encode;
		}
		
		/** 
		 * Calculate stats about the tracks
		 * Experimental
		 * @param array
		 * @return array
		 */
		function getTrackStats($arr) {
			$arr = preg_grep('/0\.00/', $arr, PREG_GREP_INVERT);
			
			
			
			$arr_count = array_count_values($arr);
			#print_r($arr_count);
			
			foreach($arr as $value) {
				
			
				$group = ceil($value / 10);
				$len = floor($value);
				if($group > 0) {
					$count[$group]++;
				}
				
			}
			
			arsort($count);
			
			#print_r($count);
			#die;
			
			$max = max($arr);
			$min = min($arr);
			$avg = round(array_sum($arr) / count($arr), 2);
			
			return array($max, $min, $avg);
		}
			
	
		function lsdvd($dvd = '/dev/dvd') {
			#exec('lsdvd -q 2> /dev/null', $arr);
			$xml = `lsdvd -Ox $dvd 2> /dev/null`;

			$lsdvd = simplexml_load_string($xml);

			// Get the "Disc Title:" string
			$this->disc_title = (string)$lsdvd->title;
			
			$this->msg("Disc Title: ".$this->disc_title, false, true);
			
			// Get the disc ID (libdvdread)
			if(!isset($this->disc_id))
				$this->disc_id = $this->getDiscId($dvd);
			
			// Longest track
			$this->longest_track = (int)$lsdvd->longest_track;
			$this->longest_track = 1;

			// Build the array of tracks and their lengths
			foreach($lsdvd->track as $obj) {
				$this->arr_tracks[(int)$obj->ix] = number_format((float)$obj->length / 60, 2, '.', '');
			}

			return true;
		}

		function midentify($file = 'movie.vob') {

			exec("midentify $file", $midentify);

			foreach($midentify as $key => $value) {
				$arr_split = preg_split('/\s*=\s*/', $value);
				$arr[trim($arr_split[0])] = trim($arr_split[1]);
			}

			return $arr;

		}
		
		function msg($string = '', $stderr = false, $debug = false) {
		
			if($debug === true) {
				if($this->debug == true)
					$string = "[Debug] $string";
				else
					$string = '';
			}
		
			if(!empty($string)) {
				if($stderr === true) {
					fwrite(STDERR, "$string\n");
				}
				else {
					fwrite(STDOUT, "$string\n");
				}
			}
			return true;
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
			else
				return array();
		}
		
		/**
		 * Query a Disc ID to see if it's in the database
		 *
		 * @param string Disc ID
		 * @return bool
		 */
		function queryDisc($disc_id) {
			$disc_id = pg_escape_string(trim($disc_id));
			
			$sql = "SELECT 1 FROM discs WHERE disc_id = '$disc_id';";
			$rs = pg_query($sql) or die(pg_last_error());
			
			if(pg_num_rows($rs) == 1)
				return true;
			else
				return false;
		}

		function ripTrack($track, $vob = 'movie.vob') {
			$this->msg("Ripping to $vob", false, true);
			$exec = "mplayer -dvd-device {$this->config['dvd_device']} dvd://$track -dumpstream -dumpfile $vob";
			$this->executeCommand($exec);
		}
		
		/**
		 * Set configuration options
		 *
		 * @param int Argument count
		 * @param array Argument array
		 * @param array Configuration options
		 */
		function setConfig($argc, $argv, $arr_config) {
			if(is_int($argc) && is_array($argv) && is_array($arr_config)) {
			
				$this->config = $arr_config;
				
				// Fix some stuff about the 'parse_ini_file' php function I don't like
				if($this->config['mount'] == '1')
					$this->config['mount'] = true;
				else
					$this->config['mount'] = false;
				if($this->config['eject'] == '1')
					$this->config['eject'] = true;
				else
					$this->config['eject'] = false;
			
				if(substr($argv[0], -7, 7) == 'dvd2mkv')
					$this->dvd2mkv = true;
				else
					$this->dvd2mkv = false;
					
				$this->args = $this->parseArguments($argc, $argv);
					
				if(isset($this->args['min']))
					$this->min_len = $this->args['min'];
				if(isset($this->args['max']))
					$this->max_len = $this->args['max'];
				
				if(isset($this->args['debug']))
					$this->debug = true;
				else
					$this->debug = false;
					
				#print_r($this);
				
				return true;
			}
			else {
				trigger_error("Couldn't parse your configuration options", E_USER_WARNING);
				return false;
			}
		}

		function tidyUp($vob, $avi, $mkv, $txt) {
			// Tidy up
			if(file_exists($avi) && file_exists($vob))
				unlink($vob);
			if(file_exists($txt))
				unlink($txt);

			// Delete the AVI once we're sure the MKV is there. ;)
			if(file_exists($mkv) && file_exists($avi)) {
				unlink($avi);
				if(file_exists('divx4.log')) {
					#copy('divx4.log', $log);
					unlink('divx4.log');
				}
			}
		}

		#$this->transcode($vob, $avi, '', $mkv, $config_dir, $this->arr_encode['fps']);

		function transcode($vob, $avi, $fps = 0) {

			$flags = '';

			if($fps == 1)
				$flags = "-f 24,1 ";

			// Two-pass encoding the VOB to AVI
			// By default, use XviD for excellent results
			if(!file_exists($avi) && !file_exists($mkv)) {
				$config_file = "{$config_dir}/xvid4.cfg";

				if(!empty($config_dir) && file_exists($config_file)) {
					decho("Reading configuration from '$config_file'\n");
					$flags .= "--config_dir $config_dir ";
				}

				$this->msg("*** Encoding to AVI, pass 1 of 2 ...");
				echo $exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1 -x vob -y xvid4,null $flags -o /dev/null";
				$this->executeCommand($exec);

				$this->msg("*** Encoding to AVI, pass 2 of 2 ...");
				$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 2 -x vob -y xvid4 $flags -o $avi";
				$this->executeCommand($exec);
			}
		}

		function writeChapters($chapters = '', $txt = 'movie.txt') {
			$handle = fopen($txt, 'w') or die('error');
			fwrite($handle, $chapters);
			fclose($handle);
		}
	}
	
?>