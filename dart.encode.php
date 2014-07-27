<?php

	/**
	 * --encode
	 *
	 * Encode the episodes in the queue
	 *
	 */
	if($encode) {

		$queue_model = new Queue_Model;

		$num_encoded = 0;

		echo "[Encode]\n";

		$queue_episodes = $queue_model->get_episodes($hostname, $skip, $max);

		if($skip)
			echo "* Skipping $skip episodes\n";
		if($max)
			echo "* Limiting encoding to $max episodes\n";

		do {

			$num_queued_episodes = count($queue_episodes);
			if($num_queued_episodes > 1)
				echo "* $num_queued_episodes episodes queued up!\n";

			foreach($queue_episodes as $episode_id) {

				clearstatcache();

				if($num_queued_episodes > 1) {
					echo "\n";
					echo "[Encode ".($num_encoded + 1)."/$num_queued_episodes]\n";
				}

				$handbrake_success = false;
				$matroska_xml_success = false;
				$mkvmerge_success = false;

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
				$episode_basename = basename($episode_filename);
				$episode_filename = $export_dir.$episode_filename;
				$display_name = $episodes_model->get_display_name();

				$tracks_model = new Tracks_Model($track_id);
				$track_number = $tracks_model->ix;

				$dvds_model = new Dvds_Model($tracks_model->dvd_id);

				$series_id = $episodes_model->get_series_id();
				$series_model = new Series_Model($series_id);
				$collection_title = $series_model->get_collection_title();
				$series_title = $series_model->title;
				$series_dir = $export_dir.formatTitle($series_title)."/";
				$series_volume = $episodes_model->get_volume();

				if(!is_dir($series_dir))
					mkdir($series_dir);
				// Fix a rare case where the starting chapter is not null in the database,
				// but the ending chapter is.  In situations like this, set the ending
				// chapter to the last chapter.  Ideally, this should not happen in the
				// front end, but do an extra check here as well.
				// Handbrake will need an ending chapter passed to it if a starting chapter
				// is given, it will default to only encoding that one chapter otherwise.
				if(is_null($episode_ending_chapter) && !is_null($episode_starting_chapter)) {
					$episode_ending_chapter = $tracks_model->get_num_chapters();
				}

				$iso = $export_dir.$episodes_model->get_iso();
				$mkv = "$episode_filename.mkv";

				// Temporary files stored in queue directory
				$episode_queue_dir = $export_dir."queue/".formatTitle($series_title)."/".$episode_id;
				$queue_handbrake = $episode_queue_dir."/".$episode_basename.".handbrake.sh";
				$queue_handbrake_output = $episode_queue_dir."/".$episode_basename.".handbrake.out";
				$queue_mkvmerge = $episode_queue_dir."/".$episode_basename.".mkvmerge.sh";
				$queue_mkvmerge_output = $episode_queue_dir."/".$episode_basename.".mkvmerge.out";
				$queue_x264 = $episode_queue_dir."/".$episode_basename.".x264";
				$queue_xml = $episode_queue_dir."/".$episode_basename.".xml";
				$queue_mkv = $episode_queue_dir."/".$episode_basename.".mkv";
				$queue_iso_symlink = $episode_queue_dir."/".basename($iso);
				if(!is_dir($episode_queue_dir))
					mkdir($episode_queue_dir, 0755, true);
				symlink($iso, $queue_iso_symlink);

				$queue_status = $queue_model->get_episode_status($episode_id);

				// Check to see if file exists, if not, encode it
				if(file_exists($iso) && !file_exists($mkv) && $queue_status == 0) {

					$basename_iso = basename($iso);
					$basename_mkv = basename($mkv);

					echo "Collection:\t$collection_title\n";
					echo "Series:\t\t$series_title\n";
					echo "Episode:\t$episode_title\n";
					echo "Source:\t\t$basename_iso\n";
					echo "Target:\t\t$basename_mkv\n";

					$matroska = new Matroska($queue_mkv);
					$matroska->setDebug($debug);
					$matroska->setTitle($episode_title);

					$handbrake = new Handbrake;

					if($svn)
						$handbrake->set_binary('handbrake-svn');

					$handbrake->verbose($verbose);
					$handbrake->debug($debug);
					$handbrake->set_dry_run($dry_run);

					if(!file_exists($queue_x264)) {

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
								$dvdtrack = new DvdTrack($track_number, $iso);
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

						$episode_dirname = dirname($episode_filename);

						// Handbrake
						$handbrake->input_filename($src);
						if(!$dumpvob)
							$handbrake->input_track($track_number);
						$handbrake->output_filename($queue_x264);
						// $handbrake->dvdnav($dvdnav);
						$handbrake->add_chapters();
						$handbrake->output_format('mkv');


						/** Video **/
						$video_encoder = 'x264';
						$video_quality = $series_model->get_crf();
						$video_bitrate = $series_model->get_video_bitrate();
						$video_two_pass = $series_model->get_two_pass();
						$video_two_pass_turbo = $series_model->get_two_pass_turbo();
						$deinterlace = false;
						$decomb = true;
						$detelecine = true;
						$grayscale = false;
						if($series_model->grayscale == 't')
							$grayscale = true;
						$autocrop = true;
						$x264_opts = $series_model->get_x264opts();
						// Add support for dlna-usb-4 spec
						$h264_profile = 'high';
						$h264_level = '3.1';
						if($x264_opts)
							$x264_opts .= ":keyint=30";
						else
							$x264_opts = "keyint=30";
						// Added for dlna-usb-4
						$x264_opts .= ":vbv-bufsize=1024:vbv-maxrate=1024";
						$x264_preset = 'medium';
						$x264_tune = 'film';
						$animation = false;
						if($series_model->animation == 't') {
							$animation = true;
							$x264_tune = 'animation';
						}

						$handbrake->set_video_encoder($video_encoder);
						if($video_quality)
							$handbrake->set_video_quality($video_quality);
						if($video_bitrate)
							$handbrake->set_video_bitrate($video_bitrate);
						$handbrake->set_two_pass($video_two_pass);
						if($video_two_pass)
							$handbrake->set_two_pass_turbo($video_two_pass_turbo);
						$handbrake->deinterlace($deinterlace);
						$handbrake->decomb($decomb);
						$handbrake->detelecine($detelecine);
						$handbrake->grayscale($grayscale);
						$handbrake->autocrop($autocrop);
						$handbrake->set_h264_profile($h264_profile);
						$handbrake->set_h264_level($h264_level);
						if($x264_opts)
							$handbrake->set_x264opts($x264_opts);
						$handbrake->set_x264_preset($x264_preset);
						$handbrake->set_x264_tune($x264_tune);


						/** Audio **/
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
								if($handbrake->get_audio_index($arr['streamid']) && !$added_audio) {
									$handbrake->add_audio_stream($arr['streamid']);
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

						$audio_encoder = $series_model->get_audio_encoder();
						$audio_bitrate = $series_model->get_audio_bitrate();
						if($audio_encoder == 'aac') {
							$handbrake->add_audio_encoder('fdk_aac');
							$handbrake->set_audio_fallback('copy');
							if($audio_bitrate)
								$handbrake->set_audio_bitrate($audio_bitrate);
						} elseif($audio_encoder == 'copy') {
							$handbrake->add_audio_encoder('copy');
						} else {
							$handbrake->set_audio_fallback('copy');
						}

						// Check for a subtitle track
						$subp_ix = $tracks_model->get_first_english_subp();

						// If we have a VobSub one, add it
						// Otherwise, check for a CC stream, and add that
						if(!is_null($subp_ix)) {
							$handbrake->add_subtitle_track($subp_ix);
							echo "Subtitles:\tVOBSUB\n";
						} elseif($handbrake->has_closed_captioning()) {
							$handbrake->add_subtitle_track($handbrake->get_closed_captioning_ix());
							echo "Subtitles:\tClosed Captioning\n";
						} else {
							echo "Subtitles:\tNone :(\n";
						}

						// Set Chapters
						if(!$dumpvob) {
							$handbrake->set_chapters($episode_starting_chapter, $episode_ending_chapter);
						}

						// Cartoons!
						if($animation) {
							echo "Cartoons!! :D\n";
						}

						if($verbose > 1) {
							echo "// Handbrake Video //\n";
							if($video_quality)
								echo "* CRF: $video_quality\n";
							if($video_bitrate)
								echo "* Bitrate: ${video_bitrate}k\n";
							echo "* Deinterlace: ".d_yes_no(intval($deinterlace))."\n";
							echo "* Decomb: ".d_yes_no(intval($decomb))."\n";
							echo "* Detelecine: ".d_yes_no(intval($detelecine))."\n";
							echo "* Grayscale: ".d_yes_no(intval($grayscale))."\n";
							echo "* Animation: ".d_yes_no(intval($animation))."\n";
							echo "* Autocrop: ".d_yes_no(intval($autocrop))."\n";
							echo "* H.264 profile: $h264_profile\n";
							echo "* H.264 level: $h264_level\n";
							echo "* x264 preset: $x264_preset\n";
							echo "* x264 tune: $x264_tune\n";
						}

						$exit_code = null;
						if(!$dry_run) {

							$queue_model->set_episode_status($episode_id, 1);

							file_put_contents($queue_handbrake, $handbrake->get_executable_string()."\n");
							chmod($queue_handbrake, 0755);

							// Handbrake class will output encoding status
							$exit_code = $handbrake->encode();

							// One line break to clear out the encoding line from handbrake
							echo "\n";
						} elseif ($dry_run && $verbose) {

							echo "* Handbrake command: ".$handbrake->get_executable_string()."\n";

						}

						if($exit_code === 0 && !$dry_run)
							$handbrake_success = true;
						else
							$handbrake_success = false;

						// Handbrake failed -- either by non-zero exit code, or empty file
						if(!$dry_run && (!$handbrake_success || ($handbrake_success && !sprintf("%u", filesize($queue_x264))))) {

							$handbrake_success = false;

							echo "HandBrake failed for some reason.  See $episode_queue_dir for temporary files.\n";

							file_put_contents($queue_handbrake, $handbrake->get_executable_string()." $* \n");
							chmod($queue_handbrake, 0755);
							$queue_model->set_episode_status($episode_id, 2);

						} else {

							// Post-encode checks

							if(!$debug && $dumpvob && file_exists($vob))
								unlink($vob);

						}

					}

					/** Matroska Metadata */
					if(!file_exists($mkv) && !file_exists($queue_xml) && !$dry_run && $handbrake_success) {

						$queue_model->set_episode_status($episode_id, 3);

						$production_studio = $series_model->production_studio;
						$production_year = $series_model->production_year;

						$matroska = new Matroska();

						if($episode_title)
							$matroska->setTitle($episode_title);

						$matroska->addTag();
						$matroska->addTarget(70, "COLLECTION");
						$matroska->addSimpleTag("TITLE", $series_model->title);
						if($production_studio)
							$matroska->addSimpleTag("PRODUCTION_STUDIO", $production_studio);
						if($production_year)
							$matroska->addSimpleTag("DATE_RELEASE", $production_year);
						$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");

						// Tag MKV with latest spec I've created
						$matroska->addSimpleTag("ENCODING_SPEC", "dlna-usb-4");

						// Metadata specification DVD-MKV-1
						$matroska->addSimpleTag("METADATA_SPEC", "DVD-MKV-1");
						$matroska->addSimpleTag("DVD_COLLECTION", $collection_title);
						$matroska->addSimpleTag("DVD_SERIES_TITLE", $series_title);
						if($episode_season)
							$matroska->addSimpleTag("DVD_SERIES_SEASON", $episode_season);
						if($series_volume)
							$matroska->addSimpleTag("DVD_SERIES_VOLUME", $series_volume);
						$matroska->addSimpleTag("DVD_TRACK_NUMBER", $track_number);
						if($episode_number)
							$matroska->addSimpleTag("DVD_EPISODE_NUMBER", $episode_number);
						$matroska->addSimpleTag("DVD_EPISODE_TITLE", $episode_title);
						if($episode_part)
							$matroska->addSimpleTag("DVD_EPISODE_PART_NUMBER", $episode_part);
						$matroska->addSimpleTag("DVD_ID", $tracks_model->dvd_id);
						$matroska->addSimpleTag("DVD_SERIES_ID", $series_id);
						$matroska->addSimpleTag("DVD_TRACK_ID", $track_id);
						$matroska->addSimpleTag("DVD_EPISODE_ID", $episode_id);

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

						$str = $matroska->getXML();

						if($str) {
							file_put_contents($queue_xml, $str);
							$matroska_xml_success = true;
						} else {
							// Creating the XML file failed for some reason
							$queue_model->set_episode_status($episode_id, 4);
							$matroska_xml_success = false;
						}

					}

					// Only re-mux if it's not a dry run
					if(!$dry_run && $handbrake_success && $matroska_xml_success) {

						$queue_model->set_episode_status($episode_id, 4);

						$matroska->addFile($queue_x264);

						$matroska->addGlobalTags($queue_xml);

						$matroska->setFilename($queue_mkv);

						file_put_contents($queue_mkvmerge, $matroska->getCommandString());
						chmod($queue_mkvmerge, 0755);

						exec($matroska->getCommandString()." 2>&1", $mkvmerge_output_arr, $mkvmerge_exit_code);

						$queue_mkvmerge_output = implode("\n", $mkvmerge_output_arr);

						file_put_contents($queue_mkvmerge, $queue_mkvmerge_output);

						if($mkvmerge_exit_code == 0 || $mkvmerge_exit_code == 1) {

							$mkvmerge_success = true;
							rename($queue_mkv, $mkv);
							$num_encoded++;
							$queue_model->remove_episode($episode_id);

							// Cleanup
							if(!$debug) {

								unlink($queue_handbrake);
								unlink($queue_handbrake_output);
								unlink($queue_mkvmerge);
								unlink($queue_mkvmerge_output);
								unlink($queue_x264);
								unlink($queue_xml);
								unlink($queue_iso_symlink);

								rmdir($episode_queue_dir);

							}

						} else {

							$mkvmerge_success = false;
							$queue_model->set_episode_status($episode_id, 5);

						}

					}

				} elseif (!file_exists($iso) && !file_exists($mkv)) {

					// At this point, it shouldn't be in the queue.
					echo "! ISO not found ($iso), MKV not found ($mkv), force removing episode from queue\n";
					$queue_model->remove_episode($episode_id);

					$queue_model->set_episode_status($episode_id, 6);

				}

				// Delete old files
				if(file_exists($mkv) && $handbrake_success && $matroska_xml_success && $mkvmerge_success && !$dry_run) {

					$queue_model->remove_episode($episode_id);

					if($debug) {
						if(file_exists($queue_xml))
							echo "! Not removing $queue_xml\n";
						if(file_exists($queue_x264))
							echo "! Not removing $queue_x264\n";
					}

					if(!$debug) {
						if(file_exists($queue_xml))
							unlink($queue_xml);
						if(file_exists($queue_x264))
							unlink($queue_x264);

						/** Remove any old ISOs */
						$queue_isos = array();

						// Get the dvd_ids from episodes that are in the entire queue
						$queue_dvds = $queue_model->get_dvds(php_uname('n'));

					}
				}

				// On a dry run or failed handbrake encode, increment to the next file
				if($dry_run || !$handbrake_success) {
					$skip++;
					$num_encoded++;
				}

				// Refresh the queue
				$queue_episodes = $queue_model->get_episodes($hostname, $skip);

				$count = count($queue_episodes);

			}

		} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));

	}
