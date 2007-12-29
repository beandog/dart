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
		
		function addTVShow($title = 'TV Show', $min_len = 30, $max_len = 60, $cartoon = false) {
			$title = pg_escape_string(trim($title));
			$min_len = intval($min_len);
			$max_len = intval($max_len);
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
			
			$sql = "INSERT INTO tv_shows (id, title, min_len, max_len, cartoon) VALUES ($id, '$title', $min_len, $max_len, $pg_cartoon);";
			pg_query($sql) or die(pg_last_error());
			
			$this->tv_show = compact('id', 'title', 'min_len', 'max_len', 'cartoon');
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
			
			$episode = 1;
			
			foreach($arr_tracks as $track => $valid) {
				
				// Don't insert tracks with zero length
				if($this->arr_tracks[$track] != '0.00') {
					#$this->archiveEpisode($this->disc['id'], $episode, $this->arr_tracks[$track], $chapters, $track, $valid);
					$track_id = $this->archiveTrack($this->disc['id'], $episode, $this->arr_tracks[$track], $track, $valid);
					
					$episode++;
					
					$chapters = $this->getChapters($track, $this->config['dvd_device']);
					$this->archiveTrackChapters($track_id, $chapters);
					
				}
			}
			
		}
		
		function archiveAudioVideoTracks($episode, $vob) {
		
			$episode = intval($episode);
			
			if(!$episode)
				return false;
			
			// Find the audio/video tracks in the VOB
			exec("mkvmerge -i $vob", $arr);
			
			// Strip out just track information
			$arr = preg_grep('/^Track ID/', $arr);
			
			$sql = "DELETE FROM episode_tracks WHERE episode = $episode;";
			pg_query($sql) or die(pg_last_error());

			// Only archive the tracks if there's more than one audio + one video
			if(count($arr) > 2) {
				foreach($arr as $str) {
					$tmp = explode(':', $str);
					$track_id = str_replace('Track ID ', '', $tmp[0]);
					
					$av = 0;
					
					if(strpos($tmp[1], 'video') !== false)
						$av = 1;
					
					// ONLY store audio tracks for now.  I think that I'll never see
					// multiple video tracks, so I'm not going to check for it now.
					if($av === 0) {
						$sql = "INSERT INTO episode_tracks (episode, av, track_id) VALUES ($episode, $av, $track_id);";
						pg_query($sql) or die(pg_last_error());
					}
				}
			}
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
		
		function archiveTrack($disc_id, $episode, $len, $track, $valid = false) {
		
			$disc_id = intval($disc_id);
			$len = pg_escape_string($len);
			$chapters = pg_escape_string(trim($chapters));
			
			if(!is_null($queue))
				$queue = intval($queue);
				
			$track = intval($track);
			$valid = intval($valid);
			
			$ignore = ($valid == 0 ? 't' : 'f');
			
			$sql = "SELECT NEXTVAL('public.tracks_id_seq');";
			$id = current(pg_fetch_row(pg_query($sql))) or die(pg_last_error());
				
			$sql = "INSERT INTO tracks (id, disc, track, len) VALUES ($id, $disc_id, $track, $len);";
			pg_query($sql) or die(pg_last_error());
			
			// Create a blank episode for each track, assuming a one-to-one
			// relationship for now, until the frontend corrects the data.
			$sql = "INSERT INTO episodes (track, episode_order, ignore) VALUES ($id, $episode, '$ignore');";
			pg_query($sql) or die(pg_last_error());
			
			return $id;
		}
		
		function archiveTrackChapters($track, $chapters) {
			
			$track = intval($track);
			$chapters = trim($chapters);
			
			$arr = explode("\n", $chapters);
			$arr = preg_grep('/^CHAPTER\d+=/', $arr);
			$arr = preg_replace('/^CHAPTER\d+=/', '', $arr);
			$arr = array_unique($arr);
			
			// Only store the chapters if there is more than one
			if(count($arr) > 1) {
				
				$chapter = 1;
				
				foreach($arr as $start_time) {
				
					// Convert start times to seconds
					$tmp = explode(':', $start_time);
					
					$seconds = ($tmp[0] * 60 * 60) + ($tmp[1] * 60);
					$seconds = bcadd($seconds, $tmp[2], 3);
				
					$sql = "INSERT INTO track_chapters (track, start_time, chapter) VALUES ($track, $seconds, $chapter);";
					pg_query($sql) or die(pg_last_error());
					
					$chapter++;
				}
			}
			
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
			$sql = "SELECT e.id, tv.title AS tv_show_title, d.season, e.title AS episode_title, t.len AS episode_len FROM queue q INNER JOIN episodes e ON e.id = q.episode INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND e.title != '' AND q.queue = {$this->config['queue_id']} ORDER BY q.insert_date;";
			
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
			
			#print_r($arr); die;
			
			$mkv_title = $this->formatTitle("$tv_show_title: $title", false);
			$title = $this->formatTitle($tv_show_title);
			$dir = $this->config['export_dir'].$title.'/';
			
			chdir($dir);
			
			$file = "season_{$season}_disc_{$disc_number}_track_{$track}";
			
			if(!empty($chapter)) {
				$chapter = intval($chapter);
				$file .= "_chapter_$chapter";
			}
			
			$vob = "$file.vob";
			$log = "$file.log";
			$avi = "$file.avi";
			$txt = "$file.txt";
			$mkv = "$file.mkv";
			$episode_title = $this->getEpisodeTitle($episode_id);
			$filename = $this->getEpisodeFilename($disc_id, $track, $episode_id, $unordered);
			$png = basename($filename, '.mkv').'.png';
			
			$msg = "Encoding: $tv_show_title";
			if($episode_title)
				$msg .= ": $episode_title";
			$this->msg($msg);
			
			
			
			// Someday, we'll use mencoder profiles to encode stuff
			$avi = $vob;
			$display_format = 'MPEG2';
			
			if($multi == 'f') {
				$arr['chapters'] = $this->getTrackChapters($track_id);
				
				#print_r($arr['chapters']);
				
				// Dump the chapters to a text file
				if(in_dir($avi, $dir) && !in_dir($txt, $dir) && !empty($arr['chapters'])) {
					$chapters = implode("\n", $arr['chapters']);
					$this->writeChapters($chapters, $txt);
				}
			}
			
			// Create the Matroska file
			if(in_dir($avi, $dir) && !in_dir($mkv, $dir)) {
				$this->msg("Wrapping $display_format and chapters into Matroska format");
				$this->mkvmerge($avi, $txt, $mkv, $mkv_title, $mkvmerge_atrack);
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
			
			// Don't delete from the queue if the file doesn't exist
			// For daemon mode, delete from queue so we don't get stuck in a loop
			if(in_dir($filename, $dir) || $dvd->args['daemon'] ) {
				$sql = "DELETE FROM queue WHERE episode = $episode_id;";
				pg_query($sql) or die(pg_last_error());
			}
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
			$arr_pattern = array('/\s+/', "/[^A-Za-z_0-9\-,.?':!]/");
			$arr_replace = array('_', '');
			$str = preg_replace($arr_pattern, $arr_replace, $str);
			return $str;
		}
		
		function executeCommand($str, $do_not_escape = false, $verbose = false) {

			if($do_not_escape === false)
				$str = escapeshellcmd($str);

			if($this->debug) {
				#$str .= ';';
				$this->msg("Executing command: '$str'", false, true);
				passthru($str);
			} elseif($verbose) {
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
		
		function formatTitle($title = 'TV Show Title', $underlines = true) {
			$title = preg_replace("/[^A-Za-z0-9 \-,.?':!]/", '', $title);
			$underlines && $title = str_replace(' ', '_', $title);
			return $title;
		}
		
		function getChapters($track, $dvd_device = '/dev/dvd', $starting_chapter = 1) {
		
			if(!is_null($starting_chapter))
				$starting_chapter = intval($starting_chapter);

			$track = intval($track);
			if($track == 0) {
				$this->msg("Passed an invalid track # to get chapters from!", true);
				die;
			}
			else {
				$exec = "dvdxchap $dvd_device -t $track -c $starting_chapter 2> /dev/null";
				if($this->verbose)
					$this->msg($exec, true);
				
				exec($exec, $arr);

				$chapters = implode("\n", $arr);

				return $chapters;
			}
		}
		
		function getDisc() {
			if(!isset($this->disc_id))
				$this->disc_id = $this->getDiscID();
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
		
		function getEpisodeFilename($disc_id, $track, $episode_id, $unordered = 'f') {
			$episode = $this->getEpisodeNumber($episode_id);
			$episode_title = $this->getEpisodeTitle($episode_id);
			
			$filename = $this->formatTitle($episode_title).'.mkv';
			
			if($unordered != 't')
				$filename = $episode.'._'.$filename;
				
			return $filename;
		}
		
		function getEpisodeID($track) {
			$sql = "SELECT id FROM episodes WHERE disc = {$this->disc['id']} AND track = $track LIMIT 1;";
			$episode = current(pg_fetch_row(pg_query($sql)));
			return $episode;
		}

		function getEpisodeNumber($episode_id) {
			// This query dynamically returns the correct episode # out of the entire season for a TV show based on its track #.
			// It works by calculating the number of valid tracks that come before it
			// So, you can archive discs outside of their order, just don't transcode them
			// or your numbering scheme will be off.
			
			
			$sql = "SELECT d.tv_show, d.season, d.disc AS disc_number, e.episode_order, t.track FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE e.id = $episode_id;";
			$rs = pg_query($sql) or die(pg_last_error());
			$row = pg_fetch_assoc($rs);
			extract($row);
			
			#$sql = "SELECT (COUNT(1) + 1) AS episode_number FROM episodes, tracks, discs WHERE episodes.track = tracks.id AND tracks.disc = discs.id AND discs.tv_show = (SELECT tv_show FROM discs WHERE id = $disc_id) AND episodes.ignore = false AND season = (SELECT season FROM discs WHERE id = $disc_id) AND ((discs.disc < (SELECT disc FROM discs WHERE id = $disc_id)) OR (discs.disc = (SELECT disc FROM discs WHERE id = $disc_id) AND episode_order < (SELECT episode_order FROM episodes e INNER JOIN tracks t ON e.track = t.id WHERE t.disc = $disc_id AND e.track = $track)));";
			
			// Need to calculate:
			// # of epsiodes on previous discs plus 
			// # of episodes on current disc plus earlier tracks plus
			// # of episodes on current disc plus current track plus earlier episodes
			$sql = "SELECT (count(e.id) + 1) FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE d.tv_show = $tv_show AND d.season = $season AND t.bad_track = FALSE AND e.ignore = FALSE AND ( (d.disc < $disc_number) OR (d.disc = $disc_number AND t.track < $track ) OR (d.disc = $disc_number AND t.track = $track AND e.episode_order < $episode_order ));";

			$rs = pg_query($sql) or die(pg_last_error());
			
			$episode = current(pg_fetch_row($rs));
			
			// I'm not padding the seasons, because I'm assuming there's not
			// going to be any show that has more than 9, of which
			// something is going to prove me wrong, I know.
			// Hmm ... Simpsons, anyone?  Meh.  Patch it yourself. :)
			$episode = $season.str_pad($episode, 2, 0, STR_PAD_LEFT);

			return $episode;
		}
		
		function getEpisodeTitle($episode_id) {
			$episode_id = intval($episode_id );
			
			if($episode_id == 0)
				return false;
			
			$sql = "SELECT title FROM episodes WHERE id = $episode_id ;";
			$rs = pg_query($sql) or die(pg_last_error());
			
			if(pg_num_rows($rs) == 0)
				return false;
			
			$title = current(pg_fetch_row($rs));
			
			if(empty($title))
				return false;
			else
				return $title;
		}

		function getQueue() {
			
			$sql = "SELECT e.id AS episode_id, e.title, e.chapter, e.chapters, t.track, t.multi, t.id AS track_id, d.id AS disc_id, d.tv_show, d.season, d.disc AS disc_number, tv.title AS tv_show_title, tv.cartoon, tv.unordered FROM queue q INNER JOIN episodes e ON e.id = q.episode INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id INNER JOIN tv_shows tv ON d.tv_show = tv.id WHERE e.ignore = FALSE AND e.title != '' AND q.queue = {$this->config['queue_id']} ORDER BY q.insert_date;";
			
			$rs = pg_query($sql) or die(pg_last_error());
			for($x = 0; $x < pg_num_rows($rs); $x++)
				$arr[$x] = pg_fetch_assoc($rs);

			return $arr;
		}
		
		function getQueueTotal() {

			$sql_encode = "SELECT COUNT(1) FROM queue q INNER JOIN episodes e ON q.episode = e.id AND e.ignore = false WHERE e.title != '' AND q.queue = ".$this->config['queue_id'].";";
			$num_encode = current(pg_fetch_row(pg_query($sql_encode)));

			return $num_encode;
		}
		
		function sec2hms ($sec, $padHours = false) {
					
						// holds formatted string
						$hms = "";
						
						// there are 3600 seconds in an hour, so if we
						// divide total seconds by 3600 and throw away
						// the remainder, we've got the number of hours
						$hours = intval(intval($sec) / 3600); 
						
						// add to $hms, with a leading 0 if asked for
						$hms .= ($padHours) 
							? str_pad($hours, 2, "0", STR_PAD_LEFT). ':'
							: $hours. ':';
						
						// dividing the total seconds by 60 will give us
						// the number of minutes, but we're interested in 
						// minutes past the hour: to get that, we need to 
						// divide by 60 again and keep the remainder
						$minutes = intval(($sec / 60) % 60); 
						
						// then add to $hms (with a leading 0 if needed)
						$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ':';
						
						// seconds are simple - just divide the total
						// seconds by 60 and keep the remainder
						$seconds = intval($sec % 60); 
						
						// add to $hms, again with a leading 0 if needed
						$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
						
						// done!
						return $hms;
					
					}
		
		function getTrackChapters($track) {
		
			$track = intval($track);
		
			$sql = "SELECT chapter, start_time FROM track_chapters WHERE track = $track ORDER BY chapter;";
			$rs = pg_query($sql);
			
			$arr = array();
			
			if(pg_num_rows($rs) > 1) {
				
				$max_chapters = pg_num_rows($rs);
				#$str_pad_len = strlen($max_chapters);
				$str_pad_len = 2;
				
				while($row = pg_fetch_assoc($rs)) {
				
					$start_seconds = end(explode('.', $row['start_time']));
				
					$sec = intval($row['start_time']);
					
					// http://www.laughing-buddha.net/jon/php/sec2hms/
					$start_time = $this->sec2hms($sec, true);
				
					$chapter = str_pad($row['chapter'], $str_pad_len, 0, STR_PAD_LEFT);
					$chapter_num = "CHAPTER".$chapter."=$start_time.$start_seconds";
					$chapter_name = "CHAPTER".$chapter."NAME=Chapter $chapter";
					$arr[] = $chapter_num;
					$arr[] = $chapter_name;
				}
			}
			
			
			return($arr);
		
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
		
		function getValidTracks($arr_tracks = null, $min_len = 20, $max_len = 60) {
		
			if(!is_array($arr_tracks))
				return false;
			
			$min_len = intval($min_len);
			$max_len = intval($max_len);

			$add = $ignore = 0;

			
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
			

			$this->msg("$add tracks will be added to the database");
			$this->msg("$ignore tracks ignored because of length");
			
			return $arr_valid;
		}
	
		function lsdvd($dvd = '/dev/dvd') {
			#exec('lsdvd -q 2> /dev/null', $arr);
			#$xml = `lsdvd -c -Ox $dvd 2> /dev/null`;
			$xml = `lsdvd -Ox -v -a -s $dvd 2> /dev/null`;
			
			$xml = str_replace('Pan&Scan', 'Pan&amp;Scan', $xml);
			$xml = str_replace('P&S', 'P&amp;S', $xml);
			
			// Fix broken encoding on langcodes
			$xml = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $xml);
			
			#print_r($xml); die;

			$lsdvd = simplexml_load_string($xml) or die("Couldn't parse lsdvd XML output");

			// Get the "Disc Title:" string
			$this->disc_title = (string)$lsdvd->title;
			
			$this->msg("Disc Title: ".$this->disc_title, false, true);
			
			// Get the disc ID (libdvdread)
			if(!isset($this->disc_id))
				$this->disc_id = $this->getDiscId($dvd);
			
			// Build the array of tracks and their lengths
			foreach($lsdvd->track as $track) {
				// This is the original array created -- track lengths
				// only.  Now I dump more information into a second array
				// below (arr_lsdvd) for dvd2mkv, to store extra
				// information about audio tracks, subtitles, aspect ratio
				$this->arr_tracks[(int)$track->ix] = number_format((float)$track->length / 60, 2, '.', '');
				
				// Newer array
				// Only add ones where length != 0
				if($this->arr_tracks[(int)$track->ix] != '0.00') {
					$this->arr_lsdvd[(int)$track->ix]['length'] = $this->arr_tracks[(int)$track->ix];
					
					$this->arr_lsdvd[(int)$track->ix]['aspect'] = (string)$track->aspect;
					
					// Get the audio streams
					foreach($track->audio as $audio) {
						$this->arr_lsdvd[(int)$track->ix]['audio'][] = array('lang' => (string)$audio->langcode, 'channels' => (int)$audio->channels, 'format' => (string)$audio->format);
					}
					
					// Get the subtitle streams
					foreach($track->subp as $subp) {
						$this->arr_lsdvd[(int)$track->ix]['vobsub'][] = array('lang' => (string)$subp->langcode, 'language' => (string)$subp->language);
					}
				}
				
			}

			return true;
		}
		
		function mencoder($vob, $aid = null) {
			$flags = '';
			
			if(is_numeric($aid))
				$flags = " -aid $aid ";
			
			$basename = basename($vob, '.vob');
			$avi = "$basename.avi";
			$log = "$basename.log";
			
			$max = 1;
			
			// TODO: Get codec from preferences
			$ovc = 'xvid';
			$flags .= " -xvidencopts bitrate=2200{$xvidencopts} ";
			
			#if($fps > 0)
			#	$flags .= " -vf pullup,softskip -ofps 24000/1001 ";
			
			$pass1 = "mencoder $vob -o $avi -ovc $ovc -oac copy $flags  ";
			
			$this->msg("Encoding MPEG2 (VOB) to MPEG4 (AVI)");
			$this->executeCommand($pass1);

		}

		function midentify($file = 'movie.vob') {

			exec("midentify $file", $midentify);
			
			$a = 0;

			foreach($midentify as $key => $value) {
				$arr_split = preg_split('/\s*=\s*/', $value);
				$key = trim($arr_split[0]);
				$value = trim($arr_split[1]);
				$arr[$key] = $value;
				
				// Use lsdvd instead .. cleaner
				/*
				if($key == 'ID_AUDIO_ID') {
					$arr['audio_tracks'][$a] = array('id' => $value);
				} elseif($key == "ID_AID_".$arr['audio_tracks'][$a]['id']."_LANG") {
					$arr['audio_tracks'][$a]['lang'] = $value;
					$a++;
				}
				*/
			}

			return $arr;

		}
		
		function mkvmerge($avi = 'movie.avi', $txt = 'chapters.txt', $mkv = 'movie.mkv', $title = '', $atrack = 1, $aspect = '4/3', $idx = 'movie.idx') {
				
				if(is_null($atrack))
					$atrack = 1;
				// Moving order around to work with audio tracks
				$exec = "mkvmerge --aspect-ratio 0:$aspect -o \"$mkv\" -a $atrack \"$avi\" --title \"$title\" --default-language en";
				
				if(file_exists($txt))
					 $exec .= " --chapters \"$txt\"";
				 
				// Include VobSub subtitles if they exist
				if(file_exists($idx))
					 $exec .= " \"$idx\"";
					 
				#echo $exec; die;
				
				$verbose = false;
				if($this->args['v'] == 'mkv')
					$verbose = true;
					 
				$this->executeCommand($exec, true, $verbose);
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

		function ripTrack($track_id, $track_number, $vob = 'movie.vob', $multi = false, $starting_chapter = 1, $ending_chapter = null) {
			$this->msg("Ripping to $vob", false, true);
			
			// For some reason, with MPlayer, adding even -chapter 1
			// will fix buggy IDE + IRQ seek issues.
			
			$starting_chapter = abs(intval($starting_chapter));
			
			if(!$starting_chapter)
				$starting_chapter = 1;
				
			if(!is_null($ending_chapter)) {
				$ending_chapter = abs(intval($ending_chapter));
				if($ending_chapter && $ending_chapter >= $starting_chapter)
					$starting_chapter .= "-$ending_chapter";
			}
			
			if(($starting_chapter > 1) && $multi == 'f') {
				// If there is a starting chapter (and not multiple episodes per track),
				// we need to use mencoder to dump it, since dumpstream breaks mkvmerge's ability to read the # of audio
				// tracks, since you are starting midstream and lose the AC3 headers
				// We also output the file with a .vob extension, even though the
				// format is AVI.
				
				// Seems to work with latest mplayer + mkvmerge 2.1.0 just fine
				// $exec = "mencoder -dvd-device {$this->config['dvd_device']} dvd://$track_number -chapter $starting_chapter -ovc copy -oac copy -o $vob -alang en";
			} else {
				// $exec = "mplayer -dvd-device {$this->config['dvd_device']} dvd://$track_number -dumpstream -dumpfile $vob -chapter $starting_chapter";
			}
				
			$exec = "mplayer -dvd-device {$this->config['dvd_device']} dvd://$track_number -chapter $starting_chapter -dumpstream -dumpfile $vob";
				
			#echo $exec; die;
			
			$this->executeCommand($exec);
			
			// Rip chapters
			// Copied from addDisc() since the frontend can change the starting chapter
			$chapters = $this->getChapters($track_number, $this->config['dvd_device'], $starting_chapter);
			$this->archiveTrackChapters($track_id, $chapters);
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
					
				// Check export_dir
				if(substr($this->config['export_dir'], -1) != '/')
					$this->config['export_dir'] .= '/';
					
				return true;
			}
			else {
				trigger_error("Couldn't parse your configuration options", E_USER_WARNING);
				return false;
			}
		}
		
		function tcprobe($dvd = '/dev/dvd') {
			exec("tcprobe -i /dev/dvd 2> /dev/null", $tcprobe);
			
			// Get the aspect ratio
			$str = trim(current(preg_grep('/^\s*aspect/', $tcprobe)));
			#print_r($arr);
			$explode = preg_split('/\s+/', $str);
			
			$aspect_ratio = $explode[2];
			
			return($aspect_ratio);
		}

		function transcode($vob) {
			$flags = '';
			
			$basename = basename($vob, '.vob');
			$avi = "$basename.avi";
			$log = "$basename.log";
			
			if($this->debug) {
				$flags .= ' --print_status 10 ';
				$verbose = ':verbose=1';
			}
			
			// For 23.97, specify the framerate
			#if($fps == 1)
			#	$flags .= " -f 23.976,1 ";
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

// 			if($fps < 2) {
// 				$pass1 = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1,$log -x vob,vob -y xvid4,null $flags -o /dev/null -q $q";
// 				$pass2 = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 2,$log -x vob,vob -y xvid4 $flags -o $avi -q $q";
// 				$max = 2;
// 			}
// 			// Variable framerate
// 			// This isn't a magic bullet, but sure does work most of the time
// 			elseif($fps == 2) {
// 				#$pass1 = "mencoder $vob -aid 128 -ovc xvid -oac copy -o $avi -mc 0 -noskip -fps 24000/1001";
// 				$pass1 = "transcode -i $vob -x vob,vob -f 0,4 -M 2 -R 3 -w 2 --export_frc 1 -J ivtc -J decimate -B 3,9,16 --hard_fps -J 32detect=force_mode=5:chromathres=2:chromadi=9 -y xvid -o $avi -A -N 0x2000 -a 0 -b 128,0,0";
// 				$max = 1;
// 				if(isset($pass2))
// 					unset($pass2);
// 			}
			
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
