<?php

// Display encode instructions about a disc
if($disc_indexed && ($opt_encode_info || $opt_scan || $opt_encode || $opt_copy || $opt_remux || $opt_ffprobe) && !$opt_ffplay) {

	// Override in config.local.php
	if(isset($config_qa_max))
		$qa_max = $config_qa_max;
	else
		$qa_max = 90;

	$collection_id = $dvds_model->get_collection_id();

	// Override DVD encoder if disc is flagged with bugs
	$dvd_encoder = $dvds_model->get_encoder();

	if($dvd_encoder == 'ffpipe') {
		$dvd_encoder = 'ffmpeg';
		$opt_ffpipe = true;
	}

	elseif($disc_type == 'dvd' && $dvd_encoder == '')
		$dvd_encoder = 'handbrake';
	elseif($disc_type == 'bluray' && $dvd_encoder == '')
		$dvd_encoder = 'ffmpeg';

	if($opt_handbrake)
		$dvd_encoder = 'handbrake';
	elseif($opt_ffmpeg)
		$dvd_encoder = 'ffmpeg';
	elseif($opt_copy)
		$dvd_encoder = 'dvd_copy';
	elseif($opt_remux)
		$dvd_encoder = 'ffmpeg';

	$dvd_episodes = $dvds_model->get_episodes();

	// On QA run, only encode the first one
	if($opt_qa || $opt_one) {
		foreach($dvd_episodes as $episode_id) {
			$episodes_model = new Episodes_Model($episode_id);
			if($episodes_model->skip)
				continue;
			$dvd_episodes = array($episode_id);
			break;
		}
	}

	if($disc_type == 'dvd' && $opt_copy)
		$container = 'mpg';
	elseif($disc_type == 'bluray' && $opt_copy)
		$container = 'm2ts';
	else
		$container = 'mkv';

	$hardware = 'nvidia';

	if(isset($config_hardware))
		$hardware = $config_hardware;

	if(isset($arg_hardware))
		$hardware = $arg_hardware;

	$encode_subtitles = true;
	if($opt_no_subtitles)
		$encode_subtitles = false;

	$ffmpeg_version = trim(shell_exec("ffmpeg -version | head -n 1 | cut -d ' ' -f 3"));

	// Display the episode names
	foreach($dvd_episodes as $episode_id) {

		$episodes_model = new Episodes_Model($episode_id);

		if($episodes_model->skip)
			continue;

		$filename = $episodes_model->get_filename($container);

		// Filename overrides
		$prefix = '';
		if($arg_prefix)
			$prefix = "$arg_prefix-";
		if($opt_qa && $dvd_encoder == 'handbrake')
			$prefix .= "hb-qa-";
		elseif($opt_qa && $dvd_encoder == 'ffmpeg' && !$opt_pipe)
			$prefix .= "ffmpeg-qa-";
		elseif($opt_qa && $dvd_encoder == 'ffmpeg' && $opt_pipe)
			$prefix .= "ffpipe-qa-";
		if($arg_vcodec)
			$prefix .= "$arg_vcodec-";
		if($arg_acodec)
			$prefix .= "$arg_acodec-";
		if($arg_crf)
			$prefix .= "q-$arg_crf-";
		if($arg_vf) {
			$vf_name = current(explode('=', $arg_vf));
			$prefix .= "vf-$vf_name-";
		}
		if($opt_fast)
			$prefix .= "fast-";
		elseif($opt_slow)
			$prefix .= "vslow-";

		// Skip existing output files
		if(file_exists($filename) && $opt_skip_existing)
			continue;

		$tracks_model = new Tracks_Model($episodes_model->track_id);
		$series_model = new Series_Model($episodes_model->get_series_id());
		$nsix = $series_model->nsix;
		$vcodec = $series_model->get_vcodec();
		$video_format = strtolower($tracks_model->format);

		if($disc_type == 'dvd' && $dvd_encoder == 'dvd_copy') {

			// Skip existing output files
			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$dvd_copy = new DVDCopy();

			if($debug)
				$dvd_copy->debug();

			if($verbose)
				$dvd_copy->verbose();

			$dvd_copy->input_filename($device);
			$dvd_copy->output_filename($filename);
			$dvd_copy->input_track($tracks_model->ix);
			$dvd_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

			$dvd_copy_command = $dvd_copy->get_executable_string();

			if($opt_encode_info) {
				echo "$dvd_copy_command\n";
				continue;
			}

			$arg_device = escapeshellarg(realpath($device));

			$arg_filename = escapeshellarg($filename);

			// Stolen from emo :D
			$episode_metadata = $episodes_model->get_metadata();
			$title = $episode_metadata['title'];
			$d_episode_title = $episodes_model->get_display_name();
			$arr_d_info = array();
			$arr_d_info[] = $episode_metadata['series_title'];
			$d_season = '';
			if($episode_metadata['season'])
				$d_season = "s${episode_metadata['season']}";
			if($episode_metadata['episode_number'])
				$d_season .= "e${episode_metadata['episode_number']}";
			if($d_season)
				$arr_d_info[] = "$d_season";
			if($episode_metadata['part'])
				$title .= " (".$episode_metadata['part'].")";
			$arr_d_info[] = $title;
			$d_info = implode(" : ", $arr_d_info);

			echo "[Copying]\n";
			echo "* $d_info\n";
			echo "* Source: $arg_device\n";
			echo "* Target: $arg_filename\n";

			passthru($dvd_copy_command);

			continue;

		}

		if($disc_type == 'bluray' && $opt_ffprobe) {

			if($disc_type == 'bluray') {

				$dvd_bugs = $dvds_model->get_bugs();

				$ffmpeg->set_disc_type('bluray');

				$ffmpeg->input_filename($device);

				$ffmpeg->input_track($tracks_model->ix);

				$starting_chapter = $episodes_model->starting_chapter;
				if($starting_chapter)
					$ffmpeg->set_chapters($starting_chapter, null);

			}

			if($opt_encode_info) {
				echo "$ffmpeg_command\n";
				continue;
			}

			// If you ever feel like showing it in JSON
			/*
			$opt_json = false;
			if($opt_json) {
				$ffmpeg_command .= ' -show_format -of json -show_streams 2> /dev/null';
				passthru($ffmpeg_command);
				continue;
			}

			echo "[Probing]\n";
			$arg_device = escapeshellarg($device);
			echo "* Source: $arg_device\n";
			echo "* Track: ".$tracks_model->ix."\n";

			echo "[ffmpeg]\n";

			passthru($ffmpeg_command);

			continue;
			*/

		}

		$uhd = false;
		if($disc_type == 'bluray' && substr($nsix, 0, 2) == '4K')
			$uhd = true;

		$denoise = $series_model->get_denoise();
		if($opt_denoise)
			$denoise = true;

		// For now, only testing on cartoons and TV shows
		$sharpen = $series_model->get_sharpen();
		if($opt_sharpen && $collection_id == 1)
			$sharpen = 'animation';
		if($opt_sharpen && $collection_id == 2)
			$sharpen = 'film';
		$sharpen_tune = $series_model->get_sharpen_tune();

		// A note about setting fps with ffmpeg: use 'vf=fps' to set it, instead of '-r fps'. See
		// https://trac.ffmpeg.org/wiki/ChangingFrameRate for reasoning.
		// "For variable frame rate formats, like Matroska, the -r value acts as a ceiling, so that a lower frame rate input stream will pass through, and a higher frame rate stream, will have frames dropped, in order to match the target rate."
		// "The -r value also acts as an indication to the encoder of how long each frame is, and can affect the ratecontrol decisions made by the encoder."
		// "fps, as a filter, needs to be inserted in a filtergraph, and will always generate a CFR stream. It offers five rounding modes that affect which source frames are dropped or duplicated in order to achieve the target framerate. See the documentation of the fps filter for details."
		// https://ffmpeg.org/ffmpeg-filters.html#fps
		// bwdif bob will cause stuttering on playback on Sony 4K TV with original FPS
		if($video_format == 'pal')
			$fps = 25;
		else
			$fps = 29.97;

		if($arg_fps)
			$fps = $arg_fps;

		if($disc_type == 'dvd' && $opt_copy)
			$container = 'mpg';

		if($disc_type == 'dvd' && $opt_scan) {

			$arg_device = escapeshellarg($device);

			$handbrake_command = "HandBrakeCLI --input $arg_device";

			$tracks_model = new Tracks_Model($episodes_model->track_id);

			$handbrake_command .= " --title '".$tracks_model->ix."'";

			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;

			if($starting_chapter)
				$handbrake_command .= "--chapters '".$starting_chapter."-";
			if($ending_chapter)
				$handbrake_command .= "$ending_chapter";
			if($starting_chapter || $ending_chapter)
				$handbrake_command .= "'";

			$handbrake_command .= " --scan 2>&1";
			echo "$handbrake_command\n";

		}

		if($dvd_encoder == 'handbrake')
			require 'dart.encode_handbrake.php';

		if($dvd_encoder == 'ffmpeg')
			require 'dart.encode_ffmpeg.php';

		/** Blu-rays **/

		// Note that ffmpeg-7.1.1 doesn't copy chapters by default (unlike dvdvideo). If you want
		// them in there, you'll have to do it another way. Right now, I haven't used chapters in
		// years, so I'm okay without them.
		if($disc_type == 'bluray' && $dvd_encoder == 'ffmpeg') {

			$episodes_model = new Episodes_Model($episode_id);

			if($episodes_model->skip)
				continue;

			$ffmpeg = new FFMpeg();

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->set_disc_type('bluray');

			$acodec = $series_model->get_acodec();
			$ffmpeg->set_acodec($acodec);

			if($opt_ffpipe)
				$dvd_encoder = 'ffpipe';

			if(!$opt_ffpipe) {

				$ffmpeg->set_encoder('ffmpeg');

				$ffmpeg->input_filename($device);

			} elseif($opt_ffpipe) {

				$ffmpeg->set_encoder('ffpipe');

				$ffmpeg->input_filename('-');

				$bluray_copy = new BlurayCopy();
				$bluray_copy->input_filename($device);

				$bluray_copy->input_track($tracks_model->ix);
				$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_chapters = new BlurayChapters();

				$bluray_chapters->input_filename($device);

				$bluray_chapters->input_track($tracks_model->ix);

				$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_copy->output_filename("-");

				$bluray_copy_command = $bluray_copy->get_executable_string();

			}

			if($tracks_model->codec == 'vc1') {

				/** Video **/

				$ffmpeg->set_vcodec('h264_nvenc');

				$cq = $series_model->get_crf();

				$ffmpeg->set_cq($cq);

				$ffmpeg->set_crf(null);

				if($opt_fast)
					$ffmpeg->set_preset('ultrafast');
				elseif($opt_slow)
					$ffmpeg->set_preset('veryslow');

			}

			$ffmpeg->output_filename($filename);

			$ffmpeg->input_track($tracks_model->ix);

			$audio_streamid = $tracks_model->get_first_english_streamid('bluray');
			if($uhd)
				$ffmpeg->add_audio_stream('a:0');
			else
				$ffmpeg->add_audio_stream($audio_streamid);

			// HD Blu-rays, first PGS is 0x1200
			// UHD Blu-rays, first PGS is 0x12a0 (usually, 4KSAN has 1200)
			if($encode_subtitles) {
				$ffmpeg->enable_subtitles();
				$ffmpeg->add_subtitle_stream('0x1200?');
				$ffmpeg->add_subtitle_stream('0x12a0?');
			}

			$starting_chapter = $episodes_model->starting_chapter;
			if($starting_chapter)
				$ffmpeg->set_chapters($starting_chapter, null);

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			if($opt_test_existing)
				$ffmpeg->overwrite(false);
			else
				$ffmpeg->overwrite(true);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			if($opt_ffpipe)
				$ffmpeg_command = "$bluray_copy_command 2> /dev/null | $ffmpeg_command";

			if($opt_encode) {
				$encode_command = $ffmpeg_command;
				require 'dart.encode_episode.php';
			} else {
				echo "$ffmpeg_command\n";
			}

		}

	}

}
