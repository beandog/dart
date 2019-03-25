<?php

	// Display encode instructions about a disc
	if($opt_encode_info || $opt_copy_info) {

		$dvd_episodes = $dvds_model->get_episodes();

		// On QA run, only encode the first one
		if($opt_qa)
			$dvd_episodes = array(current($dvd_episodes));

		// Display the episode names
		foreach($dvd_episodes as $episode_id) {

			$episodes_model = new Episodes_Model($episode_id);
			$tracks_model = new Tracks_Model($episodes_model->track_id);
			$series_model = new Series_Model($episodes_model->get_series_id());
			$container = $series_model->get_preset_format();
			$video_encoder = $series_model->get_video_encoder();

			if($opt_copy_info)
				$container = 'vob';

			$filename = get_episode_filename($episode_id, $container, $arg_hardware);

			$input_filename = realpath($device);

			// If using MakeMKV to manually extract the tracks, change the source to match its naming scheme
			if($opt_makemkv) {
				$input_filename = dirname(realpath($device))."/title".str_pad($tracks_model->ix - 1, 2, 0, STR_PAD_LEFT).".mkv";
			}

			/** DVDs **/
			if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd") {

				if($container == 'mkv' || $container == 'mp4') {

					require 'dart.x264.php';
					require 'dart.x265.php';
					$handbrake->input_filename($input_filename);
					if($opt_vob)
						$handbrake->input_filename(get_episode_filename($episode_id, 'vob', $arg_hardware));
					$handbrake->output_filename($filename);
					$handbrake_command = $handbrake->get_executable_string();
					if($episodes_model->skip)
						echo "# $handbrake_command # skipped\n";
					else
						echo "$handbrake_command\n";

				} else if($container == 'vob') {

					require 'dart.dvd_copy.php';
					$dvd_copy->input_filename($input_filename);
					$dvd_copy->output_filename($filename);
					$dvd_copy_command = $dvd_copy->get_executable_string();
					echo "$dvd_copy_command\n";

				}
			}

			/** Blu-rays **/

			$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
			$bluray_mkv = substr($bluray_m2ts, 0, strlen($bluray_m2ts) - 4)."mkv";

			if(!($opt_skip_existing && file_exists($bluray_m2ts)) && $disc_type == "bluray") {

				require 'dart.bluray_copy.php';
				$bluray_copy->input_filename($input_filename);
				$bluray_copy->output_filename($bluray_m2ts);
				$bluray_copy_command = $bluray_copy->get_executable_string();
				echo "$bluray_copy_command\n";

			}

			if(!($opt_skip_existing && file_exists($bluray_mkv)) && $disc_type == "bluray") {

				require 'dart.bluray_mkvmerge.php';
				$mkvmerge->input_filename($bluray_m2ts);
				$mkvmerge->output_filename($bluray_mkv);
				$mkvmerge_command = $mkvmerge->get_executable_string();
				echo "$mkvmerge_command\n";

			}

		}

	}
