<?

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
	if($encode) {

		$queue_model = new Queue_Model;
	
		if($verbose)
			shell::msg("[Encode]");
	
		$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip, $max);
		
		do {
		
			foreach($queue_episodes as $episode_id) {
			
				clearstatcache();
			
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
				$episode_filename = get_episode_filename($episode_id);
				$episode_filename = $export_dir.$episode_filename;
				$display_name = $episodes_model->get_display_name();
				
				$tracks_model = new Tracks_Model($track_id);
				$track_number = $tracks_model->ix;
				
				$dvds_model = new Dvds_Model($tracks_model->dvd_id);
				
				$series_model = new Series_Model($episodes_model->get_series_id());
				$series_title = $series_model->title;
				$series_dir = $export_dir.formatTitle($series_title)."/";
				
				if($dvds_model->get_no_dvdnav() == 't')
					$dvdnav = false;
				else
					$dvdnav = true;
				
				if(!is_dir($series_dir))
					mkdir($series_dir);
				
				// Clean up any old tmp files
				$scandir = scandir($series_dir);
				
				if(count($arr = preg_grep('/(^(x264|vob)|xml$)/', $scandir))) {
					foreach($arr as $filename) {
						$filename = $series_dir.$filename;
						if(is_writable($filename))
							unlink($filename);
					}
				}
				
				$iso = $export_dir.$episodes_model->get_iso();
				$mkv = "$episode_filename.mkv";
				$x264 = "$episode_filename.x264";

				// Store XML metadata in temporary file
				$xml = tempnam(sys_get_temp_dir(), "xml");
				
				// Check to see if file exists, if not, encode it
				if(file_exists($iso) && !file_exists($mkv)) {
				
					echo("$display_name\n");
				
					$matroska = new Matroska($mkv);
					$matroska->setDebug($debug);
					$matroska->setTitle($episode_title);
					
					$handbrake = new Handbrake();

					if($svn)
						$handbrake->set_binary('handbrake-svn');
					
					if($debug)
						$handbrake->debug();
					if($verbose)
						$handbrake->verbose();
				
					if(!file_exists($x264)) {
					
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
						
						if($dumpvob) {
						
							$vob = "$episode_filename.vob";
							
							if(!file_exists($vob)) {
						
								$tmpfname = tempnam(dirname($episode_filename), "vob.$episode_id.");
								$dvdtrack = new DvdTrack($track_number, $iso, $dvdnav);
								$dvdtrack->getNumAudioTracks();
								$dvdtrack->setVerbose($verbose);
								$dvdtrack->setDebug($debug);
								$dvdtrack->setBasename($tmpfname);
								$dvdtrack->setStartingChapter($episode_starting_chapter);
								$dvdtrack->setEndingChapter($episode_ending_chapter);
								$dvdtrack->setAudioStreamID($default_audio_streamid);
								unlink($tmpfname);
								$dvdtrack->dumpStream();
								
								rename("$tmpfname.vob", $vob);
								
							}
							
							$src = $vob;
							
						} else {
							$src = $iso;
						}
						
						$dest = tempnam(dirname($episode_filename), "x264.$episode_id.");
						
						$handbrake->input_filename($src);
						if(!$dumpvob)
							$handbrake->input_track($track_number);
						$handbrake->output_filename($dest);
						
						$handbrake_base_preset = $series_model->get_handbrake_base_preset();
						$x264opts = $series_model->get_x264opts();
						$crf = $series_model->get_crf();
						
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
							if(!$added_audio && !$dumpvob)
								$handbrake->add_audio_stream("0x80");
							elseif(!$added_audio && $dumpvob)
								$handbrake->add_audio_track(1);
							
						}

						// Add the audio encoders to use
						// Add two tracks: copy the AC3/DTS, and also a secondary AAC channel
						$handbrake->add_audio_encoder('copy');
						$handbrake->add_audio_encoder('aac');

						// Check for a subtitle track
						$subp_ix = $tracks_model->get_first_english_subp();
								
						// If we have a VobSub one, add it
						// Otherwise, check for a CC stream, and add that
						if(!is_null($subp_ix))
							$handbrake->add_subtitle_track($subp_ix);
						elseif($handbrake->has_cc())
							$handbrake->add_subtitle_track($handbrake->get_cc_ix());
								
						// Set Chapters
						if(!$dumpvob)
							$handbrake->set_chapters($episode_starting_chapter, $episode_ending_chapter);
						
						$handbrake->autocrop();
						if($series_model->grayscale == 't')
							$handbrake->grayscale();
						$handbrake->dvdnav($dvdnav);
						$handbrake->set_preset($handbrake_base_preset);
						$handbrake->set_x264opts($x264opts);
						$handbrake->set_video_quality($crf);
								
						$handbrake->encode();
						
						// One line break to clear out the encoding line from handbrake
						if($verbose)
							echo("\n");
						
						// Handbrake can exit successfully and not actually encode anything,
						// by leaving an empty file.
						if(sprintf("%u", filesize($dest))) {
							rename($dest, $x264);
							
							if(!$debug && $dumpvob && file_exists($vob))
								unlink($vob);
							
						} else
							shell::msg("$episode_filename didn't encode properly: zero filesize");
						
					}
					
					/** Matroska Metadata */
					if(!file_exists($mkv)) {
					
						$production_studio = $series_model->production_studio;
						$production_year = $series_model->production_year;
					
						$matroska = new Matroska();
						
						if($episode_title)
							$matroska->setTitle($episode_title);
						if(!$reencode)
							$matroska->setAspectRatio($tracks_model->aspect);
						
						$matroska->addTag();
						$matroska->addTarget(70, "COLLECTION");
						$matroska->addSimpleTag("TITLE", $series_model->title);
						if($production_studio)
							$matroska->addSimpleTag("PRODUCTION_STUDIO", $production_studio);
						if($production_year)
							$matroska->addSimpleTag("DATE_RELEASE", $production_year);
						$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");
						
						/** Season **/
						if($episode_season) {
						
							$matroska->addTag();
							$matroska->addTarget(60, "SEASON");
							
							if($series_model->production_year) {
								$year = $production_year + $episode_season - 1;
								$matroska->addSimpleTag("DATE_RELEASE", $year);
							}
							
							$matroska->addSimpleTag("PART_NUMBER", $episode_season);
							
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
					
					if(file_exists($xml) && filesize($xml))
						$matroska->addGlobalTags($xml);
					
					$tmpfname = tempnam(dirname($episode_filename), "mkv.$episode_id.");
					$matroska->setFilename($tmpfname);
					$matroska->mux();
					rename($tmpfname, $mkv);
					
					$num_encoded++;
					
				} elseif (!file_exists($iso) && !file_exists($mkv)) {
					// At this point, it shouldn't be in the queue.
					$queue_model->remove_episode($episode_id);
				}
				
				// Delete old files
				if(file_exists($mkv)) {
				
					$queue_model->remove_episode($episode_id);
				
					if(!$debug) {
						if(file_exists($xml) && is_writable($xml))
							unlink($xml);
						if(file_exists($x264) && is_writable($x264))
							unlink($x264);
	
						/** Remove any old ISOs */
						$queue_isos = array();
						
						// Get the dvd_ids from episodes that are in the entire queue
						$queue_dvds = $queue_model->get_dvds(php_uname('n'));
	
						// For each of those DVDs, build an array of ISO filenames
						foreach($queue_dvds as $queue_dvd_id) {
							
							$dvds_model = new Dvds_Model($queue_dvd_id);
							
							$queue_isos[] = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";
							
						}
	
						if(!in_array($iso, $queue_isos) && file_exists($iso)) {
						
							
							// If we told it to rip from the disc, and the ISO
							// is a symlink to the device, then eject the disc
							// drive now that we're finished with it.
							if($handbrake && is_link($iso)) {
							
								$readlink = readlink($iso);
							
								if(substr($readlink, 0, 4) == "/dev") {
									$drive->open();
								}
							
							}
						}
					}
				}
				
				// Refresh the queue
				$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip);
				
				$count = count($queue_episodes);
				
			}
			
		} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));
		
// 		echo "\n";
		
	}
