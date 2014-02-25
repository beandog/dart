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

		shell::msg("[Encode]");

		$hostname = php_uname('n');
		$queue_episodes = $queue_model->get_episodes($hostname, $skip, $max);

		if($skip)
			shell::msg("* Skipping $skip episodes");
		if($max)
			shell::msg("* Limiting encoding to $max episodes");

		do {

			$num_queued_episodes = count($queue_episodes);
			if($num_queued_episodes > 1)
				shell::msg("* $num_queued_episodes episodes queued up!");

			foreach($queue_episodes as $episode_id) {

				clearstatcache();

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

					$basename_iso = basename($iso);
					$basename_mkv = basename($mkv);

					shell::msg("Series:\t\t$series_title");
					shell::msg("Episode:\t$episode_title");
					shell::msg("Source:\t\t$basename_iso");
					shell::msg("Target:\t\t$basename_mkv");

					$matroska = new Matroska($mkv);
					$matroska->setDebug($debug);
					$matroska->setTitle($episode_title);

					$handbrake = new Handbrake;

					if($svn)
						$handbrake->set_binary('handbrake-svn');

					$handbrake->verbose($verbose);
					$handbrake->debug($debug);
					$handbrake->set_dry_run($dry_run);

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

						$episode_dirname = dirname($episode_filename);
						$dest = tempnam($episode_dirname, "x264.$episode_id.");

						// Handbrake
						$handbrake->input_filename($src);
						if(!$dumpvob)
							$handbrake->input_track($track_number);
						$handbrake->output_filename($dest);
						$handbrake->dvdnav($dvdnav);
						$handbrake->output_format('mkv');
						$handbrake->set_http_optimize(true);


						/** Video **/
						$video_encoder = 'x264';
						$video_quality = $series_model->get_crf();
						$deinterlace = false;
						$decomb = true;
						$detelecine = true;
						$grayscale = false;
						if($series_model->grayscale == 't')
							$grayscale = true;
						$autocrop = true;
						$h264_profile = 'high';
						$h264_level = '3.1';
						$x264_preset = 'medium';
						$x264_tune = 'film';
						$animation = false;
						if($series_model->animation == 't') {
							$animation = true;
							$x264_tune = 'animation';
						}

						$handbrake->set_video_encoder($video_encoder);
						$handbrake->set_video_quality($video_quality);
						$handbrake->deinterlace($deinterlace);
						$handbrake->decomb($decomb);
						$handbrake->detelecine($detelecine);
						$handbrake->grayscale($grayscale);
						$handbrake->autocrop($autocrop);
						$handbrake->set_h264_profile($h264_profile);
						$handbrake->set_h264_level($h264_level);
						$handbrake->set_x264_preset($x264_preset);
						$handbrake->set_x264_tune($x264_tune);


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

						// Add the audio encoders to use
						// Add two tracks: copy the AC3/DTS, and also a secondary AAC channel
						// FIXME!! this completely ignores preferences stored in the database.
						$handbrake->add_audio_encoder('copy');
						// Disabling adding AAC at all for now
						// $handbrake->add_audio_encoder('faac');
						// Setting a default audio fallback in case 'copy' doesn't work (0.9.9)
						$handbrake->set_audio_fallback('fdk_aac');

						// Check for a subtitle track
						$subp_ix = $tracks_model->get_first_english_subp();

						// If we have a VobSub one, add it
						// Otherwise, check for a CC stream, and add that
						if(!is_null($subp_ix)) {
							$handbrake->add_subtitle_track($subp_ix);
							shell::msg("Subtitles:\tVOBSUB");
						} elseif($handbrake->has_cc()) {
							$handbrake->add_subtitle_track($handbrake->get_cc_ix());
							shell::msg("Subtitles:\tClosed Captioning");
						} else {
							shell::msg("Subtitles:\tNone :(");
						}

						// Set Chapters
						if(!$dumpvob) {
							$handbrake->set_chapters($episode_starting_chapter, $episode_ending_chapter);
						}

						// Cartoons!
						if($animation) {
							shell::msg("Cartoons!! :D");
						}

						if($verbose > 1) {
							shell::msg("// Handbrake Video //");
							shell::msg("* Quality: $video_quality");
							shell::msg("* Deinterlace: ".intval($deinterlace));
							shell::msg("* Decomb: ".intval($decomb));
							shell::msg("* Detelecine: ".intval($detelecine));
							shell::msg("* Grayscale: ".intval($grayscale));
							shell::msg("* Animation: ".intval($animation));
							shell::msg("* Autocrop: ".intval($autocrop));
							shell::msg("* H.264 profile: $h264_profile");
							shell::msg("* H.264 level: $h264_level");
							shell::msg("* x264 preset: $x264_preset");
							shell::msg("* x264 tune: $x264_tune");
						}

						// Handbrake class will output encoding status
						$ret = $handbrake->encode();

						// One line break to clear out the encoding line from handbrake
						echo("\n");

						// Handbrake exited on a non-zero code
						if($ret) {
							shell::msg("! Handbrake died :(");
							// FIXME this probably is not right
							break;
						}

						// Handbrake can exit successfully and not actually encode anything,
						// by leaving an empty file.
						if(sprintf("%u", filesize($dest))) {
							rename($dest, $x264);

							if(!$debug && $dumpvob && file_exists($vob))
								unlink($vob);

						// FIXME
						// Add checks on Matroska file to see if it actually has data
						// Matroska will allow an empty container file
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
					chmod($mkv, 0644);

					$num_encoded++;

				} elseif (!file_exists($iso) && !file_exists($mkv)) {
					// At this point, it shouldn't be in the queue.
					shell::msg("! ISO not found ($iso), MKV not found ($mkv), force removing episode from queue");
					$queue_model->remove_episode($episode_id);
				}

				// Delete old files
				if(file_exists($mkv)) {

					$queue_model->remove_episode($episode_id);

					if($debug) {
						if(file_exists($xml))
							shell::msg("! Not removing $xml");
						if(file_exists($x264))
							shell::Msg("! Not removing $x264");
					}

					if(!$debug) {
						if(file_exists($xml) && is_writable($xml))
							unlink($xml);
						if(file_exists($x264) && is_writable($x264))
							unlink($x264);

						/** Remove any old ISOs */
						$queue_isos = array();

						// Get the dvd_ids from episodes that are in the entire queue
						$queue_dvds = $queue_model->get_dvds(php_uname('n'));

						/**
						// For each of those DVDs, build an array of ISO filenames
						foreach($queue_dvds as $queue_dvd_id) {

							$dvds_model = new Dvds_Model($queue_dvd_id);

							// FIXME isn't going to match new format for ISO filename
							$queue_isos[] = $export_dir.$dvds_model->id.".".$dvds_model->title.".iso";

						}

						if(!in_array($iso, $queue_isos) && file_exists($iso)) {


							// If we told it to rip from the disc, and the ISO
							// is a symlink to the device, then eject the disc
							// drive now that we're finished with it.
							if($handbrake && is_link($iso)) {

								$readlink = readlink($iso);

								// FIXME this probably won't work with new dvddrive class
								if(substr($readlink, 0, 4) == "/dev") {
									$drive->open();
								}

							}
						}
						**/
					}
				}

				// Refresh the queue
				$queue_episodes = $queue_model->get_episodes(php_uname('n'), $skip);

				$count = count($queue_episodes);

			}

		} while(count($queue_episodes) && $encode && (!$max || ($num_encoded < $max)));

	}
