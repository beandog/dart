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

			if($dry_run)
				$bluray_copy->dry_run();

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

					require 'dart.x264.php';


					if($opt_encode_info) {

						$handbrake->input_filename($input_filename);
						if($opt_vob)
							$handbrake->input_filename(get_episode_filename($episode_id, 'vob', $arg_hardware));

						$handbrake->output_filename($filename);
						$handbrake_command = $handbrake->get_executable_string();
						if($opt_time)
							$handbrake_command = "command time -f '$filename - %E' -o '${filename}.time' $handbrake_command";

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

				$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
				$bluray_txt = substr($filename, 0, strlen($filename) - 3)."txt";
				$bluray_mkv = substr($filename, 0, strlen($filename) - 3)."mkv";
				$bluray_mp4 = substr($filename, 0, strlen($filename) - 3)."mp4";

				if(file_exists($bluray_mkv) && $opt_skip_existing)
					continue;

				if(file_exists($bluray_mp4) && $opt_skip_existing && $opt_bluray_encode)
					continue;

				if($opt_bluray_encode) {

					$hb_track = $tracks_model->get_bluray_hb_track();

					$container = 'mp4';

					require 'dart.x264.php';

					$handbrake->input_track($hb_track);
					$handbrake->set_disc_type('bluray');
					$handbrake->set_crop('0:0:0:0');

					if($debug)
						$handbrake->set_duration(300);

					$handbrake->output_filename($bluray_mp4);
					$handbrake_command = $handbrake->get_executable_string();

					echo "$handbrake_command\n";

					continue;

				}

				if(file_exists($bluray_txt) && $opt_skip_existing)
					$display_txt = false;

				if(file_exists($bluray_m2ts) && $opt_skip_existing)
					$display_m2ts = false;

				$bluray_copy->input_track($tracks_model->ix);
				$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_chapters->input_track($tracks_model->ix);
				$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_copy->output_filename($bluray_m2ts);
				$bluray_m2ts_command = $bluray_copy->get_executable_string();
				$bluray_chapters->output_filename($bluray_txt);
				$bluray_chapters_command = $bluray_chapters->get_executable_string();

				$mkvmerge = new Mkvmerge();
				$mkvmerge->add_video_track(0);

				$num_pgs_tracks = $tracks_model->get_num_subp_tracks();
				$num_active_pgs_tracks = $tracks_model->get_num_active_subp_tracks();
				$num_active_en_pgs_tracks = $tracks_model->get_num_active_subp_tracks('eng');

				$audio_ix = $tracks_model->get_best_quality_audio_ix('bluray');

				$mkvmerge->add_audio_track($audio_ix);

				if($num_pgs_tracks) {
					$pgs_ix = 0;
					$pgs_ix += count($tracks_model->get_audio_streams());
					$pgs_ix += $tracks_model->get_first_english_subp();
					$mkvmerge->add_subtitle_track($pgs_ix);
				}

				$mkvmerge->input_filename($bluray_m2ts);
				$mkvmerge->output_filename($bluray_mkv);
				$mkvmerge->add_chapters($bluray_txt);
				$mkvmerge_command = $mkvmerge->get_executable_string();

				if($display_txt)
					echo "$bluray_chapters_command\n";

				if($display_m2ts)
					echo "$bluray_m2ts_command\n";

				if($opt_skip_existing && !file_exists($bluray_mkv))
					echo "$mkvmerge_command\n";

			}

		}

	}
