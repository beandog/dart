<?php

if(($opt_rip_info || $opt_pts_info) && $episode_id) {

	if($opt_rip_info) {

		$ffmpeg = new FFMpeg();
		$ffmpeg->set_binary('ffmpeg');

		if($debug)
			$ffmpeg->debug();

		if($verbose)
			$ffmpeg->verbose();

		if($dry_run)
			$ffmpeg->dry_run();

		$subp_ix = $tracks_model->get_first_english_subp();
		if($subp_ix)
			$ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
		$ffmpeg->input_filename('-');
		$ffmpeg->output_filename($filename);

		$video_quality = $series_model->get_crf();

		if($arg_crf)
			$video_quality = abs(intval($arg_crf));

		if($tracks_model->format == 'NTSC')
			$ffmpeg_opts = "crf=$video_quality:colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m";
		elseif($tracks_model->format == 'PAL')
			$ffmpeg_opts = "crf=$video_quality:colorprim=bt470bg:transfer=gamma28:colormatrix=bt470bg";
		// if($preset_opts)
		//	$ffmpeg_opts .= ":$preset_opts";

		$x264_tune = $series_model->get_x264_tune();

		if($video_encoder == 'x264') {
			$ffmpeg->set_vcodec('libx264');
			$ffmpeg_opts = "-x264-params '$ffmpeg_opts'";
			if($x264_tune)
				$ffmpeg_opts .= " -tune '$x264_tune'";
		} else if($video_encoder == 'x265') {
			$ffmpeg->set_vcodec('libx265');
			$ffmpeg_opts = "-x265-params '$ffmpeg_opts'";
			if($x264_tune)
				$ffmpeg_opts .= " -tune '$x264_tune'";
		}

		$x264_preset = $series_model->get_x264_preset();
		if($arg_preset)
			$x264_preset = $arg_preset;
		$ffmpeg_opts .= " -preset '$x264_preset'";

		if($opt_qa)
			$ffmpeg->set_duration($qa_max);

		// Set video filters based on frame info
		$progressive = $episodes_model->progressive;
		$top_field = $episodes_model->top_field;
		$bottom_field = $episodes_model->bottom_field;
		$crop = $episodes_model->crop;
		$fps = $series_model->get_preset_fps();

		$detelecine = false;
		$deinterlace = false;

		// Detelecine by default and output to 24 FPS
		if($progressive == null && $top_field == null && $bottom_field == null) {
			$fps = "24000/1001";
			$detelecine = true;
		}

		while($progressive || $top_field || $bottom_field) {

			// Top Field only
			if($progressive == 0 && $bottom_field == 0) {
				$detelecine = true;
				break;
			}

			// Bottom Field only
			if($progressive == 0 && $top_field == 0) {
				$detelecine = true;
				break;
			}

			// Top Field only, but under 1 second
			if($top_field <= 30 && $bottom_field == 0)
				break;

			// Bottom Field only, but under 1 second
			if($top_field == 0 && $bottom_field <= 30)
				break;

			// Top Field and Bottom Field, each under one second
			if($top_field <= 30 && $bottom_field <= 30)
				break;

			// Progressive is not the majority
			if($progressive < $top_field || $progressive < $bottom_field || $progressive < ($top_field + $bottom_field)) {
				$detelecine = true;
				break;
			}

			// Progressive fields the majority, but no Bottom Field
			if($progressive > $top_field && $bottom_field == 0) {
				$deinterlace = true;
				break;
			}

			// Progressive fields the majority, but no Top Field
			if($progressive > $bottom_field && $top_field == 0) {
				$deinterlace = true;
				break;
			}

			// Progressive and Top Fields, less than one second of Bottom Field
			if($progressive > $top_field && $bottom_field <= 30) {
				$deinterlace = true;
				break;
			}

			// Progressive and Bottom Fields, less than one second of Top Field
			if($progressive > $bottom_field && $top_field <= 30) {
				$deinterlace = true;
				break;
			}

			// All other cases
			$detelecine = true;

			break;

		}

		if($crop != null && $crop != '720:480:0:0')
			$ffmpeg->add_video_filter("crop=$crop");

		if($detelecine)
			$ffmpeg->add_video_filter("pullup,dejudder");

		if($deinterlace && $fps > 30)
			$ffmpeg->add_video_filter("bwdif=deint=1");
		elseif($deinterlace)
			$ffmpeg->add_video_filter("bwdif=mode=send_frame:deint=1");

		if($fps && $fps == 24)
			$ffmpeg->add_video_filter("fps=fps=24000/1001");
		elseif($fps)
			$ffmpeg->add_video_filter("fps=fps=$fps");

		$audio_streamid = $tracks_model->get_first_english_streamid();
		if($audio_streamid) {
			$ffmpeg->add_audio_stream($audio_streamid);
			$ffmpeg->set_acodec('copy');
		}

		if($subp_ix)
			$ffmpeg->add_subtitle_stream($subp_ix);

		$ffmpeg_opts .= " -metadata:s:a:0 'language=eng'";

		$ffmpeg->add_opts($ffmpeg_opts);

		$ffmpeg_command = $ffmpeg->get_executable_string();

	}

	if($opt_pts_info) {

		$ffmpeg = new FFMpeg();
		$ffmpeg->set_binary('ffmpeg');

		if($debug)
			$ffmpeg->debug();

		if($verbose)
			$ffmpeg->verbose();

		if($dry_run)
			$ffmpeg->dry_run();

		$ffmpeg->input_filename('-');
		$ffmpeg->output_filename('-');

		$ffmpeg->add_video_filter('showinfo,blackframe,cropdetect');

		$ffmpeg->disable_stats();

		$ffmpeg_command = $ffmpeg->get_executable_string();

		$ffmpeg_command .= " &> $filename";

	}

}
