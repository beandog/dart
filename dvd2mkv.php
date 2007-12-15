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
	
		$dvd->disc_id = $dvd->getDiscID();
		
		if(strlen($dvd->disc_id) != '32')
			die("Couldn't get disc ID!\n");
		
		$dvd->lsdvd();
		
		if(!$dvd->args['debug'])
			$dvd->msg("[DVD] Disc title: ".$dvd->disc_title);
		
		if($dvd->args['q']) {
			print_r($dvd->arr_lsdvd); die;
		}
		
		$disc_id = pg_escape_string($dvd->disc_id);
		$sql = "SELECT * FROM movies WHERE disc_id = '$disc_id';";
		$rs = sqlite_query($sql, $db);
		
		if(sqlite_num_rows($rs) && !$dvd->args['i']) {
			$arr = sqlite_fetch_array($rs);
			extract($arr);
			$title =& $arr['disc_title'];
			$dvd->msg("[DVD] Movie title: $title");
			
		} else {
		
			// Default to the single track if its the only non-zero lengthed one
			if(count($dvd->arr_lsdvd) == 1) {
				$track = key($dvd->arr_lsdvd);
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
			
			if(count($dvd->arr_lsdvd[$track]['audio']) > 1 || $dvd->args['i']) {
				$lang_track = array();
				
				// Should be customizable sometime
				$default_lang = 'en';
				$preferred_audio_formats = array('dts', 'ac3', 'stereo');
				
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
						
							#$max_channels = max(array($max_channels, $atmp['channels']));
						
							if($atmp['channels'] > $lang_track['channels']) {
								$lang_track = $atmp;
								$aid = 128 + $tmp;
							}
						}
					}
					
					// Possible code to pick the preferred audio track first
					// really needs to be cleaned up / done right
// 					foreach($arr_possible_tracks as $key => $tmp) {
// 						$atmp = $dvd->arr_lsdvd[$track]['audio'][$tmp];
// 						if(in_array($atmp['format'], $preferred_audio_formats) && $atmp['channels'] == $max_channels) {
// 						
// 							if($atmp['format'] == $preferred_audio_formats[0]) {
// 								$lang_track = $atmp;
//  								$aid = 128 + $tmp;
//  								$audio_track = true;
// 							}
// 						
// // 							if($atmp['channels'] > $lang_track['channels']) {
// // 								$lang_track = $atmp;
// // 								$aid = 128 + $tmp;
// // 							}
// 						}
// 						
// 						if(!$audio_track) {
// 							$lang_track = $atmp;
// 							$aid = 128 + $tmp;
// 						}
// 					}
					
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
				$title = ucwords(strtolower(str_replace('_', ' ', $dvd->disc_title)));
				$title = str_replace(' And ', ' and ', $title);
				$title = str_replace(' Of ', ' of ', $title);
				$title = str_replace(' The ', ' the ', $title);
				$title = $dvd->ask("Enter a movie title: [$title]", $title);
			} else {
				$title = $dvd->args['title'];
			}
		
			$str_title = pg_escape_string($title);
			
			if(!$dvd->args['nodb']) {
				$sql = "DELETE FROM movies WHERE disc_id = '$disc_id' AND track = $track;";
				sqlite_query($sql, $db);
				$sql = "INSERT INTO movies (disc_id, disc_title, track, aid) VALUES ('$disc_id', '$str_title', $track, $aid);";
				sqlite_query($sql, $db);
			}
			
		}
		
		// Make sure there is a subtitle track to rip
		$slang = false;
		
		if(array_key_exists('vobsub', $dvd->arr_lsdvd[$track])) {
			foreach($dvd->arr_lsdvd[$track]['vobsub'] as $key => $tmp) {
				if($slang == false && ($tmp['lang'] == 'en' || $tmp['language'] == 'English'))
					$slang = true;
			}
		}
		
		$aspect = $dvd->arr_lsdvd[$track]['aspect'];
		$length = $dvd->arr_lsdvd[$track]['length'];
		
		$audio_track = $aid - 128;
		$channels = $dvd->arr_lsdvd[$track]['audio'][$audio_track]['channels'];
		$format = $dvd->arr_lsdvd[$track]['audio'][$audio_track]['format'];
		if($format == 'ac3')
			$format = 'Dolby Digital';
		elseif($format == 'dts')
			$format = 'DTS';
		elseif($format == 'stereo')
			$format = 'Stereo';
		
		$subtitles = ( $slang ? 'Present' : 'None' );

		$title = $dvd->escapeTitle($title);
		$vob = "$title.vob";
		$sub = "$title.sub";
		$idx = "$title.idx";
		$txt = "$title.txt";
		$avi = "$title.avi";
		$mkv = "$title.mkv";

		$scandir = preg_grep('/(vob|sub|idx|txt|avi|mkv)$/', scandir('./'));

		if(!in_array($mkv, $scandir) || !in_array($vob, $scandir) || !in_array($avi, $scandir)) {
		
			// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
			// so we use scandir and in_array instead
			$dvd->msg("[Video] Track number: $track");
			$dvd->msg("[Video] Aspect ratio: $aspect");
			$dvd->msg("[Video] Length: $length");
			$dvd->msg("[Audio] Track: $aid");
			$dvd->msg("[Audio] Format: $format");
			$dvd->msg("[Audio] Channels: $channels");
			$dvd->msg("[DVD] Subtitles: $subtitles");
			
			if(!$dvd->args['encode']) {
			
				if(!in_array($vob, $scandir)) {
					$dvd->msg("[DVD] Ripping MPEG-2");
					$exec = "mplayer -dvd-device {$dvd->config['dvd_device']} dvd://$track -dumpstream -dumpfile $vob";
					$dvd->executeCommand($exec);
					#$dvd->msg("VOB dumped");
				}
				
				// Rip subtitles if:
				// 1) They exist and
				// 2) there is one for the specified language and
				// 3) They are not already ripped
				if(!$dvd->args['nosub'] && $slang && (!in_array($idx, $scandir) && !in_array($sub, $scandir))) {
					$dvd->msg("[DVD] Ripping subtitles");
					$exec = "mencoder -dvd-device {$dvd->config['dvd_device']} dvd://$track -ovc copy -nosound -vobsubout $title -o /dev/null -slang en";
					$dvd->executeCommand($exec);
					$dvd->msg("Subtitles dumped");
				}
			} elseif(!in_array($avi, $scandir)) {
				$exec = "mencoder -dvd-device {$dvd->config['dvd_device']} dvd://$track -ovc copy -oac copy $str_aid -slang en -vobsubout $title -o $avi";
				$dvd->executeCommand($exec);
				$dvd->msg("A/V encoded");
			}
			
		}
		
		// Get the chapters
		if(!file_exists($txt)) {
			$dvd->msg("[DVD] Ripping chapters");
			$chapters = $dvd->getChapters($track, $dvd->config['dvd_device']);
			$dvd->writeChapters($chapters, $txt);
		}
		
		if($dvd->config['eject']) {
			$dvd->msg("Attempting to eject disc.", true, true);
			$dvd->executeCommand('eject '.$dvd->config['dvd_device']);
		}
		
		if(file_exists($vob) && !file_exists($avi))
			$avi =& $vob;
		
		if(!file_exists($mkv)) {
		
			$dvd->msg("[MKV] Creating Matroska file");
			
			if($dvd->args['encode'])
				$atrack = 1;
			else
				$atrack = ($aid - 127);
			
			$dvd->mkvmerge($avi, $txt, $mkv, $title, $atrack, $aspect, $idx);
		}

		if(file_exists($mkv) && !$dvd->args['debug']) {
			file_exists($vob) && unlink($vob);
			file_exists($avi) && unlink($avi);
			file_exists($txt) && unlink($txt);
			file_exists($idx) && unlink($idx);
			file_exists($sub) && unlink($sub);
		}

		#print_r($dvd);
		#print_r($chapters);

	}
	
	
?>
