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

		/*
		 * Archives a disc in the database
		 *
		 */
		function archiveDisc() {

			if(!isset($this->args['disc']) || intval($this->args['disc']) == 0)
				die("You need to pass a valid disc # with the --disc argument.\n");
			if(!isset($this->args['season']) || intval($this->args['season']) == 0)
				die("You need to pass a valid season # with the --season argument.\n");

			$arr_insert = array(
				'tv_show' => $this->tv_show,
				'season' => $this->args['season'],
				'disc' => $this->args['disc'],
				'disc_id' => $this->disc_id,
				'disc_title' => $this->disc_title
			);
			
			#print_r($arr_insert);
			#die;

			if(isset($this->args['chapters'])) {
				$arr_insert['chapters'] = 't';
				$arr_insert['chapters_track'] = intval($this->args['tracks']);
			}

			$sql_insert = pg_insert($this->db, 'discs', $arr_insert, PGSQL_DML_STRING);

			if($sql_insert === false) {
				print_r($arr_insert);
				trigger_error("Cannot build query", E_USER_ERROR);
			}
			elseif(is_string($sql_insert)) {
				decho("Query: $sql_insert");
				pg_query($sql_insert) or die(pg_last_error());
				return true;
			}
		}

		function archiveEpisodes() {

			// Calculate the starting episode # for this disc
			// Also, always update it each time, since the title orders may have changed
			$sql_start = "SELECT discs.disc, episodes.episode, episodes.title FROM episodes, discs WHERE discs.tv_show = {$this->tv_show} AND season = {$this->season} AND discs.disc < {$this->disc_number} AND episodes.disc = discs.id AND episodes.ignore = FALSE ORDER BY discs.disc, episodes.episode;";
			#decho($sql_start);
			$rs_start = pg_query($sql_start) or die(pg_last_error());
			$this->start = pg_num_rows($rs_start);

			// Now that we have the count of episodes before this disc, add one
			$this->start++;
			// ... and update the database
			$sql_update = "UPDATE discs SET start = {$this->start} WHERE id = {$this->disc};";
			decho($sql_update);
			pg_query($sql_update) or die(pg_last_error());

			$i = 1;

			#decho($this->arr_tracks);
			#die;

			if(isset($this->args['chapters'])) {

				$chapters = $this->getChapters(key($this->arr_tracks));
				
				$arr_chapters = explode("\n", $chapters);
				$this->chapters = (count($arr_chapters) / 2);

				#decho($arr_chapters);

				$i = 1;
				foreach($arr_chapters as $key => $value) {
					if($key % 2 === 0) {
						$value = preg_replace('/(CHAPTER\d+=|\.\d{3}$)/', '', $value);
						#$value = preg_replace('/\.\d{3}$/', '', $value);
						$value = str_replace(':', '.', $value);
						$arr[$i] = $this->correctLength($value);
						$i++;
					}
				}


				foreach($arr as $key => $value) {
					if($key == count($arr))
						$arr[$key] = current($this->arr_tracks) - $arr[$key];
					else
						$arr[$key] = $arr[($key + 1)] - $value;

					$arr_insert = array(
						'disc' => $this->disc,
						'episode' => $key,
						'len' => $arr[$key],
						'track' => $key
					);

					$this->num_episodes++;
					$sql_insert = pg_insert($this->db, 'episodes', $arr_insert, PGSQL_DML_STRING) or die("archiveEpisodes() couldn't build the query");
					pg_query($sql_insert) or die(pg_last_error());
				}
			}
			else {
				// Insert the episodes
				foreach($this->arr_tracks as $track => $len) {

					$chapters = $this->getChapters($track);

					$arr_insert = array(
						'disc' => $this->disc,
						'episode' => $i,
						'len' => $len,
						'chapters' => $chapters,
						'track' => $track
					);
					$i++;
					$this->num_episodes++;

					$sql_insert = pg_insert($this->db, 'episodes', $arr_insert, PGSQL_DML_STRING) or die("archiveEpisodes() couldn't build the query");
					pg_query($sql_insert) or die(pg_last_error());
				}
			}

			if($this->num_episodes > 0)
				echo("Archived {$this->num_episodes} episodes, be sure to set the titles in the frontend.\n");

			/*
			// Legacy code: update old database schema to include tracks
			if($args['update'] == 1) {
				$i = 1;
				foreach($dvd->arr_tracks as $key => $value) {
					$sql_update = "UPDATE episodes SET track = $key WHERE disc = {$dvd->disc} AND episode = $i;";
					decho($sql_update);
					pg_query($sql_update) or die(pg_last_error());
					$i++;
				}

			}
			*/
		}

		function archiveTitle() {

			$title = trim($this->args['title']);

			$arr_insert = array(
				'title' => $title
			);

			if(empty($this->args['pattern'])) {
				$arr_insert['pattern'] = $this->disc_title;
				echo "No --pattern argument, setting to disc title: '{$this->disc_title}'\n";
			}
			else
				$arr_insert['pattern'] = $this->args['pattern'];

			if(isset($this->args['min']) && is_numeric($this->args['min']))
				$arr_insert['min_len'] = $this->args['min'];
			if(isset($this->args['max']) && is_numeric($this->args['max']))
				$arr_insert['max_len'] = $this->args['max'];
			if(isset($this->args['cartoon']))
				$arr_insert['cartoon'] = 't';


			decho($arr_insert);

			$sql_title = pg_insert($this->db, 'tv_shows', $arr_insert, PGSQL_DML_STRING);

			decho($sql_title);

			pg_query($sql_title) or die(pg_last_error());
			echo("Inserting new title.  Be sure to set the titles before ripping!\n");

			$sql_title = "SELECT id FROM tv_shows ORDER BY id desc LIMIT 1;";
			$rs_title = pg_query($sql_title);
			$this->tv_show = current(pg_fetch_assoc($rs_title));
		}

		function createMatroska($avi, $mkv, $txt) {

			// Wrap the encoded episode into Matroska, add chapters
			if(!file_exists($mkv) && file_exists($avi)) {

				// Create the chapters file
				if(!file_exists($txt) && !is_null($this->arr_encode['chapters'])) {
					$this->writeChapters($txt);
					$flags = "--chapters $txt";
				}
				else
					$flags = '';

				echo "Wrapping AVI and chapters into Matroska ...\n";
				$exec = "mkvmerge -o \"$mkv\" $flags $avi";
				decho($exec);
				$this->executeCommand($exec);
			}
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
			$sql_queue = "SELECT episodes.episode, episodes.title, episodes.len, tv_shows.title AS tv_show_title FROM episodes, discs, tv_shows WHERE queue = {$this->config['queue']} AND episodes.disc = discs.id AND discs.tv_show = tv_shows.id AND ignore = FALSE ORDER BY tv_shows.title, episodes.disc, episodes.id;";
			#decho($sql_queue);
			$rs_queue = pg_query($sql_queue) or die(pg_last_error());
			while($arr_queue = pg_fetch_assoc($rs_queue)) {
				echo "$i. ".$arr_queue['tv_show_title'].": ".$arr_queue['title']." (".$arr_queue['len'].")\n";
			}
		}

		function encodeMovie() {

			$this->arr_encode = $this->getQueue();
			$this->export_dir = $this->getExportDir($this->arr_encode['tv_show_title'], $this->arr_encode['season']);

			$trunk = $this->export_dir."season_".$this->arr_encode['season']."_disc_".$this->arr_encode['disc_number']."_track_".$this->arr_encode['track'];

			if($this->arr_encode['one_chapter'] == 't')
				$trunk = $this->export_dir."season_".$this->arr_encode['season']."disc_".$this->arr_encode['disc_number']."_track_".$this->arr_encode['chapters_track']."_chapter_{$this->arr_encode['track']}";

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
			$sql_queue = "DELETE FROM episodes WHERE queue = {$this->config['queue']};";
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
				echo "Executing command: '$str'\n";
				exec($str);
			}
			elseif($this->args['debug'] == 2) {
				$str .= ';';
				echo "Executing command: '$str'\n";
				system($str);
			}
			elseif($this->args['debug'] == 3) {
				$str .= ';';
				echo "Executing command: '$str'\n";
				passthru($str);
			}
			else {
				$str .= ' 2> /dev/null;';
				exec($str);
			}
		}

		function getChapters($track) {

			$track = intval($track);
			if($track == 0)
				die("Passed an invalid track # to get chapters from!!\n");
			else {
				$exec = "dvdxchap {$this->config['device']} -t $track 2> /dev/null";
				decho($exec);
				exec($exec, $arr);
				#decho($arr);

				$chapters = implode("\n", $arr);

				return $chapters;
			}
		}

		function getDisc() {

			// Get min, max length for tv_show
			$sql_tv_show = "SELECT min_len, max_len, title FROM tv_shows WHERE tv_shows.id = {$this->tv_show};";
			$rs_tv_show = pg_query($sql_tv_show) or die(pg_last_error());
			$arr_tv_show = pg_fetch_assoc($rs_tv_show);

			if(!is_null($arr_tv_show['min_len'])) {
				$this->min_len = $arr_tv_show['min_len'];
			}
			if(!is_null($arr_tv_show['max_len'])) {
				$this->max_len = $arr_tv_show['max_len'];
			}


			// Get disc ID
			#$this->disc_id = $this->getDiscId($this->config['device']);
			//$sql_disc = "SELECT discs.*, tv_shows.title AS tv_show_title, discs.min_len, discs.max_len FROM discs, tv_shows WHERE tv_show = {$this->tv_show} AND disc_title = '{$this->disc_title}' AND md5 = '{$this->md5sum}' AND tv_shows.id = discs.tv_show;";

			$sql_where = '';
			if(isset($this->args['tv_show'])) {
				$sql_where .= " AND tv_show = {$this->args['tv_show']} ";
			}
			if(isset($this->args['season'])) {
				$sql_where .= " AND season = {$this->args['season']} ";
			}
			if(isset($this->args['disc'])) {
				$sql_where .= " AND disc = {$this->args['disc']} ";
			}

			$sql_disc = "SELECT discs.*, tv_shows.title AS tv_show_title, discs.min_len, discs.max_len FROM discs, tv_shows WHERE tv_show = {$this->tv_show} AND disc_title = '{$this->disc_title}' AND tv_shows.id = discs.tv_show $sql_where;";
			#$sql_disc = "SELECT discs.*, tv_shows.title AS tv_show_title, discs.min_len, discs.max_len FROM discs, tv_shows WHERE disc_id = '{$this->disc_id}' AND tv_shows.id = discs.tv_show;";
			decho($sql_disc);
			$rs_disc = pg_query($sql_disc) or die(pg_last_error());
			$num_rows = pg_num_rows($rs_disc);

			decho("Found $num_rows discs");

			if($num_rows == 1) {

				echo "Found your disc. :)\n";

				$this->arr_disc = $arr_disc = pg_fetch_assoc($rs_disc);

				if(!is_null($arr_disc['min_len']))
					$this->min_len = $arr_disc['min_len'];
				if(!is_null($arr_disc['max_len']))
					$this->max_len = $arr_disc['max_len'];
					
				if(is_null($arr_disc['disc_id'])) {
					decho("Updating Disc ID");
					$this->updateDiscId($arr_disc['id'], $this->disc_id);
				}
				else
					decho("Disc ID already logged. :)");

				#decho($this);

				$add = $ignore = 0;

				// If we are ripping only one track as chapters, then ignore the length
				if($arr_disc['chapters'] == 't') {

					foreach($this->arr_tracks as $key => $value) {
						if($key != $arr_disc['chapters_track'])
							unset($this->arr_tracks[$key]);
					}
				}
				else {
					// Trim the tracks that do not meet the min and max length criteria
					foreach($this->arr_tracks as $key => $value) {

						// On lsdvd 0.15, for XML output, track length is reported
						// in minutes
						#$value = ($value / 60);
						if($value > $this->max_len || $value < $this->min_len) {
							decho(" - Ignoring track $key, length $value");
							unset($this->arr_tracks[$key]);
							$ignore++;
						}
						else {
							decho(" + Adding track $key, length $value");
							$add++;
						}
					}
				}

				decho("$add tracks will be added to the database");
				decho("$ignore tracks ignored because of length");

				// Build on the DVD object a bit more
				$this->title = $arr_disc['tv_show_title'];
				$this->disc = $arr_disc['id'];
				$this->season = $arr_disc['season'];
				$this->disc_number = $arr_disc['disc'];

				// The min/max lengths in the disc table override the tv_show settings
				if(!is_null($arr_disc['min_len']))
					$this->min_len = $arr_disc['min_len'];
				if(!is_null($arr_disc['max_len']))
					$this->max_len = $arr_disc['max_len'];

				return true;
			}
			elseif($num_rows > 1) {
				echo "Found more than one disc, couldn't find the _unique_ one.\n";
				echo "Pass any combination of --tv_show <id>, --season <#> and --disc <#> to help narrow the search.\n";

				return false;
			}
			else
				return false;
		}
		
		function getDiscID($device = '/dev/dvd', $disc_id_binary = '/usr/bin/disc_id') {
			if(!empty($device)) {
				$disc_id = exec("$disc_id_binary $device");
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

		function getMatches() {

			// Find the tv_show ID
			$sql_pattern = "SELECT id AS tv_show, pattern, title FROM tv_shows WHERE pattern IS NOT NULL;";
			$rs_pattern = pg_query($sql_pattern);

			while($arr_pattern = pg_fetch_row($rs_pattern)) {
				if(preg_match("/\b{$arr_pattern[1]}\w*\b/i", $this->disc_title) == 1) {
					echo "Found a match!\n";
					$matches[] = $arr_pattern[0];
				}
			 }

			 return $matches;
			 
			 /*
			 echo "Disc ID: ".$this->disc_id."\n";
			 $sql_match = "SELECT id FROM discs WHERE disc_id = '{$this->disc_id}' LIMIT 1;";
			 $rs_match = pg_query($sql_match);
			 if(pg_num_rows($rs_match) == 0)
			 	return false;
			 else {
			 	$id = current(pg_fetch_row($rs_matches));
			 	return $id;
			 }
			 */
		}

		function getQueue() {

			$sql_queue = "SELECT episodes.id AS episode_id, episodes.episode, episodes.title, episodes.chapters, episodes.track, discs.id AS disc_id, discs.tv_show AS tv_show, discs.season, discs.disc AS disc_number, discs.start, discs.chapters AS one_chapter, discs.chapters_track, tv_shows.title AS tv_show_title, tv_shows.cartoon, tv_shows.fps FROM episodes, discs, tv_shows WHERE queue = {$this->config['queue']} AND episodes.disc = discs.id AND discs.tv_show = tv_shows.id AND ignore = FALSE ORDER BY tv_shows.title, episodes.disc, episodes.id LIMIT 1;";

			$rs_queue = pg_query($sql_queue) or die(pg_last_error());
			$arr_queue = pg_fetch_assoc($rs_queue);

			return $arr_queue;
		}

		function getQueueTotal($queue_id) {

			$queue_id = intval($queue_id);

			$sql_encode = "SELECT 1 FROM episodes WHERE queue = $queue_id AND ignore = FALSE;";
			$num_encode = pg_num_rows(pg_query($sql_encode));

			return $num_encode;
		}

		function lsdvd() {
			#exec('lsdvd -q 2> /dev/null', $arr);
			$xml = `lsdvd -Ox 2> /dev/null`;

			$lsdvd = simplexml_load_string($xml);

			// Get the "Disc Title:" string
			$this->disc_title = (string)$lsdvd->title;

			echo "Disc Title: ".$this->disc_title."\n";
			
			// Get the disc ID (libdvdread)
			$this->disc_id = $this->getDiscId($this->config['device']);
			
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

		function ripTrack($track = '', $vob = 'movie.vob', $flags = '') {
			$exec = "mplayer dvd://$track $flags -dumpstream -dumpfile $vob";
			$this->executeCommand($exec);
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

		function transcode($vob, $avi, $flags = '', $mkv, $config_dir = '', $fps = 0) {

			$flags = " $flags ";

			if($fps == 1)
				$flags .= "-f 24,1 ";

			// Two-pass encoding the VOB to AVI
			// By default, use XviD for excellent results
			if(!file_exists($avi) && !file_exists($mkv)) {
				$config_file = "{$config_dir}/xvid4.cfg";

				if(!empty($config_dir) && file_exists($config_file)) {
					decho("Reading configuration from '$config_file'\n");
					$flags .= "--config_dir $config_dir ";
				}

				echo "*** Encoding to AVI, pass 1 of 2 ...\n";
				$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1 -x vob -y xvid4,null $flags -o /dev/null";
				$this->executeCommand($exec);

				echo "*** Encoding to AVI, pass 2 of 2 ...\n";
				$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 2 -x vob -y xvid4 $flags -o $avi";
				$this->executeCommand($exec);
			}
		}
		
		/**
		 * Update Disc ID
		 * Should be a temporary function, as the IDs get updated
		 * for old records
		 */
		function updateDiscId($id, $disc_id) {
			$sql = "UPDATE discs SET disc_id = '$disc_id' WHERE id = $id;";
			decho("Query: $sql");
			pg_query($sql);
		}

		function writeChapters($txt = 'movie.txt') {
			$handle = fopen($txt, 'w') or die('error');
			fwrite($handle, $this->arr_encode['chapters']);
			fclose($handle);
		}
	}
?>
