<?
	class DVD {

		/**
		 * DVD construct
		 *
		 * Creaates database connection, and default values
		 */
		function __construct() {
			$this->db = pg_connect('host=charlie dbname=movies user=steve') or die(pg_last_error());
			$this->min_len = 20;
			$this->max_len = 59;
			
			// Default config settings
			$this->config = array(
				'queue_id' => 0,
				'transcode_video_codec' => 'xvid4',
				'transcode_video_bitrate' => 2200,
				'transcode_audio_codec' => 'copy',
				'transcode_audio_bitrate' => 128,
				'export_dir' => './',
				'dvd_device' => '/dev/dvd',
				'mount' => true,
				'eject' => true
			);
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
				
			// Insert disc into database
			$sql = "SELECT NEXTVAL('public.discs_id_seq');";
			$id = current(pg_fetch_row(pg_query($sql))) or die(pg_last_error());
			
			$sql = "INSERT INTO discs (id, tv_show, season, disc, disc_id, disc_title) VALUES ($id, $tv_show, $season, $disc, '$disc_id', '$disc_title');";
			#echo $sql;
			pg_query($sql) or die(pg_last_error());
			
			// Rebuild disc object array
			$this->disc = compact('id', 'tv_show', 'season', 'disc', 'disc_id', 'disc_title', 'start');
			
			$episode = 0;
			
			foreach($arr_tracks as $track => $valid) {
				$chapters = $this->getChapters($track, $this->config['dvd_device']);
				
				if($valid)
					$episode++;
				
				// Don't insert tracks with zero length
				if($this->arr_tracks[$track] != '0.00') {
					$this->archiveEpisode($this->disc['id'], $episode, $this->arr_tracks[$track], $chapters, $track, $valid);
					$this->enqueue($episode);
				}
			}
			
			if($this->num_episodes > 0)
				echo("Archived {$this->num_episodes} episodes, be sure to set the titles in the frontend.\n");
			
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
				
			$sql = "INSERT INTO episodes (disc, episode_order, len, chapters, track, ignore) VALUES ($disc_id, $episode, $len, '$chapters', $track, '$ignore');";
			pg_query($sql) or die(pg_last_error());
		}

		function ask($string, $default = false) {
			if(is_string($string)) {
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
		
		function correctLength($len) {
			$hours = substr($len, 0, 2);
			$len = substr($len, 3);
			$len = ($hours * 60) + $len;

			return $len;
		}
		
		function createSnapshot($input, $output, $ss = 60) {
			$ss = intval($ss);
			if($ss > 0) {
			
				/*
				$exec = "mplayer \"$input\" -vo png:z=9 -ss $ss -frames 1 -vf scale=360:240 -ao null";
				$this->executeCommand($exec);
				rename("00000001.png", "$output");
				*/
				
				$exec = "transcode -i \"$input\" -o snapshot -T 1,-1 -x vob,null -F 90 -y jpg,null -c 5400-5401";
				$this->executeCommand($exec);
				rename("snapshot000000.jpg", "$output");
				
				$this->msg($exec);
			}
		}
		
		/**
		 * displayQueue()
		 *
		 * Displays the episodes that are in the encoding queue
		 * for the current client
		 *
		 */
		function displayQueue() {
			$sql = "SELECT e.id, tv.title AS tv_show_title, d.season, e.title AS episode_title, e.len AS episode_len FROM queue q INNER JOIN episodes e ON e.id = q.episode INNER JOIN discs d ON e.disc = d.id INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND q.queue = {$this->config['queue_id']} ORDER BY q.insert_date;";
			
			$rs = pg_query($sql) or die(pg_last_error());
			
			$this->msg("Queue ID: {$this->config['queue_id']}", false, true);
			
			if(pg_num_rows($rs) == 0)
				$this->msg("Your encoding queue is empty.", true);
			$i = 1;
			while($arr = pg_fetch_assoc($rs)) {
				$this->msg("$i. ".$arr['tv_show_title']." (Season {$arr['season']}): ".$arr['episode_title']." (".$arr['episode_len'].")");
				$i++;
			}
			return true;
		}
		
		function emptyQueue() {
			$sql_queue = "DELETE FROM queue WHERE queue = {$this->config['queue_id']};";
			pg_query($sql_queue) or die(pg_last_error());
		}
		
		function encodeMovie($arr = array()) {
		
			extract($arr);
			
			$title = $this->formatTitle($tv_show_title);
			$dir = $this->config['export_dir'].'/'.$title.'/';
			
			$file = "season_{$season}_disc_{$disc_number}_track_{$track}";
			
			$vob = "$file.vob";
			$log = "$file.log";
			$avi = "$file.avi";
			$txt = "$file.txt";
			$mkv = "$file.mkv";
			$episode_title = $this->getEpisodeTitle($disc_id, $track);
			$filename = $this->getEpisodeFilename($disc_id, $track);
			$png = basename($filename, '.mkv').'.png';
			
			// Change to the directory so the 2 pass stats are dumped there,
			// and so is xvid4.cfg
			chdir($dir);
			
			// By default, use XviD for excellent results
			$config_file = getenv('HOME').'/.transcode/xvid4.cfg';
			$tmp_config_file = $dir.'xvid4.cfg';
			if(file_exists($config_file) && $cartoon == 't') {
				if(!file_exists($tmp_config_file)) {
					copy($config_file, $tmp_config_file);
				}
				$exec = "sed --in-place -e s/cartoon\ =\ 0/cartoon\ =\ 1/ xvid4.cfg";
				$this->executeCommand($exec, true);
			}
			
			if(in_dir($vob, $dir) && !in_dir($avi, $dir)) {
				$msg = "Encoding: $tv_show_title";
				if($episode_title)
					$msg .= ": $episode_title";
				$this->msg($msg);
				
				if($fps == 2 || $fps == 1)
					$this->mencoder($vob, $cartoon, $mencoder_aid);
				else
					$this->transcode($vob, $fps);
			}
			
			// Dump the chapters to a text file
			if(in_dir($avi, $dir) && !in_dir($txt, $dir)) {
				$this->writeChapters($chapters, $txt);
			}
				
			// Create the Matroska file
			if(in_dir($avi, $dir) && !in_dir($mkv, $dir)) {
				$this->msg("Wrapping AVI and chapters into Matroska");
				$this->mkvmerge($avi, $txt, $mkv);
			}
			
			// Rename the matroska file from to Episode_Title.mkv
			if(in_dir($mkv, $dir) && !in_dir($filename, $dir)) {
				$this->msg("Moving $mkv to $filename", false, true);
				rename($mkv, $filename);
			}
			
			// Create a snapshot
			if(in_dir($filename, $dir) && !in_dir($png, $dir)) {
				$this->msg("Creating a PNG snapshot", false, true);
				#$this->createSnapshot($filename, $png);
			}
			
			// Remove the VOB, AVI and chapters file if the Matroska exists
			if(in_dir($filename, $dir) || in_dir($mkv, $dir)) {
				if(in_dir($vob, $dir))
					unlink($vob);
				if(in_dir($log, $dir))
					unlink($log);
				if(in_dir($avi, $dir))
					unlink($avi);
				if(in_dir($txt, $dir))
					unlink($txt);
			}
			
			// Delete from queue so we don't get stuck in a loop
			$sql = "DELETE FROM queue WHERE episode = $episode_id;";
			pg_query($sql) or die(pg_last_error());
		}
		
		/**
		 * enqueue()
		 *
		 * Insert an episode into the queue
		 *
		 * @param int Episode ID
		 */
		function enqueue($episode) {
			$episode = intval($episode);
			
			$num_rows = pg_num_rows(pg_query("SELECT 1 FROM queue WHERE queue = ".$this->config['queue_id']." AND episode = $episode;"));
			
			if($num_rows === 1)
				return false;
			else {
				$sql = "INSERT INTO queue (queue, episode) VALUES (".$this->config['queue_id'].", $episode);";
				pg_query($sql) or die(pg_last_error());
				return true;
			}
		}
		
		function escapeTitle($str) {
			$str = trim($str);
			$arr_pattern = array('/\s+/', '/\W/');
			$arr_replace = array('_', '');
			$str = preg_replace($arr_pattern, $arr_replace, $str);
			return $str;
		}
		
		function executeCommand($str, $do_not_escape = false) {

			if($do_not_escape === false)
				$str = escapeshellcmd($str);

			if($this->debug) {
				#$str .= ';';
				$this->msg("Executing command: '$str'", false, true);
				passthru($str);
			}
			/*
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
			*/
			else {
				$str .= ' 2> /dev/null;';
				exec($str);
			}
		}
		
		function formatTitle($title = 'TV Show Title') {
			$title = preg_replace('/[^A-Za-z0-9 \-,.?\':]/', '', $title);
			$title = str_replace(' ', '_', $title);
			return $title;
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
		
		function getEpisodeFilename($disc_id, $track) {
			$episode = $this->getEpisodeNumber($disc_id, $track);
			$episode_title = $this->getEpisodeTitle($disc_id, $track);
			$filename = $episode.'._'.$this->formatTitle($episode_title).'.mkv';
			return $filename;
		}
		
		function getEpisodeID($track) {
			$sql = "SELECT id FROM episodes WHERE disc = {$this->disc['id']} AND track = $track LIMIT 1;";
			$episode = current(pg_fetch_row(pg_query($sql)));
			return $episode;
		}

		function getEpisodeNumber($disc_id, $track) {
			// This query dynamically returns the correct episode # out of the entire season for a TV show based on its track #.
			// It works by calculating the number of valid tracks that come before it
			// So, you can archive discs outside of their order, just don't transcode them
			// or your numbering scheme will be off.
			
			$season = current(pg_fetch_row(pg_query("SELECT season FROM discs WHERE id = $disc_id;")));
			
			$sql = "SELECT (COUNT(1) + 1) AS episode_number FROM episodes, discs WHERE episodes.disc = discs.id AND discs.tv_show = (SELECT tv_show FROM discs WHERE id = $disc_id) AND episodes.ignore = false AND season = (SELECT season FROM discs WHERE id = $disc_id) AND ((discs.disc < (SELECT disc FROM discs WHERE id = $disc_id)) OR (discs.disc = (SELECT disc FROM discs WHERE id = $disc_id) AND episode_order < (SELECT episode_order FROM episodes WHERE disc = $disc_id AND track = $track)));";
			$rs = pg_query($sql) or die(pg_last_error());
			
			$episode = current(pg_fetch_row($rs));
			
			// I'm not padding the seasons, because I'm assuming there's not
			// going to be any show that has more than 9, of which
			// something is going to prove me wrong, I know.
			// Hmm ... Simpsons, anyone?  Meh.  Patch it yourself. :)
			$episode = $season.str_pad($episode, 2, 0, STR_PAD_LEFT);

			return $episode;
		}
		
		function getEpisodeTitle($disc, $track) {
			$disc = intval($disc);
			$track = intval($track);
			
			if($disc == 0 || $track == 0)
				return false;
			
			$sql = "SELECT title FROM episodes WHERE disc = $disc AND track = $track;";
			$rs = pg_query($sql) or die(pg_last_error());
			
			if(pg_num_rows($rs) == 0)
				return false;
			
			$title = current(pg_fetch_row($rs));
			
			if(empty($title))
				return false;
			else
				return $title;
		}

		function getExportDir($title, $season) {

			$export_dir = trim($this->config['export_dir']).preg_replace('/\W+/', '_', trim($title))."/";

			return $export_dir;
		}
		
		function getQueue() {
			$sql = "SELECT e.id AS episode_id, e.title, e.chapters, e.track, d.id AS disc_id, d.tv_show, d.season, d.disc AS disc_number, tv.title AS tv_show_title, tv.cartoon, tv.fps, tv.mencoder_aid FROM queue q INNER JOIN episodes e ON e.id = q.episode INNER JOIN discs d ON e.disc = d.id INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND q.queue = {$this->config['queue_id']} ORDER BY q.insert_date;";

			$rs = pg_query($sql) or die(pg_last_error());
			for($x = 0; $x < pg_num_rows($rs); $x++)
				$arr[$x] = pg_fetch_assoc($rs);

			return $arr;
		}
		
		function getQueueTotal() {

			$sql_encode = "SELECT COUNT(1) FROM queue q INNER JOIN episodes e ON q.episode = e.id AND e.ignore = false WHERE q.queue = ".$this->config['queue_id'].";";
			$num_encode = current(pg_fetch_row(pg_query($sql_encode)));

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
			
			foreach($arr as $value) {
				$group = ceil($value / 10);
				$len = floor($value);
				if($group > 0) {
					$count[$group]++;
				}
			}
			
			arsort($count);
			
			$max = max($arr);
			$min = min($arr);
			$avg = round(array_sum($arr) / count($arr), 2);
			
			return array($max, $min, $avg);
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

			$this->msg("$add tracks will be added to the database");
			$this->msg("$ignore tracks ignored because of length");
			
			return $arr_valid;
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
		
		function mencoder($vob, $cartoon = false, $aid = null) {
			$flags = '';
			
			if(is_numeric($aid))
				$flags = " -aid $aid ";
			if($cartoon == 't') {
				$xvidencopts = ':cartoon=1';
				$flags .= " -vf pullup,softskip ";
			}
			
			$basename = basename($vob, '.vob');
			$avi = "$basename.avi";
			$log = "$basename.log";
			
			$max = 1;
			
			$pass1 = "mencoder $vob -o $avi -ovc xvid -oac copy $flags -xvidencopts bitrate=2200{$xvidencopts} ";
			
			$this->msg("[Pass 1/$max] VOB => AVI");
			$this->executeCommand($pass1);

		}

		function midentify($file = 'movie.vob') {

			exec("midentify $file", $midentify);

			foreach($midentify as $key => $value) {
				$arr_split = preg_split('/\s*=\s*/', $value);
				$arr[trim($arr_split[0])] = trim($arr_split[1]);
			}

			return $arr;

		}
		
		function mkvmerge($avi = 'movie.avi', $txt = 'chapters.txt', $mkv = 'movie.mkv') {
				$exec = "mkvmerge --aspect-ratio 0:4/3 $avi -o \"$mkv\" --chapters $txt";
				$this->executeCommand($exec);
		}
		
		function mount() {
			$exec = "mount {$this->config['dvd_device']}";
			$this->executeCommand($exec);
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
					
				return true;
			}
			else {
				trigger_error("Couldn't parse your configuration options", E_USER_WARNING);
				return false;
			}
		}

		function transcode($vob, $fps = 0) {
			$flags = '';
			
			$basename = basename($vob, '.vob');
			$avi = "$basename.avi";
			$log = "$basename.log";
			
			if($this->debug) {
				$flags .= ' --print_status 10 ';
				$verbose = ':verbose=1';
			}
			
			// For 23.97, specify the framerate
			if($fps == 1)
				$flags .= " -f 23.976,1 ";
			// Variable framerate
			#elseif($fps == 2) {
				//$flags .= " -f 0,4 ";
			#	$flags .= " --export_fps 23.976,1 --hard_fps ";
			#}
			
			if(!empty($config_dir) && file_exists($config_file)) {
				$this->msg("Reading configuration from '$config_file'", true);
				$flags .= "--config_dir $config_dir ";
			}
			
			// Set transcode debug level
			$q = intval($this->debug);

			if($fps < 2) {
				$pass1 = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1,$log -x vob,vob -y xvid4,null $flags -o /dev/null -q $q";
				$pass2 = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 2,$log -x vob,vob -y xvid4 $flags -o $avi -q $q";
				$max = 2;
			}
			// Variable framerate
			// This isn't a magic bullet, but sure does work most of the time
			elseif($fps == 2) {
				#$pass1 = "mencoder $vob -aid 128 -ovc xvid -oac copy -o $avi -mc 0 -noskip -fps 24000/1001";
				$pass1 = "transcode -i $vob -x vob,vob -f 0,4 -M 2 -R 3 -w 2 --export_frc 1 -J ivtc -J decimate -B 3,9,16 --hard_fps -J 32detect=force_mode=5:chromathres=2:chromadi=9 -y xvid -o $avi -A -N 0x2000 -a 0 -b 128,0,0";
				$max = 1;
				if(isset($pass2))
					unset($pass2);
			}
			
			$this->msg("[Pass 1/$max] VOB => AVI");
			$this->executeCommand($pass1);

			if($pass2) {
				$this->msg("[Pass 2/$max] VOB => AVI");
				$this->executeCommand($pass2);
			}
		}

		function writeChapters($chapters = '', $txt = 'movie.txt') {
			$handle = fopen($txt, 'w') or die('error');
			fwrite($handle, $chapters);
			fclose($handle);
		}
	}
	
?>
