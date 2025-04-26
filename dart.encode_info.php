<?php

// Display encode instructions about a disc
if($disc_indexed && ($opt_encode_info || $opt_copy_info || $opt_ffplay || $opt_ffprobe || $opt_scan || $opt_remux)) {

	// Override in conifg.local.php
	if(!isset($qa_max))
		$qa_max = 60;

	// Override DVD encoder if disc is flagged with bugs
	$dvd_encoder = $dvds_model->get_encoder();

	// Default encoder
	if($disc_type == 'dvd' && $dvd_encoder == '')
		$dvd_encoder = 'ffmpeg';

	if($disc_type == 'bluray' && $dvd_encoder == '')
		$dvd_encoder = 'ffmpeg';

	if($opt_handbrake)
		$dvd_encoder = 'handbrake';
	elseif($opt_ffmpeg)
		$dvd_encoder = 'ffmpeg';
	elseif($opt_ffprobe)
		$dvd_encoder = 'ffprobe';
	elseif($opt_ffpipe)
		$dvd_encoder = 'ffpipe';
	elseif($opt_copy_info)
		$dvd_encoder = 'dvd_copy';
	elseif($opt_remux)
		$dvd_encoder = 'remux';

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

	$input_filename = realpath($device);

	// Display the episode names
	foreach($dvd_episodes as $episode_id) {

		$episodes_model = new Episodes_Model($episode_id);

		if($episodes_model->skip)
			continue;

		$tracks_model = new Tracks_Model($episodes_model->track_id);
		$series_model = new Series_Model($episodes_model->get_series_id());
		$vcodec = $series_model->get_vcodec();
		$video_deint = $dvds_model->get_deint();
		$video_format = strtolower($tracks_model->format);

		// A note about setting fps with ffmpeg: use 'vf=fps' to set it, instead of '-r fps'. See
		// https://trac.ffmpeg.org/wiki/ChangingFrameRate for reasoning.
		// "For variable frame rate formats, like Matroska, the -r value acts as a ceiling, so that a lower frame rate input stream will pass through, and a higher frame rate stream, will have frames dropped, in order to match the target rate."
		// "The -r value also acts as an indication to the encoder of how long each frame is, and can affect the ratecontrol decisions made by the encoder."
		// "fps, as a filter, needs to be inserted in a filtergraph, and will always generate a CFR stream. It offers five rounding modes that affect which source frames are dropped or duplicated in order to achieve the target framerate. See the documentation of the fps filter for details."
		// https://ffmpeg.org/ffmpeg-filters.html#fps
		$fps = $series_model->get_fps();
		if($video_format == 'pal' && $fps == '29.97')
			$fps = 25;
		if($video_format == 'pal' && $fps == '59.94')
			$fps = 50;

		if($arg_vcodec)
			$vcodec = $arg_vcodec;

		$container = 'mkv';

		if($disc_type == 'dvd' && $opt_copy_info)
			$container = 'mpg';

		$filename = $episodes_model->get_filename($container);

		if($disc_type == 'dvd' && $opt_ffplay) {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffplay');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			/** Video **/
			$deint_filter = "bwdif=deint=$video_deint";
			$ffmpeg->add_video_filter($deint_filter);

			// Not sure if I really need this or not, at the very least, you can be sure it matches
			// what the encode would look like.
			if($fps)
				$ffmpeg->add_video_filter("fps=$fps");

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
			}

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			$ffmpeg->fullscreen();

			$ffplay_command = $ffmpeg->get_executable_string();

			echo "$ffplay_command\n";

		}

		if($disc_type == 'dvd' && $opt_ffprobe) {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffprobe');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
			}

			$ffprobe_command = $ffmpeg->ffprobe();

			echo "$ffprobe_command\n";

		}

		if($disc_type == 'dvd' && $opt_scan) {

			$handbrake_command = "HandBrakeCLI --input '".escapeshellcmd($input_filename)."'";

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

		// Filename overrides
		$prefix = '';
		if($opt_qa && $dvd_encoder == 'handbrake')
			$prefix = "hb-qa-";
		elseif($opt_qa && $dvd_encoder == 'ffmpeg')
			$prefix = "ffmpeg-qa-";
		elseif($opt_qa && $dvd_encoder == 'ffpipe')
			$prefix = "ffpipe-qa-";
		if($arg_vcodec)
			$prefix .= "$arg_vcodec-";
		if($arg_crf)
			$prefix .= "q-$arg_crf-";
		if($opt_fast)
			$prefix .= "fast-";
		elseif($opt_slow)
			$prefix .= "slow-";

		$filename = $prefix.$filename;

		/** Encode DVDs **/
		/*
		 * Classic ripping using HandBrake
		 */
		if($disc_type == 'dvd' && $opt_encode_info && $dvd_encoder == 'handbrake') {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$handbrake = new HandBrake;
			$handbrake->set_binary($handbrake_bin);
			$handbrake->verbose($verbose);
			$handbrake->debug($debug);

			/** Files **/

			$handbrake->input_filename($input_filename);
			$handbrake->input_track($tracks_model->ix);

			/** Video **/

			$handbrake->set_vcodec($vcodec);

			$video_quality = $series_model->get_crf();
			if($arg_crf)
				$video_quality = abs(intval($arg_crf));
			$handbrake->set_video_quality($video_quality);

			if($opt_fast)
				$handbrake->set_x264_preset('ultrafast');
			elseif($opt_slow)
				$handbrake->set_x264_preset('slow');

			$x264_tune = $series_model->get_x264_tune();
			if($vcodec == 'x264' && $x264_tune)
				$handbrake->set_x264_tune($x264_tune);

			/** Frame and fields **/

			// Set framerate
			if($fps)
				$handbrake->set_video_framerate($fps);

			/** Audio **/

			$handbrake->add_audio_track($tracks_model->audio_ix);

			$acodec = $series_model->get_acodec();
			if($acodec == 'fdk_aac' || $acodec == 'ac3' || $acodec == 'flac' || $acodec == 'mp3') {
				$handbrake->add_acodec($acodec);
				if($acodec == 'mp3')
					$handbrake->set_audio_bitrate('320k');
			} elseif($acodec == 'fdk_aac,copy') {
				$handbrake->add_acodec('fdk_aac');
				$handbrake->add_acodec('copy');
			} else {
				$handbrake->add_acodec('copy');
			}

			/** Subtitles **/

			// Check for a subtitle track

			$subp_ix = $tracks_model->get_first_english_subp();
			$has_closed_captioning = $tracks_model->has_closed_captioning();

			// If we have a VobSub one, add it
			// Otherwise, check for a CC stream, and add that
			if($subp_ix) {
				$handbrake->add_subtitle_track($subp_ix);
			} elseif($has_closed_captioning) {
				$num_subp_tracks = $tracks_model->get_num_active_subp_tracks();
				$closed_captioning_ix = $num_subp_tracks + 1;
				$handbrake->add_subtitle_track($closed_captioning_ix);
			}

			/** Chapters **/

			$handbrake->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
			$handbrake->add_chapters();

			$handbrake->set_video_format($tracks_model->format);

			$handbrake->output_filename($filename);

			if($opt_qa)
				$handbrake->set_duration($qa_max);

			$handbrake->output_filename($filename);

			$handbrake_command = $handbrake->get_executable_string();

			if($opt_time)
				$handbrake_command = "tout $handbrake_command";

			echo "$handbrake_command\n";

		}

		/*
		 * Use in-development dvd_rip
		 */
		if($disc_type == 'dvd' && $opt_encode_info && $opt_dvdrip) {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$dvdrip = new DVDRip;
			$dvdrip->verbose($verbose);
			$dvdrip->debug($debug);

			/** Files **/

			$dvdrip->input_filename($input_filename);
			$dvdrip->input_track($tracks_model->ix);

			/** Video **/

			$dvdrip->set_vcodec($vcodec);
			$video_quality = $series_model->get_crf();

			if($arg_crf)
				$video_quality = abs(intval($arg_crf));

			$dvdrip->set_video_quality($video_quality);

			/** Audio **/

			$dvdrip->set_acodec('en');

			$acodec = $series_model->get_acodec();

			$acodec = 'aac';

			$dvdrip->set_acodec($acodec);

			$dvdrip->set_audio_lang('en');

			/** Subtitles **/

			$dvdrip->set_subtitle_lang('en');

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$dvdrip->set_chapters($starting_chapter, $ending_chapter);
			}

			$dvdrip->output_filename($filename);

			$dvdrip_command = $dvdrip->get_executable_string();

			if($opt_time)
				$dvdrip_command = "tout $dvdrip_command";

			echo "$dvdrip_command\n";

		}

		/**
		 * Next-generation DVD Ripping
		 *
		 * Rip DVDs directly from source using ffmpeg
		 */

		// Extract SSA subtitles from DVDs
		$dvd_bugs = $dvds_model->get_bugs();
		$dvd_encode_ssa = false;
		if($opt_ssa && in_array('cc-only', $dvd_bugs) && $tracks_model->has_closed_captioning() && ($dvd_encoder == 'ffmpeg' || $dvd_encoder == 'ffpipe')) {

			$dvd_encode_ssa = true;

			$str_episode_id = str_pad($episode_id, 5, 0, STR_PAD_LEFT);
			$ssa_filename = "subs-".$str_episode_id."-".$series_model->nsix.".ssa";

			$ssa_filename = "subs-".basename($filename, '.mkv').".ssa";

			if(!($opt_skip_existing && file_exists($ssa_filename))) {

				require 'dart.dvd_copy.php';
				$dvd_copy->input_filename($input_filename);
				$dvd_copy->output_filename('-');
				$dvd_copy_command = $dvd_copy->get_executable_string();

				$arg_filename = escapeshellarg($ssa_filename);

				$dvd_ssa_command = "$dvd_copy_command 2> /dev/null | ffmpeg -f lavfi -i 'movie=pipe\\\\:0[out+subcc]' -y $arg_filename";

				echo "$dvd_ssa_command\n";

			}

		}

		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == 'dvd' && $opt_encode_info && $dvd_encoder == 'ffmpeg') {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffmpeg');

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->input_filename($input_filename);
			$ffmpeg->input_track($tracks_model->ix);

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			/** Video **/
			$video_quality = $series_model->get_crf();

			if($arg_crf)
				$video_quality = abs(intval($arg_crf));

			$ffmpeg->set_crf($video_quality);

			if($vcodec == 'x264') {
				$ffmpeg->set_vcodec('libx264');
				$ffmpeg->set_tune($series_model->get_x264_tune());
			}

			if($opt_fast)
				$ffmpeg->set_preset('ultrafast');
			elseif($opt_slow)
				$ffmpeg->set_preset('slow');

			if($vcodec == 'x265') {
				$ffmpeg->set_vcodec('libx265');
			}

			// Set video filters based on frame info
			$crop = $episodes_model->crop;
			if($crop != null && $crop != '720:480:0:0')
				$ffmpeg->add_video_filter("crop=$crop");

			$deint_filter = "bwdif=deint=$video_deint";
			$ffmpeg->add_video_filter($deint_filter);

			if($fps)
				$ffmpeg->add_video_filter("fps=$fps");

			/** Audio **/
			$audio_streamid = $tracks_model->get_first_english_streamid();
			if(!$audio_streamid)
				$audio_streamid = '0x80';
			$ffmpeg->add_audio_stream($audio_streamid);

			$ffmpeg->set_acodec('copy');

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
			}

			/** Subtitles **/
			$subp_ix = $tracks_model->get_first_english_subp();
			if(!$subp_ix && ($tracks_model->get_num_active_subp_tracks() == 1))
				$subp_ix = '0x20';

			if($subp_ix) {
				// Not sure if I need this now that I'm pulling straight from dvdvideo format
				// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
				$ffmpeg->add_subtitle_stream($subp_ix);
			}

			// When copying closed captioning with ffmpeg, the time indexes are always off, so
			// drop it completely.
			if($tracks_model->has_closed_captioning())
				$ffmpeg->remove_closed_captioning();

			// Use an external SSA file instead of existing closed captioning
			if($dvd_encode_ssa)
				$ffmpeg->add_ssa_filename($ssa_filename);

			$ffmpeg->output_filename($filename);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			echo "$ffmpeg_command\n";

		}

		/** Copy DVD tracks **/

		if($disc_type == 'dvd' && $dvd_encoder == 'dvd_copy') {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			require 'dart.dvd_copy.php';
			$dvd_copy->input_filename($input_filename);
			$dvd_copy->output_filename($filename);
			$dvd_copy_command = $dvd_copy->get_executable_string();
			echo "$dvd_copy_command\n";

		}

		/**
		 * Rip DVDs using dvd_copy piped to ffmpeg
		 */
		if($disc_type == 'dvd' && $opt_encode_info && ($opt_ffpipe || $dvd_encoder == 'ffpipe')) {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			require 'dart.dvd_copy.php';
			$dvd_copy->input_filename($input_filename);
			$dvd_copy->output_filename('-');
			$dvd_copy_command = $dvd_copy->get_executable_string();

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffmpeg');
			$ffmpeg->disc_type = 'dvdcopy';

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->generate_pts();

			$ffmpeg->input_filename('-');

			$video_quality = $series_model->get_crf();

			if($arg_crf)
				$video_quality = abs(intval($arg_crf));

			$ffmpeg->set_crf($video_quality);

			if($vcodec == 'x264') {
				$ffmpeg->set_vcodec('libx264');
				$ffmpeg->set_tune($series_model->get_x264_tune());
			}

			if($opt_fast)
				$ffmpeg->set_preset('ultrafast');
			elseif($opt_slow)
				$ffmpeg->set_preset('slow');

			if($vcodec == 'x265') {
				$ffmpeg->set_vcodec('libx265');
			}

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			// Set video filters based on frame info
			$crop = $episodes_model->crop;
			if($crop != null && $crop != '720:480:0:0')
				$ffmpeg->add_video_filter("crop=$crop");

			$deint_filter = "bwdif=deint=$video_deint";
			$ffmpeg->add_video_filter($deint_filter);

			if($fps)
				$ffmpeg->add_video_filter("fps=$fps");

			/** Audio **/
			$audio_streamid = $tracks_model->get_first_english_streamid();
			if(!$audio_streamid)
				$audio_streamid = '0x80';
			$ffmpeg->add_audio_stream($audio_streamid);

			$ffmpeg->set_acodec('copy');

			$subp_ix = $tracks_model->get_first_english_subp();
			if(!$subp_ix && ($tracks_model->get_num_active_subp_tracks() == 1))
				$subp_ix = '0x20';

			if($subp_ix) {
				// Not sure if I need this now that I'm pulling straight from dvdvideo format
				// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
				$ffmpeg->add_subtitle_stream($subp_ix);
			}

			// Remove closed captioning. There are only 367 cartoon episodes that have CC and *not* vobsub
			// TMNT '87 (208), TMNT 2012 (119), Droopy, and Scooby-Doo Show
			// It adds an extra step to encoding because they have to be extracted first.
			// Another reason they are being removed is that ffmpeg garbles them, they do not play
			// at the correct index time.
			// See 'view_episode_eng_subs' database view
			if($tracks_model->has_closed_captioning()) {
				$ffmpeg->remove_closed_captioning();
			}

			$ffmpeg->output_filename($filename);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			$ffpipe_command = "$dvd_copy_command 2> /dev/null | $ffmpeg_command";

			echo "$ffpipe_command\n";

		}

		/**
		 * Remux titles using dvd_copy + ffmpeg
		 */
		if($disc_type == 'dvd' && $dvd_encoder == 'remux') {

			$filename = "remux-$filename";

			if(!($opt_skip_existing && file_exists($filename))) {

				require 'dart.dvd_copy.php';
				$dvd_copy->input_filename($input_filename);
				$dvd_copy->output_filename('-');
				$dvd_copy_command = $dvd_copy->get_executable_string();

				$dvd_remux_command = "$dvd_copy_command 2> /dev/null | ffmpeg -fflags +genpts -i - -codec copy -y $filename";

				echo "$dvd_remux_command\n";

			}

		}

		/** Blu-rays **/

		if($disc_type == 'bluray' && ($opt_ffprobe || $opt_ffplay)) {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_disc_type('bluray');

			$ffmpeg->input_filename($input_filename);

			$ffmpeg->input_track($tracks_model->ix);

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$starting_chapter = $episodes_model->starting_chapter;
			if($starting_chapter)
				$ffmpeg->set_chapters($starting_chapter, null);

			if($opt_ffprobe) {
				$ffmpeg->set_binary('ffprobe');
				$ffmpeg_command = $ffmpeg->ffprobe();
			} elseif($opt_ffplay) {
				$ffmpeg->set_binary('ffplay');
				$ffmpeg_command = $ffmpeg->get_executable_string();
			}

			echo "$ffmpeg_command\n";

		}

		// Note that ffmpeg-7.1.1 doesn't copy chapters by default (unlike dvdvideo). If you want
		// them in there, you'll have to do it another way. Right now, I haven't used chapters in
		// years, so I'm okay without them.
		if($disc_type == 'bluray' && ($dvd_encoder == 'ffmpeg' || $dvd_encoder == 'ffpipe')) {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$episodes_model = new Episodes_Model($episode_id);

			if($episodes_model->skip)
				continue;

			$ffmpeg = new FFMpeg();

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->set_disc_type('bluray');

			if($dvd_encoder == 'ffmpeg') {
				$ffmpeg->set_binary('ffmpeg');
				$ffmpeg->input_filename($input_filename);
			} elseif($dvd_encoder == 'ffpipe') {

				$ffmpeg->set_binary('ffpipe');
				$ffmpeg->input_filename('-');

				$bluray_copy = new BlurayCopy();
				$bluray_copy->input_filename($input_filename);

				$bluray_copy->input_track($tracks_model->ix);
				$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_chapters = new BlurayChapters();

				$bluray_chapters->input_filename($input_filename);

				$bluray_chapters->input_track($tracks_model->ix);

				$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);

				$bluray_copy->output_filename("-");

				$bluray_copy_command = $bluray_copy->get_executable_string();

			}

			if($tracks_model->codec == 'vc1') {

				/** Video **/

				$ffmpeg->set_vcodec('libx264');

				$video_quality = $series_model->get_crf();

				if($arg_crf)
					$video_quality = abs(intval($arg_crf));

				$ffmpeg->set_crf($video_quality);

				if($opt_fast)
					$ffmpeg->set_preset('ultrafast');
				elseif($opt_slow)
					$ffmpeg->set_preset('slow');

			}

			$ffmpeg->output_filename($filename);

			$ffmpeg->input_track($tracks_model->ix);

			$ffmpeg->add_audio_stream('0x1100');

			// HD Blu-rays, first PGS is 0x1200
			// UHD Blu-rays, first PGS is 0x12a0
			$ffmpeg->add_subtitle_stream('0x1200?');
			$ffmpeg->add_subtitle_stream('0x12a0?');

			$starting_chapter = $episodes_model->starting_chapter;
			if($starting_chapter)
				$ffmpeg->set_chapters($starting_chapter, null);

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			if($dvd_encoder == 'ffpipe')
				$ffmpeg_command = "$bluray_copy_command 2> /dev/null | $ffmpeg_command";

			echo "$ffmpeg_command\n";

		}

		/*
		// Legacy mkvmerge -- needs to scan file and parse JSON output for correct track IDs.
		// Currently it's broken, and has to be changed manually a lot.
		// Also this is a FIXME because tracks_model isn't working. Not going to fix for now, until
		// this option is restored.
		if($disc_type == 'bluray' && $tracks_model->codec != 'vc1' && (($dvd_encoder == 'bluraycopy' && !$opt_ffplay && !$opt_ffmpeg) || $opt_bluraycopy)) {

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$display_txt = true;
			$display_m2ts = true;
			$display_mkv = true;

			$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
			$bluray_txt = substr($filename, 0, strlen($filename) - 3)."txt";

			if(file_exists($bluray_txt) && $opt_skip_existing)
				$display_txt = false;

			if(file_exists($bluray_m2ts) && $opt_skip_existing)
				$display_m2ts = false;

			$bluray_copy = new BlurayCopy();

			$bluray_copy->input_track($tracks_model->ix);

			$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
			$bluray_copy->output_filename($bluray_m2ts);

			$bluray_m2ts_command = $bluray_copy->get_executable_string();

			$bluray_chapters = new BlurayChapters();

			$bluray_chapters->input_filename($input_filename);

			$bluray_chapters->input_track($tracks_model->ix);

			$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
			$bluray_chapters->output_filename($bluray_txt);

			$bluray_chapters_command = $bluray_chapters->get_executable_string();

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
			$mkvmerge->output_filename($filename);
			$mkvmerge->add_chapters($bluray_txt);

			$mkvmerge_command = $mkvmerge->get_executable_string();

			if($display_txt)
				echo "$bluray_chapters_command\n";

			if($display_m2ts)
				echo "$bluray_m2ts_command\n";

			if($display_mkv)
				echo "$mkvmerge_command\n";

		}
		*/

	}

}
