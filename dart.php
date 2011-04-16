#!/usr/bin/php
<?

	require_once 'Console/ProgressBar.php';

	// dart Functions
	require_once 'inc.dart.php';
	$queue_model = new Queue_Model;
	$dvd_episodes = array();
	
	$storage_dir = "/var/media";
	
	/** Get options **/
	$args = shell::parseArguments();
	
	$ini = array();
	$config = getenv('HOME').'/.dart/config';
	if(file_exists($config))
		$ini = parse_ini_file($config);
		
	if($args['h'] || $args['help']) {
		display_help();
		exit(0);
	}
	
	/** Verbosity **/
	
	/** --debug **/
	if($args['debug']) {
		$dart->debug = $dart->verbose = true;
		$debug =& $dart->debug;
		$verbose =& $dart->verbose;
	}
	
	/** --verbose **/
	if($args['v'] || $args['verbose'] || $ini['verbose'] || $debug) {
		$dart->verbose = true;
		$verbose =& $dart->verbose;
	}
	
	/** Command line arguments **/
	
	/** --device [name] **/
	if($args['device'])
		$device = $args['device'];
	elseif($ini['device'])
		$device = $ini['device'];
	else
		$device = "/dev/dvd";
	
	if(substr($device, -4, 4) == ".iso")
		$device_is_iso = true;
	else
		$device_is_iso = false;
	
	/** --devices **/
	if($ini['devices'])
		$devices = explode(",", $ini['devices']);
	else
		$devices = array($device);
	
	/** --encode **/
	if($args['encode'])
		$encode = true;
	
	/** --info **/
	if($args['i'] || $args['info'])
		$info = true;
	
	/** --info **/
	if($args['iso'])
		$dump_iso = true;
	
	/** --max [number] **/
	if($args['max'])
		$max = abs(intval($args['max']));
		
	/** --mount **/
	if($args['mount'] || $ini['mount'])
		$mount = true;
	else
		$mount = false;
	
	/** --movie **/
	if($args['movie'])
		$movie = true;
	
	/** --poll **/
	if($args['poll'])
		$poll = true;
	
	/** --queue **/
	if($args['q'] || $args['queue'])
		$queue = true;
	
	/** --rip **/
	if($args['rip'])
		$rip = true;
	
	/** --skip [number] **/
	if($args['skip'])
		$skip = abs(intval($args['skip']));
	else
		$skip = 0;
	
	/** --eject **/
	if((($ini['eject'] && !$debug && !$info) || $args['eject']) && !$args['noeject'])
		$eject = true;
	
	/** Subtitles **/
	// Closed Captioning
	if($args['cc'] || $ini['rip_cc'])
		$rip_cc = true;
	if($args['cc'] || $ini['mux_cc'])
		$mux_cc = true;
	if($args['nocc'])
		$rip_cc = $mux_cc = false;
	$min_cc_filesize = 25;
	
	// DVD Subs (VobSubs)
	if($args['vobsub'] || $ini['rip_vobsub'])
		$rip_vobsub = true;
	if($args['vobsub'] || $ini['mux_vobsub'])
		$mux_vobsub = true;
	if($args['novobsub'])
		$rip_vobsub = $mux_vobsub = false;

	start:
	
	/** Start everything **/
	$dvd = new DVD($device);
	
	// Determine whether we are reading the device
	if($rip || $info || $import)
		$access_device = true;
	
	// Determine whether we need physical access to a disc.
	if(!$device_is_iso && $access_device)
		$access_drive = true;
	else {
		$access_drive = false;
		$mount = false;
	}
	
	if($access_drive)
		$dvd->close_tray();
	else
		$eject = false;
	
	if($mount)
  		$dvd->mount();
  	
  	$dvds_model = new Dvds_Model;
  	
  	if($access_device) {
  		
  		$dvd->load_css();
		$uniq_id = $dvd->getID();
		
		$dvds_model_id = $dvds_model->find_id('uniq_id', $uniq_id);
		
		if($dvds_model_id)
			$disc_archived = true;
		
		$dvds_model->load($dvds_model_id);
		
		$dvd_episodes = $dvds_model->get_episodes();
		
		$num_episodes = count($dvd_episodes);
		
		// Update disc size
		/** Set the filesize of the DVD disc **/
		if(is_null($dvds_model->filesize)) {
		
			// FIXME Kind of pointless if only checks if mounted..
			// FIXME reads udev amount
 			if($mount)
 				$dvds_model->filesize = $dvd->getSize();
			
			if($device_is_iso && file_exists($device)) {
				$filesize = sprintf("%u", filesize($device)) / 1024;
				$dvds_model->filesize = $filesize;
				unset($filesize);
			}
		}
		
	}
	
	// Display info about disc
	if($info)
		if($disc_archived)
			display_info($uniq_id);
		else
			shell::msg("Disc is not archived");
	
	/**
	 * --iso
	 *
	 * Copy a disc's content to the harddrive
	 */
	// Get the target filename
	$iso = $dart->export.$dvds_model->id.".".$dvds_model->title.".iso";
	
	// Check if needed
	if(($rip || $dump_iso) && !file_exists($iso) && !$device_is_iso) {
			
			$tmpfname = tempnam($dart->export, "tmp");
		
			$dvd->dump_iso($tmpfname);
			rename($tmpfname, $iso);
			unset($tmpfname);
	
	}
	
	/**
	 * --queue
	 *
	 * Get episode list in the queue
	 *
	 */
	
	$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);
	
	if($queue) {
	
		foreach($queue_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			$display_name = $episodes_model->get_display_name();
			echo("$display_name\n");
		}
	
	}
	
	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */
	 
	if($rip && $disc_archived && !$num_episodes) {
	
		shell::msg("The disc is archived, but there are no episodes to rip.");
		shell::msg("Check the frontend to see if titles need to be added.");
		$eject = false;
	
	}
	
	if($rip && $disc_archived && $num_episodes) {
	
		$dump_iso = true;
	
		/** Create directory to dump files to */
 		if(!is_dir($dart->export))
 			@mkdir($dart->export, 0755);
 		
 		// Don't re-rip any episodes already in queue
 		// MAYBE LATER?  FIXME
 		// I want to re-rip them to make sure I get all the metadata ...
 		// Maybe make that one a separate action
 		$rip_episodes = array_diff($dvd_episodes, $queue_episodes);
 		
		// Extract episodes
		if(count($dvd_episodes)) {
		
			/** Testing DVD episodes #s for a disc **/
// 			foreach($dvd_episodes as $episode_id) {
// 			
// 				$episodes_model = new Episodes_Model($episode_id);
// 				$series_id = $episodes_model->get_series_id();
// 				$series_model = new Series_Model($series_id);
// 				
// 				$episode_number = $episodes_model->get_number();
// 				
// 				echo "Episode ID: ";
// 				echo $episodes_model->id."\n";
// 				echo $episodes_model->title."\n";
// 				
// 				var_dump($episode_number);
// 			
// 			}

			$bar = new Console_ProgressBar('[%bar%] %percent%'." ($num_episodes episodes to rip)", ':', ' :D ', 80, $num_episodes);
			$i = 0;
			
			foreach($dvd_episodes as $episode_id) {
			
				// New instance of a DB episode
				$episodes_model = new Episodes_Model($episode_id);
				$episode_season = $episodes_model->season;
				$episode_title = $episodes_model->title;
				$episode_part = $episodes_model->part;
				$episode_filename = $dart->get_episode_filename($episode_id);
				
				$tracks_model = new Tracks_Model($episodes_model->track_id);
 				$track_number = $tracks_model->ix;
 				
 				$dvd_track = new DVDTrack($track_number, $iso);
				
				$dvd_track->setDebug($debug);
				$dvd_track->setBasename($episode_filename);
				$dvd_track->setStartingChapter($episodes_model->starting_chapter);
				$dvd_track->setEndingChapter($episodes_model->ending_chapter);
				
				// Get the series ID
				$series_id = $episodes_model->get_series_id();
				
				// New instance of a DB series
				$series_model = new Series_Model($series_id);
				$series_title = $series_model->title;
				
				// Get and create our export directory
				$series_dir = $dart->export.$dart->formatTitle($series_title)."/";
 				if(!is_dir($series_dir))
 					mkdir($series_dir, 0755) or die("Can't create export directory $series_dir");
 				
				// Get the episode #
				if($series_model->indexed == 't') {
					
					$indexed_series = true;
					$episode_number = $episodes_model->get_number();
					
					if($episode_season)
						$episode_prefix = "${episode_season}x${episode_number}._";
					
					
				} else
					$indexed_series= false;
				
				if($episode_part > 1)
					$episode_suffix = ", Part $episode_part";
				
				$xml = "$episode_filename.xml";
				$mkv = "$episode_filename.mkv";
				$txt = "$episode_filename.txt";
				
				// Check to see if file exists, if not, rip it 				
				if(!file_exists($mkv))
					$rip_episode = true;
				
				/** LEGACY: Handbrake should copy chapters over into MKV it creates */
				// Chapters
//  				if(!file_exists($txt) && $episode->getNumChapters() > 1) {
// 					shell::msg("[DVD] Chapters");
// 					$dvd_track->dumpChapters();
// 					$num_ripped['chapters']++;
//  				}
				
				// Add episode to queue, and override previous instance
				if(file_exists($iso))
					$queue_model->add_episode($episode_id, php_uname('n'));
				
				$i++;
				
				$bar->update($i);
				
				if(($i + 1) == $max)
					break(2);
				
			}
		
		}
		
		if($eject) {
			$dvd->eject();
			$ejected = true;
		}
		
		echo "\n";
	
	}
	
	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
	if($encode) {
	
		$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);
		
		do {
		
			foreach($queue_episodes as $episode_id) {
			
				// Legacy
				$reencode = true;
				
				$episodes_model = new Episodes_Model($episode_id);
				$episode_title = $episodes_model->title;
				$track_id = $episodes_model->track_id;
				$episode_number = $episodes_model->get_number();
				$episode_index = $episodes_model->ix;
				$episode_starting_chapter = $episodes_model->starting_chapter;
				$episode_ending_chapter = $episodes_model->ending_chapter;
				$episode_part = $episodes_model->part;
				$episode_season = $episodes_model->get_season();
				$episode_filename = $dart->get_episode_filename($episode_id);
				$display_name = $episodes_model->get_display_name();
				
				echo("$display_name\n");
				
				$tracks_model = new Tracks_Model($track_id);
				$track_number = $tracks_model->ix;
				
				$dvds_model = new Dvds_Model($tracks_model->dvd_id);
				
				$series_model = new Series_Model($episodes_model->get_series_id());
				$series_title = $series_model->title;
				$series_dir = $dart->export.$dart->formatTitle($series_title)."/";
				
				$iso = $dart->export.$dvds_model->id.".".$dvds_model->title.".iso";
				$xml = "$episode_filename.xml";
				$mkv = "$episode_filename.mkv";
				$txt = "$episode_filename.txt";
				$x264 = "$episode_filename.x264";
				
				// FIXME put somewhere else
// 				if($queue) {
// 					if((!file_exists($src)) || file_exists($mkv)) {
// 						shell::msg("[Queue] Removing $series_title: $episode_title");
// 						$dart->removeQueue($episode_id);
// 					}
// 				}
				
				// Check to see if file exists, if not, encode it
				if(file_exists($iso) && !file_exists($mkv)) {
				
					$matroska = new Matroska($mkv);
					$matroska->setDebug($debug);
					$matroska->setTitle($episode_title);
					
					$handbrake = new Handbrake();
					
					if($debug)
						$handbrake->debug();
					elseif($verbose)
						$handbrake->verbose();
				
					if(!file_exists($x264)) {
					
						$tmpfname = tempnam(dirname($episode_filename), "x264.$episode_id.");
						
						$handbrake->input_filename($iso, $track_number);
						$handbrake->output_filename($tmpfname);
						
						$handbrake_base_preset = $series_model->get_handbrake_base_preset();
						$x264opts = $series_model->get_x264opts();
						$crf = $series_model->get_crf();
						
						// Find the audio track to use
						$best_quality_audio_streamid = $tracks_model->get_best_quality_audio_streamid();
						$first_english_streamid = $tracks_model->get_first_english_streamid();
						
						$audio_preference = $dvds_model->get_audio_preference();
						
						if($audio_preference === "0")
							$default_audio_streamid = $best_quality_audio_streamid;
						elseif($audio_preference === "1")
							$default_audio_streamid = $first_english_streamid;
						elseif($audio_preference === "2")
							$default_audio_streamid = $best_quality_audio_streamid;
							
						// Some DVDs may report more audio streams than
						// Handbrake does.  If that's the case, check
						// each one that lsdvd reports, to see if Handbrake
						// agrees, and add the first one that they both
						// have found.
						//
						// By default, use the one we think is right.
						if($handbrake->get_audio_index($default_audio_streamid))
							$handbrake->add_audio_stream($default_audio_streamid);
						else {
							
							$added_audio = false;
							
							$audio_streams = $tracks_model->get_audio_streams();
							
							foreach($audio_streams as $arr) {
								if($handbrake->get_audio_index($arr['stream_id']) && !$added_audio) {
									$handbrake->add_audio_stream($arr['stream_id']);
									$added_audio = true;
								}
							}
							
							// If one hasn't been added by now, just use
							// the default one.
							if(!$added_audio)
								$handbrake->add_audio_stream("0x80");
							
						}

						// FIXME Add preset support (big wishlist item)
						// For now, assume everything is 'Normal' profile.
						$handbrake->set_preset('Normal');
								
						// Check for a subtitle track
						$subp_ix = $tracks_model->get_first_english_subp();
								
						// If we have a VobSub one, add it
						// Otherwise, check for a CC stream, and add that
						if(!is_null($subp_ix))
							$handbrake->add_subtitle_track($subp_ix);
						elseif($handbrake->has_cc())
							$handbrake->add_subtitle_track($handbrake->get_cc_ix());
								
						// Set Chapters
						$handbrake->set_chapters($episode_starting_chapter, $episode_ending_chapter);
						
						$handbrake->autocrop();
						if($series_model->grayscale == 't')
							$handbrake->grayscale();
						$handbrake->set_preset($handbrake_base_preset);
						$handbrake->set_x264opts($x264opts);
						$handbrake->set_video_quality($crf);
								
						if($debug)
							shell::msg("Executing: ".$handbrake->get_executable_string());
						
						$handbrake->encode();
						
						rename($tmpfname, $x264);
						
					}
					
					/** Matroska Metadata */
					if(!file_exists($xml) && !file_exists($mkv)) {
					
						$production_studio = $series_model->production_studio;
						$production_year = $series_model->production_year;
					
						$matroska = new Matroska();
						
						$matroska->setFilename($mkv);
						if($episode_title)
							$matroska->setTitle("TITLE", $episode_title);
						if(!$reencode)
							$matroska->setAspectRatio($tracks_model->aspect);
						
						$matroska->addTag();
						$matroska->addTarget(70, "COLLECTION");
						$matroska->addSimpleTag("TITLE", $series_model->title_long);
						$matroska->addSimpleTag("SORT_WITH", $series_model->title);
						if($production_studio)
							$matroska->addSimpleTag("PRODUCTION_STUDIO", $production_studio);
						if($production_year)
							$matroska->addSimpleTag("DATE_RELEASE", $production_year);
						$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");
						
						/** Season **/
						if($episodes_model->season) {
						
							$season = $episodes_model->season;
						
							$matroska->addTag();
							$matroska->addTarget(60, "SEASON");
							
							if($series_model->production_year) {
								$year = $production_year + $season - 1;
								$matroska->addSimpleTag("DATE_RELEASE", $year);
							}
							
							$matroska->addSimpleTag("PART_NUMBER", $season);
							
						}
						
						/** Episode **/
						if(!$movie) {
							$matroska->addTag();
							$matroska->addTarget(50, "EPISODE");
							if($episode_title)
								$matroska->addSimpleTag("TITLE", $episode_title);
							if($episode_number)
								$matroska->addSimpleTag("PART_NUMBER", $episode_number);
							$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
							$matroska->addSimpleTag("PLAY_COUNTER", 0);
							
							if($episodes_model->part > 1) {
								$matroska->addTag();
								$matroska->addTarget(40, "PART");
								$matroska->addSimpleTag("PART_NUMBER", $episodes_model->part);
							}
						}
						
						$str = $matroska->getXML();
						
						if($str)
							file_put_contents($xml, $str);
						
					}
					
					if(file_exists($x264))
						$matroska->addFile($x264);
					
					
					
						
					if(file_exists($xml))
						$matroska->addGlobalTags($xml);
					
					$matroska->mux();
					
					$num_encoded++;
					
				} elseif (!file_exists($iso) && !file_exists($mkv)) {
					// At this point, it shouldn't be in the queue.
					$queue_model->remove_episode($episode_id);
				}
				
				// Delete old files
				if(file_exists($mkv) && !$debug) {
					if(file_exists($xml))
						unlink($xml);
					if(file_exists($txt))
						unlink($txt);
					if(file_exists($x264))
						unlink($x264);
				}
				
				if(file_exists($mkv)) {
					
					$queue_model->remove_episode($episode_id);

					// Remove any old ISOs
					$queue_dvds = $queue_model->get_dvds(php_uname('n'));

					$queue_isos = array();

					foreach($queue_dvds as $queue_dvd_id) {
						
						$dvds_model = new Dvds_Model($queue_dvd_id);
						
						$queue_isos[] = $dart->export.$dvds_model->id.".".$dvds_model->title.".iso";
						
					}

					if(!in_array($iso, $queue_isos) && !$debug && file_exists($iso)) {
						unlink($iso);
					}
				}
				
				// Refresh the queue
				$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip);
				
				$count = count($queue_episodes);
				
			}
			
		} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));
		
// 		echo "\n";
		
	}
	
	if($eject)
		$dvd->eject();
	
	// If polling for a new disc, check to see if one is in the
	// drive.  If there is, start over.
	if($poll && $rip) {

		$notice = false;
		
		while(true) {

			if($dvd->cddetect()) {
				shell::msg("Found a disc, starting over!");
				goto start;
			} else {
				if(!$notice)
					shell::msg("Waiting for a new disc on $device");
				$notice = true;
				sleep(60);
			}

		}

	}
	
		
?>
