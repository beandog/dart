#!/usr/bin/php
<?
	
	require_once 'class.dvd.php';
	
	$dvd =& new DVD();
	
	$db = sqlite_open('/home/steve/.dvd2mkv/movies.db', 0666, $sqliteerror);
	
	/** Get the configuration options */
	$bendrc = '/home/steve/.bend';
	if(file_exists($bendrc)) {
		$arr_config = parse_ini_file($bendrc);
	} else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
		$arr_config = array();
	}
	
	$dvd->setConfig($argc, $argv, $arr_config);

	if($dvd->args['movie'] == 1 || $dvd->dvd2mkv === true) {
	
		$title =& $dvd->args['title'];
	
		$disc_id = $dvd->getDiscID();
		
		if(strlen($disc_id) != '32')
			die("Couldn't get disc ID!\n");
		
		$dvd->lsdvd();
		
		if(!$dvd->args['debug'])
			$dvd->msg("Disc title: ".$dvd->disc_title);
		
		if($dvd->args['q']) {
			print_r($dvd->arr_lsdvd); die;
		}
		
		$disc_id = pg_escape_string($disc_id);
		$sql = "SELECT * FROM movies WHERE disc_id = '$disc_id';";
		$rs = sqlite_query($sql, $db);
		
		if(sqlite_num_rows($rs)) {
			$arr = sqlite_fetch_array($rs);
			extract($arr);
			$title =& $arr['disc_title'];
			
		} else {
		
			// Default to the single track if its the only non-zero lengthed one
			if(count($dvd->arr_lsdvd) == 1) {
				$track = current($dvd->arr_lsdvd);
			// Check for user input
			} elseif($dvd->args['track']) {
				$track = abs(intval($dvd->args['track']));
			// Otherwise, figure it out ourselves
			} else {
				$longest_track_length = max($dvd->arr_tracks);
				
				foreach($dvd->arr_tracks as $key => $value) {
					if($value == $longest_track_length) {
						$track = $key;
						break;
					}
				}
				
				// Ask which video track to pick in interactive mode
				if($dvd->args['i']) {
					$dvd->msg("Choose your video track:");
					foreach($dvd->arr_lsdvd as $key => $tmp) {
						$dvd->msg("Track $key Length: ".$tmp['length']." Aspect: ".$tmp['aspect']);
					}
					$track = $dvd->ask("Rip video track: [$track] ", $track);
				}
				
			}
			
			// Determine the audio track ID
			$aid = '';
			
			// Only check if there is more than one to pick through
			// otherwise, mencoder will get the default track fine
			// with no arguments
			if(count($dvd->arr_lsdvd[$track]['audio']) > 1) {
				$lang_track = array();
				
				// Should be customizable sometime
				$default_lang = 'en';
				$preferred_audio_formats = array('ac3', 'stereo');
				
				foreach($dvd->arr_lsdvd[$track]['audio'] as $key => $tmp) {
					if($tmp['lang'] == $default_lang)
						$arr_possible_tracks[] = $key;
				}
				
				// Now see if there was only one track of our
				// preferred language
				if(count($arr_possible_tracks) == 1) {
					$audio_track = current($arr_possible_tracks);
					$aid = 128 + $audio_track;
				} else {
				
					// Find the track with highest # of channels
					$aid = 128;
					$lang_track = $dvd->arr_lsdvd[$track]['audio'][current($arr_possible_tracks)];
						
					foreach($arr_possible_tracks as $key => $tmp) {
						$atmp = $dvd->arr_lsdvd[$track]['audio'][$tmp];
						if(in_array($atmp['format'], $preferred_audio_formats)) {
							if($atmp['channels'] > $lang_track['channels']) {
								$lang_track = $atmp;
								$aid = 128 + $tmp;
							}
						}
					}
				
					// Override interactively
					if($dvd->args['i']) {
					
						$dvd->msg("Select the audio track:");
						foreach($arr_possible_tracks as $tmp) {
							$dvd->msg("Audio Track: ".($tmp + 128)." Channels: ".$dvd->arr_lsdvd[$track]['audio'][$tmp]['channels']." Format: ".$dvd->arr_lsdvd[$track]['audio'][$tmp]['format']);
						}
						$aid = $dvd->ask("Rip audio track: [$aid] ", $aid);
					}
				}
			}
			
			$aid = abs(intval($aid));
			if(!$aid >= 128)
				$aid = 128;
			
			$str_aid = '';
			if(!empty($aid))
				$str_aid = "-aid $aid";
			
			if(empty($title)) {
				$title = $dvd->ask("Enter a movie title:", 'Movie Title');
			} else {
				$title = $dvd->args['title'];
			}
		
			$title = pg_escape_string($title);
			$sql = "INSERT INTO movies (disc_id, disc_title, track, aid) VALUES ('$disc_id', '$title', $track, $aid);";
			sqlite_query($sql, $db);
		}
		
		$aspect = $dvd->arr_lsdvd[$track]['aspect'];

		$title = $dvd->escapeTitle($title);
		$vob = "$title.vob";
		$sub = "$title.sub";
		$idx = "$title.idx";
		$txt = "$title.txt";
		$avi = "$title.avi";
		$mkv = "$title.mkv";

		$scandir = preg_grep('/(avi|mkv|vob)$/', scandir('./'));

		// Mount/read DVD contents if we need to
		if(!file_exists($txt) || !in_array($mkv, $scandir)) {

			#$dvd->executeCommand('mount /mnt/dvd');

			if(!file_exists($txt)) {
				$dvd->arr_encode['chapters'] = $dvd->getChapters($track);
				
				#print_r($dvd);
				
				$chapters = $dvd->getChapters($track, $dvd->config['dvd_device']);
				$dvd->writeChapters($chapters, $txt);
			}

			// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
			// so we use scandir and in_array instead
			if(!in_array($vob, $scandir) && !in_array($avi, $scandir) && !in_array($mkv, $scandir)) {
			
				echo("Ripping video track $track and audio track $aid\n");
				echo("Movie aspect ratio: $aspect\n");
				#$exec = "mplayer -dvd-device {$dvd->config['dvd_device']} dvd://$track -dumpstream -dumpfile $vob";
				$exec = "mencoder -dvd-device {$dvd->config['dvd_device']} dvd://$track -ovc copy -oac copy $str_aid -slang en -vobsubout $title -o $avi";
				#echo $exec; die;
				$dvd->executeCommand($exec);
				#$exec = "mencoder dvd://{$dvd->longest_track} -ovc copy -oac copy -ofps 24000/1001 -o $vob";
				
			}/* elseif($dvd->args['encode'] == 1 && !in_array($vob, $scandir)) {
				$exec = "mencoder -dvd-device {$dvd->config['dvd_device']} dvd://$track -profile dvd2mkv -o $avi";
				$dvd->executeCommand($exec);
			}*/
		}
		
		if(file_exists($vob) && !file_exists($avi))
			$avi =& $vob;
		
		if(!file_exists($mkv)) {
			echo "Creating Matroska file\n";
			
			$dvd->mkvmerge($avi, $txt, $mkv, $title, 1, $aspect);
		}

		if(file_exists($mkv)) {
			file_exists($vob) && unlink($vob);
			file_exists($avi) && unlink($avi);
			file_exists($txt) && unlink($txt);
			file_exists($idx) && unlink($idx);
			file_exists($sub) && unlink($sub);
		}

		#print_r($dvd);
		#print_r($chapters);
		
		if($dvd->config['eject']) {
			$dvd->msg("Attempting to eject disc.", true, true);
			$dvd->executeCommand('eject '.$dvd->config['dvd_device']);
		}

	}
	
	
?>
