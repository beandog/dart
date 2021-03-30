<?php

	// Display encode instructions about a disc
	if($opt_encode_info || $opt_copy_info || $opt_rip_info || $opt_pts_info) {

		$dvd_episodes = $dvds_model->get_episodes();

		// On QA run, only encode the first one
		if($opt_qa) {
			foreach($dvd_episodes as $episode_id) {
				$episodes_model = new Episodes_Model($episode_id);
				if($episodes_model->skip)
					continue;
				$dvd_episodes = array($episode_id);
				break;
			}
		}

		if($disc_type == 'bluray') {

			$bluray_copy = new BlurayCopy();
			$bluray_copy->set_binary('bluray_copy');

			if($debug)
				$bluray_copy->debug();

			if($verbose)
				$bluray_copy->verbose();

			$bluray_copy->input_filename($device);

			$bluray_chapters = new BlurayChapters();
			$bluray_chapters->input_filename($device);

		}

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);

			if($episodes_model->skip)
				continue;

			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$series_model = new Series_Model($episodes_model->get_series_id());
			$container = $series_model->get_preset_format();
			$video_encoder = $series_model->get_video_encoder();

			if($opt_copy_info)
				$container = 'mpg';

			if($opt_pts_info)
				$container = 'pts';

			if($disc_type == 'bluray')
				$container = 'mkv';

			$filename = get_episode_filename($episode_id, $container, $arg_hardware);

			$input_filename = realpath($device);

			/** DVDs **/
			if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd") {

				if($container == 'mkv' || $container == 'mp4') {

					require 'dart.vp8.php';
					require 'dart.vp9.php';
					require 'dart.x264.php';
					require 'dart.x265.php';

					if($opt_encode_info) {

						$handbrake->input_filename($input_filename);
						if($opt_vob)
							$handbrake->input_filename(get_episode_filename($episode_id, 'vob', $arg_hardware));

						$handbrake->output_filename($filename);
						$handbrake_command = $handbrake->get_executable_string();
						if($opt_time)
							$handbrake_command = "command time -f '$filename - %E' -o '${filename}.time' $handbrake_command";

						if($opt_ssim && !file_exists($ssim_filename))
							echo "$hb_ssim_command\n";

						echo "$handbrake_command\n";

						if($opt_qa) {

							$handbrake->set_duration(90);

							foreach(array('18', '20', '22', '24') as $qa_crf) {
								$handbrake->set_video_quality($qa_crf);
								$qa_filename = str_replace(".mkv", ".480p$fps.$video_encoder.q${qa_crf}.mkv", $filename);
								$handbrake->output_filename($qa_filename);
								$handbrake_command = $handbrake->get_executable_string();
								echo "$handbrake_command\n";
							}

							$qa_filename = str_replace(".mkv", ".DETEL.mkv", $filename);
							$handbrake->detelecine(true);
							$handbrake->decomb(false);
							$handbrake->comb_detect(false);
							$handbrake->output_filename($qa_filename);
							$handbrake_command = $handbrake->get_executable_string();
							echo "$handbrake_command\n";

							$qa_filename = str_replace(".mkv", ".DECOMB.mkv", $filename);
							$handbrake->detelecine(false);
							$handbrake->decomb(true);
							$handbrake->comb_detect(false);
							$handbrake->output_filename($qa_filename);
							$handbrake_command = $handbrake->get_executable_string();
							echo "$handbrake_command\n";

							$qa_filename = str_replace(".mkv", ".PERMISSIVE.mkv", $filename);
							$handbrake->detelecine(false);
							$handbrake->decomb(true);
							$handbrake->comb_detect(true);
							$handbrake->output_filename($qa_filename);
							$handbrake_command = $handbrake->get_executable_string();
							echo "$handbrake_command\n";

						}

					}

					if($opt_rip_info) {

						require 'dart.dvd_copy.php';
						$dvd_copy->input_filename($input_filename);
						$dvd_copy->output_filename("-");
						$dvd_copy_command = $dvd_copy->get_executable_string();

						require 'dart.ffmpeg.php';

						$dvd_rip_command = "$dvd_copy_command 2> /dev/null | $ffmpeg_command";
						echo "$dvd_rip_command\n";

					}

				} else if($container == 'mpg') {

					require 'dart.dvd_copy.php';
					$dvd_copy->input_filename($input_filename);
					$dvd_copy->output_filename($filename);
					if($opt_copy_stdout)
						$dvd_copy->output_filename("-");
					$dvd_copy_command = $dvd_copy->get_executable_string();
					echo "$dvd_copy_command\n";

				} else if($container == 'pts') {

					if($opt_skip_existing && ($episodes_model->progressive > 0 || $episodes_model->top_field > 0 || $episodes_model->bottom_field > 0))
						continue;

					require 'dart.dvd_copy.php';
					$dvd_copy->input_filename($input_filename);
					$dvd_copy->output_filename("-");
					$dvd_copy_command = $dvd_copy->get_executable_string();

					require 'dart.ffmpeg.php';

					$dvd_rip_command = "$dvd_copy_command 2> /dev/null | $ffmpeg_command";
					echo "$dvd_rip_command\n";

					if($opt_pts_import)
						echo "pts_import $filename\n";

				}

			}

			/** Blu-rays **/

			if($disc_type == "bluray") {

				$display_txt = true;
				$display_m2ts = true;
				$display_mkv = true;
				$bluray_encode = false;
				$bluray_encode_audio = false;

				$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
				$bluray_txt = substr($filename, 0, strlen($filename) - 3)."txt";
				$bluray_vc1 = substr($filename, 0, strlen($filename) - 3)."VC1.mkv";
				$bluray_ac3 = substr($filename, 0, strlen($filename) - 3)."eac3";
				$bluray_mkv = substr($filename, 0, strlen($filename) - 3)."mkv";

				$bluray_playlist = $tracks_model->ix;

				if(file_exists($bluray_mkv) && $opt_skip_existing)
					continue;

				if($tracks_model->codec == "vc1")
					$bluray_encode = true;

				if(file_exists($bluray_txt) && $opt_skip_existing)
					$display_txt = false;

				if(file_exists($bluray_m2ts) && $opt_skip_existing)
					$display_m2ts = false;

				if(file_exists($bluray_mkv) && $opt_skip_existing)
					$display_mkv = false;

				$bluray_copy->input_track($bluray_playlist);
				$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_chapters->input_track($bluray_playlist);
				$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				// Re-encode TrueHD into a second Dolby Digital Plus stream if necessary
				$audio_model = new Audio_Model();
				$audio_id = $audio_model->find_audio_id($tracks_model->id, 1);
				$audio_model->load($audio_id);
				$primary_format = $audio_model->format;
				if($primary_format == 'truhd')
					$bluray_encode_audio = true;

				$bluray_copy->output_filename($bluray_m2ts);
				$bluray_m2ts_command = $bluray_copy->get_executable_string();
				$bluray_chapters->output_filename($bluray_txt);
				$bluray_chapters_command = $bluray_chapters->get_executable_string();

				$bluray_ffmpeg_command = "ffmpeg -i '$bluray_m2ts' -map '0:a:0' -acodec 'eac3' -y '$bluray_ac3'";

				$mkvmerge = new Mkvmerge();
				$mkvmerge->add_video_track(0);

				// This was originally here to grab the TrueHD audio streams which
				// looked like they were the second stream instead of the first. That is
				// not always the case, and while it seems ideal to check all the
				// variables, practically speaking the best quality track is going to be
				// the first one matching the language.
				// $audio_ix = $tracks_model->get_best_quality_audio_ix('bluray');
				$audio_ix = $tracks_model->get_first_english_ix('bluray');
				$mkvmerge->add_audio_track($audio_ix);

				$num_pgs_tracks = $tracks_model->get_num_subp_tracks();
				$num_active_pgs_tracks = $tracks_model->get_num_active_subp_tracks();
				$num_active_en_pgs_tracks = $tracks_model->get_num_active_subp_tracks('eng');

				if($num_pgs_tracks) {
					$pgs_ix = 0;
					$pgs_ix += count($tracks_model->get_audio_streams());
					$pgs_ix += $tracks_model->get_first_english_subp();
					$mkvmerge->add_subtitle_track($pgs_ix);
				}

				$mkvmerge->add_input_filename($bluray_m2ts);
				$mkvmerge->output_filename($bluray_mkv);
				$mkvmerge->add_chapters($bluray_txt);

				if($bluray_encode)
					$mkvmerge->output_filename($bluray_vc1);

				if($bluray_encode_audio)
					$mkvmerge->add_input_filename($bluray_ac3);

				$mkvmerge_command = $mkvmerge->get_executable_string();

				if($display_txt && !$bluray_encode)
					echo "$bluray_chapters_command\n";

				if($display_m2ts && !$bluray_encode)
					echo "$bluray_m2ts_command\n";

				if($bluray_encode_audio && !file_exists($bluray_ac3))
					echo "$bluray_ffmpeg_command\n";

				if($display_mkv && !$bluray_encode)
					echo "$mkvmerge_command\n";

				if($bluray_encode) {

					require 'dart.x264.php';

					$handbrake->input_filename($device);

					// HandBrake orders titles by index
					$counter = 1;
					foreach($dvd->dvd_info['playlists'] as $key => $arr) {
						if($key == $bluray_playlist) {
							$handbrake->input_track($counter);
							break;
						}
						$counter++;
					}

					$handbrake->add_audio_track(1);

					if($bluray_encode_audio) {
						$handbrake->add_audio_track(1);
						$handbrake->add_audio_encoder('eac3');
					}

					// There are edge cases where the first audio track is Dolby
					// Digital, and the second audio track is Dolby TrueHD. In
					// these cases, the primary track is not ac3, but truhd. They
					// will show incorrectly in bluray_info, and mediainfo from the
					// m2ts files, but when remuxed or re-encoded they will properly
					// show the correct codec.
					// In these cases, also duplicate the truhd track as eac3.
					$audio_id = $audio_model->find_audio_id($tracks_model->id, 2);
					$audio_model->load($audio_id);
					$secondary_format = $audio_model->format;

					if($primary_format == 'ac3' && $secondary_format == 'truhd') {
						$handbrake->add_audio_track(1);
						$handbrake->add_audio_encoder('eac3');
					}

					if($debug)
						$handbrake->set_duration(300);

					$handbrake->output_filename($bluray_mkv);
					$handbrake_command = $handbrake->get_executable_string();

					echo "$handbrake_command\n";

				}

			}

		}

	}
