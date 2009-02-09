#!/usr/bin/php
<?
	
	require_once 'class.dvd.php';
	require_once 'db/willy.movies.php';
	
	$dvd =& new DVD();
	
// 	$db = sqlite_open('/home/steve/.dvd2mkv/movies.db', 0666, $sqliteerror);
	
	/** Get the configuration options */
	$config = '/home/steve/.dvd2mkv/config';
	if(file_exists($config)) {
		$arr_config = parse_ini_file($config);
	} else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
		$arr_config = array();
	}
	
	$dvd->setConfig($argc, $argv, $arr_config);
	
	if($dvd->args['debug'])
		$dvd->debug = $dvd->verbose = $verbose = $debug = true;
	if($dvd->args['v'] || $dvd->args['verbose'])
		$dvd->verbose = $verbose = true;
	if($dvd->args['force'])
		$force = true;
	if($dvd->args['s'])
		 $dvd->args['sub'] = true;
		
	if($dvd->args['h'] || $dvd->args['help']) {
	
		$dvd->msg("Options:");
		$dvd->msg("  --title <title>\tMovie title");
		$dvd->msg("  --track <track>\tSpecify track number to rip");
		$dvd->msg("  -s, --sub\t\tRip subtitles");
		$dvd->msg("  --nodb\t\tDon't write to database");
		$dvd->msg("  -i\t\t\tInteractive mode, choose everything manually");
		$dvd->msg("  -v\t\t\tVerbose output");
		echo "\n";
		$dvd->msg("Encoding:");
		$dvd->msg("  --bitrate\t\tVideo bitrate");
		echo "\n";
		$dvd->msg("Debugging:");
		$dvd->msg("  --force\t\tOverwrite files");
		$dvd->msg("  --debug\t\tDebug output");
		$dvd->msg("  -q\t\t\tlsdvd array");
	
		die;
	}
	
	if($dvd->args['movie'] == 1 || $dvd->dvd2mkv === true) {
	
		$title =& $dvd->args['title'];
		
		if($dvd->args['bitrate']) {
			$tmp = abs(intval($dvd->args['bitrate']));
			if($tmp)
				$dvd->config['video_bitrate'] = $tmp;
		}
	
		$dvd->disc_id = $dvd->getDiscID();
		
		if(strlen($dvd->disc_id) != '32')
			die("Couldn't get disc ID!\n");
			
		$dvd->lsdvd();
		
		if($verbose)
			$dvd->msg("[DVD] Disc title: ".$dvd->disc_title);
		
		if($dvd->args['q']) {
			print_r($dvd->contents); die;
		}
		
		$disc_id = pg_escape_string($dvd->disc_id);
		$sql = "SELECT * FROM movies WHERE disc_id = '$disc_id';";
		$row = $db->queryRow($sql);
		
		if($row && !$dvd->args['i']) {
			extract($row);
			$title =& $row['disc_title'];
			$dvd->msg("[DVD] Movie title: $title");
		} else {
		
			// Default to the single track if its the only non-zero lengthed one
			if(count($dvd->contents) == 1) {
				$track = key($dvd->contents);
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
					foreach($dvd->contents as $key => $tmp) {
						$dvd->msg("Track $key Length: ".$tmp['length']." Aspect: ".$tmp['aspect']);
					}
					$track = $dvd->ask("Rip video track: [$track] ", $track);
				}
				
			}
			
			if($debug && !$dvd->args['track'])
				$dvd->msg("[DVD] Selected track $track");
			
			// Determine the audio track ID
			$aid = '';
			
			// Only check if there is more than one to pick through
			// otherwise, mencoder will get the default track fine
			// with no arguments
			
			if(count($dvd->contents[$track]['audio']) > 1 || $dvd->args['i']) {
				$lang_track = array();
				
				// Should be customizable sometime
				$default_lang = 'en';
				$preferred_audio_formats = array('dts', 'ac3', 'stereo');
				
				foreach($dvd->contents[$track]['audio'] as $key => $tmp) {
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
					$lang_track = $dvd->contents[$track]['audio'][current($arr_possible_tracks)];
						
					foreach($arr_possible_tracks as $key => $tmp) {
						$atmp = $dvd->contents[$track]['audio'][$tmp];
						if(in_array($atmp['format'], $preferred_audio_formats)) {
						
							#$max_channels = max(array($max_channels, $atmp['channels']));
						
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
							$dvd->msg("Audio Track: ".($tmp + 128)." Channels: ".$dvd->contents[$track]['audio'][$tmp]['channels']." Format: ".$dvd->contents[$track]['audio'][$tmp]['format']);
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
				if($debug)
					$dvd->msg("[DB] Saving movie to database");
				$sql = "DELETE FROM movies WHERE disc_id = '$disc_id' AND track = $track;";
				$db->query($sql);
				$sql = "INSERT INTO movies (disc_id, disc_title, track, aid) VALUES ('$disc_id', '$str_title', $track, $aid);";
				$db->query($sql);
			}
			
		}
		
		// Make sure there is a subtitle track to rip
		$slang = false;
		
		if(array_key_exists('vobsub', $dvd->contents[$track])) {
			foreach($dvd->contents[$track]['vobsub'] as $key => $tmp) {
				if($slang == false && ($tmp['lang'] == 'en' || $tmp['language'] == 'English'))
					$slang = true;
			}
		}
		
		$aspect = $dvd->contents[$track]['aspect'];
		$length = $dvd->contents[$track]['length'];
		
		$audio_track = $aid - 128;
		$channels = $dvd->contents[$track]['audio'][$audio_track]['channels'];
		$format = $dvd->contents[$track]['audio'][$audio_track]['format'];
		if($format == 'ac3')
			$format = 'Dolby Digital';
		elseif($format == 'dts')
			$format = 'DTS';
		elseif($format == 'stereo')
			$format = 'Stereo';
		
		$subtitles = ( $slang ? 'Present' : 'None' );

		$file = $dvd->escapeTitle($title);
		$vob = "$file.vob";
		$sub = "$file.sub";
		$idx = "$file.idx";
		$txt = "$file.txt";
		$avi = "$file.avi";
		$mkv = "$file.mkv";

		$scandir = preg_grep('/(vob|sub|idx|txt|avi|mkv)$/', scandir('./'));
		
		if($force)
			$scandir = array();

		if(!in_array($mkv, $scandir) || !in_array($vob, $scandir) || !in_array($avi, $scandir) || !in_array($txt, $scandir)) {
		
			// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
			// so we use scandir and in_array instead
			$dvd->msg("[Video] Track number: $track");
			$dvd->msg("[Video] Aspect ratio: $aspect");
			$dvd->msg("[Video] Length: $length");
			$dvd->msg("[Audio] Track: $aid");
			$dvd->msg("[Audio] Format: $format");
			$dvd->msg("[Audio] Channels: $channels");
			// No subtitles available or Subtitles
			if($dvd->args['sub'])
				$dvd->msg("[DVD] Subtitles: $subtitles");
			// Ignoring available subtitles
			elseif($slang && !$dvd->args['sub'])
				$dvd->msg("[DVD] Subtitles: Ignoring");
			
			if(!in_array($vob, $scandir)) {
				$dvd->msg("[DVD] Ripping MPEG-2");
				$exec = "mplayer -dvd-device ".$dvd->config['dvd_device']." dvd://$track -dumpstream -dumpfile $vob";
				$dvd->executeCommand($exec, false, $verbose);
				if($verbose)
					$dvd->msg("[DVD] VOB dumped");
			}
			
			// Get the chapters
			if(!file_exists($txt)) {
				$dvd->msg("[DVD] Ripping chapters");
				$chapters = $dvd->getChapters($track, $dvd->config['dvd_device']);
				$dvd->writeChapters($chapters, $txt);
			}
			
			// Rip subtitles if:
			// 1) They exist and
			// 2) there is one for the specified language and
			// 3) They are not already ripped
			if($dvd->args['sub']) {
				
				if ($slang && (!in_array($idx, $scandir) && !in_array($sub, $scandir))) {
					$dvd->msg("[DVD] Ripping subtitles");
					$exec = "mencoder -dvd-device {$dvd->config['dvd_device']} dvd://$track -ovc copy -nosound -vobsubout $file -o /dev/null -slang en";
					$dvd->executeCommand($exec, false, $verbose);
					if($verbose)
						$dvd->msg("[DVD] VobSub dumped");
				} elseif(in_array($idx, $scandir) && in_array($sub, $scandir))
					$dvd->msg("[DVD] VobSub exists");
			}
			
			if($dvd->args['encode']) {
			
				if(!in_array($avi, $scandir)) {
				
					// Now that we have the VOB on disk, we need
					// to probe it to find the audio track order,
					// and see if it is reversed or not.
					// ffmpeg and midentify will display them backwards
					// on some discs (fex: 130, 129, 128), so we need
					// to match the AID to the audio stream, get the
					// exact number to pass to ffmpeg to map to as well.
					// Note that mkvmerge flips the order to the correct
					// direction, so subtracting 127 from $aid is correct.
					// (fex: 131 - 127 = audio track 4).
					$dvd->probeAudio($vob, $track, $aid);
					
					// Now check to see if it should encode to 23.97 (default)
					// or 29.97 (rare).
					$dvd->probeVideo($vob);
					
					$dvd->msg('[AVI] Encoding VOB to MPEG4');
					$exec = "ffmpeg -y -i \"$vob\" -r ".$dvd->ffmpeg_framerate." -vcodec mpeg4 -ab 192k -b ".$dvd->config['video_bitrate']."k -acodec mp2 -map 0:0 -map ".$dvd->ffmpeg_atrack_map.":0.1 -ac 2 \"$avi\"";
// 					$dvd->executeCommand($exec, true, $verbose);
					$dvd->executeCommand($exec, true, true);
				}
			
			}
			
		}
		
		if($dvd->config['eject'] && !$debug) {
			$dvd->msg("Attempting to eject disc.", true, true);
			$exec = 'eject '.$dvd->config['dvd_device'];
			$dvd->executeCommand($exec, false, $verbose);
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

		if(file_exists($mkv) && !$debug) {
			if($verbose)
				$dvd->msg('Deleting temporary files');
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