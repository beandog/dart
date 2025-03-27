<?php

// Display encode instructions about a disc
if($disc_indexed && ($opt_encode_info || $opt_copy_info || $opt_ffplay || $opt_ffprobe)) {

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

		if($disc_type == 'bluray')
			$container = 'mkv';

		$filename = get_episode_filename($episode_id, $container, $arg_hardware);

		$input_filename = realpath($device);

		if($opt_ffplay && $disc_type == 'dvd') {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffplay');

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

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			$video_filters = array();

			// Have a placeholder if there are *none* so that it's easier to edit command-line
			if(!count($video_filters))
				$ffmpeg->add_video_filter("bwdif=deint=interlaced");

			foreach($video_filters as $vf) {
				$ffmpeg->add_video_filter($vf);
			}

			$ffmpeg->fullscreen();

			$ffplay_command = $ffmpeg->get_executable_string();

			echo "$ffplay_command\n";

		}

		if($opt_ffprobe && $disc_type == 'dvd') {

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

		/** Encode DVDs **/
		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd" && $opt_encode_info && $opt_handbrake) {

			require 'dart.x264.php';
			require 'dart.x265.php';

			$handbrake->input_filename($input_filename);

			$handbrake->output_filename($filename);
			$handbrake_command = $handbrake->get_executable_string();
			if($opt_time)
				$handbrake_command = "tout $handbrake_command";

			echo "$handbrake_command\n";

		}

		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd" && $opt_encode_info && $opt_dvdrip) {

			$dvdrip = new DVDRip;
			$dvdrip->verbose($verbose);
			$dvdrip->debug($debug);

			// Unsupported, auto-sets based on NTSC or PAL
			// $fps = $series_model->get_preset_fps();

			/** Files **/

			$dvdrip->input_filename($device);
			$dvdrip->input_track($tracks_model->ix);

			/** Video **/

			$dvdrip->set_vcodec($video_encoder);
			$video_quality = $series_model->get_crf();

			if($arg_crf)
				$video_quality = abs(intval($arg_crf));

			$dvdrip->set_video_quality($video_quality);

			/** Audio **/

			$dvdrip->set_acodec('en');

			$audio_encoder = $series_model->get_audio_encoder();

			$audio_encoder = 'aac';

			$dvdrip->set_acodec($audio_encoder);

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

		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd" && $opt_encode_info && $opt_ffmpeg) {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_binary('ffmpeg');

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->input_filename($device);

			/** Chapters **/
			$starting_chapter = $episodes_model->starting_chapter;
			$ending_chapter = $episodes_model->ending_chapter;
			if($starting_chapter || $ending_chapter) {
				$ffmpeg->set_chapters($starting_chapter, $ending_chapter);
			}

			$ffmpeg->input_track($tracks_model->ix);

			$video_quality = $series_model->get_crf();

			if($arg_crf)
				$video_quality = abs(intval($arg_crf));

			$ffmpeg->set_crf($video_quality);

			if($video_encoder == 'x264') {
				$ffmpeg->set_vcodec('libx264');
				$ffmpeg->set_tune($series_model->get_x264_tune());
			}

			if($video_encoder == 'x265') {
				$ffmpeg->set_vcodec('libx265');
			}

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			// Set video filters based on frame info
			$crop = $episodes_model->crop;

			if($crop != null && $crop != '720:480:0:0')
				$ffmpeg->add_video_filter("crop=$crop");

			$fps = $series_model->get_preset_fps();

			$video_filters = array();

			$video_filters[] = "bwdif=deint=interlaced";

			// Have a placeholder if there are *none* so that it's easier to edit command-line
			if(!count($video_filters))
				$ffmpeg->add_video_filter("bwdif=deint=interlaced");

			foreach($video_filters as $vf) {
				$ffmpeg->add_video_filter($vf);
			}

			/** Audio **/
			$audio_streamid = $tracks_model->get_first_english_streamid();
			if(!$audio_streamid)
				$audio_streamid = '0x80';
			$ffmpeg->add_audio_stream($audio_streamid);

			$ffmpeg->set_acodec('copy');

			/** Subtitles **/
			$subp_ix = $tracks_model->get_first_english_subp();
			if(!$subp_ix && ($tracks_model->get_num_active_subp_tracks() == 1))
				$subp_ix = '0x20';

			if($subp_ix) {
				// Not sure if I need this now that I'm pulling straight from dvdvideo format
				// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
				$ffmpeg->add_subtitle_stream($subp_ix);
			}

			// Ignore closed captioning completely since ffmpeg garbles it.
			if($tracks_model->has_closed_captioning()) {
				$ffmpeg->remove_closed_captioning();
			}

			$srt_filename = "subs-${episode_id}.srt";
			$ssa_filename = "subs-${episode_id}.ssa";
			if(file_exists($srt_filename))
				$ffmpeg->input_filename($srt_filename);
			if(file_exists($ssa_filename))
				$ffmpeg->input_filename($ssa_filename);

			if($opt_qa) {
				$filename = "ffmpeg-qa-$filename";
			}

			$ffmpeg->output_filename($filename);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			echo "$ffmpeg_command\n";

		}

		/** Copy DVD tracks **/
		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd" && $container == "mpg") {

			require 'dart.dvd_copy.php';
			$dvd_copy->input_filename($input_filename);
			$dvd_copy->output_filename($filename);
			$dvd_copy_command = $dvd_copy->get_executable_string();
			echo "$dvd_copy_command\n";

		}

		if(!($opt_skip_existing && file_exists($filename)) && $disc_type == "dvd" && $opt_encode_info && $opt_ffpipe) {

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

			if($video_encoder == 'x264') {
				$ffmpeg->set_vcodec('libx264');
				$ffmpeg->set_tune($series_model->get_x264_tune());
			}

			if($video_encoder == 'x265') {
				$ffmpeg->set_vcodec('libx265');
			}

			if($opt_qa)
				$ffmpeg->set_duration($qa_max);

			// Set video filters based on frame info
			$crop = $episodes_model->crop;

			if($crop != null && $crop != '720:480:0:0')
				$ffmpeg->add_video_filter("crop=$crop");

			$fps = $series_model->get_preset_fps();

			$video_filters = array();

			$video_filters[] = "bwdif=deint=interlaced";

			// Have a placeholder if there are *none* so that it's easier to edit command-line
			if(!count($video_filters))
				$ffmpeg->add_video_filter("bwdif=deint=interlaced");

			foreach($video_filters as $vf) {
				$ffmpeg->add_video_filter($vf);
			}

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

			// Ignore closed captioning completely since ffmpeg garbles it.
			if($tracks_model->has_closed_captioning()) {
				$ffmpeg->remove_closed_captioning();
			}

			if($opt_qa) {
				$filename = "ffmpeg-qa-$filename";
			}

			$ffmpeg->output_filename($filename);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			$ffpipe_command = "$dvd_copy_command 2> /dev/null | $ffmpeg_command";

			echo "$ffpipe_command\n";

		}

		if($opt_remux && $disc_type == "dvd") {

			$remux_filename = "remux-${episode_id}.mkv";

			if(!($opt_skip_existing && file_exists($remux_filename))) {

				require 'dart.dvd_copy.php';
				$dvd_copy->input_filename($input_filename);
				$dvd_copy->output_filename('-');
				$dvd_copy_command = $dvd_copy->get_executable_string();

				$dvd_remux_command = "$dvd_copy_command | ffmpeg -fflags +genpts -i - -codec copy -y $remux_filename";

				echo "$dvd_remux_command\n";

			}

		}

		/** Blu-rays **/

		if($disc_type == 'bluray' && ($opt_ffprobe || $opt_ffplay)) {

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_disc_type('bluray');

			$ffmpeg->input_filename($device);

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
		if($disc_type == "bluray" && $opt_ffmpeg) {

			$episodes_model = new Episodes_Model($episode_id);

			if($episodes_model->skip)
				continue;

			if(file_exists($filename) && $opt_skip_existing)
				continue;

			$ffmpeg = new FFMpeg();
			$ffmpeg->set_disc_type('bluray');

			if($debug)
				$ffmpeg->debug();

			if($verbose)
				$ffmpeg->verbose();

			$ffmpeg->input_filename($device);

			$ffmpeg->input_track($tracks_model->ix);

			$starting_chapter = $episodes_model->starting_chapter;
			if($starting_chapter) {
				$ffmpeg->set_chapters($starting_chapter, null);
			}

			if($opt_qa) {
				$ffmpeg->set_duration($qa_max);
				$filename = "ffmpeg-qa-$filename";
			}

			$ffmpeg->output_filename($filename);

			$ffmpeg_command = $ffmpeg->get_executable_string();

			if($opt_time)
				$ffmpeg_command = "tout $ffmpeg_command";

			if($opt_log_progress)
				$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

			echo "$ffmpeg_command\n";

		}

		if($disc_type == "bluray" && $opt_bluraycopy) {

			$display_txt = true;
			$display_m2ts = true;
			$display_mkv = true;
			$bluray_encode = false;
			$bluray_encode_audio = false;

			$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
			$bluray_txt = substr($filename, 0, strlen($filename) - 3)."txt";
			$bluray_vc1 = substr($filename, 0, strlen($filename) - 3)."VC1.mkv";
			$bluray_flac = substr($filename, 0, strlen($filename) - 3)."flac";
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

			$bluray_ffmpeg_command = "ffmpeg -i '$bluray_m2ts' -map '0:a:0' -acodec 'flac' -y '$bluray_flac'";

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

			if($bluray_encode_audio) {
				$mkvmerge->add_input_filename($bluray_flac);
				// Make FLAC primary audio stream
				$mkvmerge->set_track_order('0:0,1:0,0:1');
			}

			$mkvmerge_command = $mkvmerge->get_executable_string();

			if($display_txt && !$bluray_encode)
				echo "$bluray_chapters_command\n";

			if($display_m2ts && !$bluray_encode)
				echo "$bluray_m2ts_command\n";

			if($bluray_encode_audio && !file_exists($bluray_flac))
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

					// FIXME? this probably shouldn't be here, HandBrake would add it directly
					// $handbrake->add_audio_track(1);

					$handbrake->add_audio_encoder('flac');
				}

				// There are edge cases where the first audio track is Dolby
				// Digital, and the second audio track is Dolby TrueHD. In
				// these cases, the primary track is not ac3, but truhd. They
				// will show incorrectly in bluray_info, and mediainfo from the
				// m2ts files, but when remuxed or re-encoded they will properly
				// show the correct codec.
				// In these cases, also duplicate the truhd track as flac.
				$audio_id = $audio_model->find_audio_id($tracks_model->id, 2);
				if($audio_id) {
					$audio_model->load($audio_id);
					$secondary_format = $audio_model->format;
				} else {
					$secondary_format = '';
				}

				if($primary_format == 'ac3' && $secondary_format == 'truhd') {
					$handbrake->add_audio_track(1);
					$handbrake->add_audio_encoder('flac');
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
