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

		$ffmpeg_opts = "crf=$video_quality";
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
		$crop = $episodes_model->crop;

		if($crop != null && $crop != '720:480:0:0')
			$ffmpeg->add_video_filter("crop=$crop");

		/*
		if($detelecine)
			$ffmpeg->add_video_filter("pullup,dejudder");
		*/

		/*
		if($deinterlace && $fps > 30)
			$ffmpeg->add_video_filter("bwdif=deint=1");
		elseif($deinterlace)
			$ffmpeg->add_video_filter("bwdif=mode=send_frame:deint=1");
		*/

		// Detelecine by default and output to 24 FPS
		$ffmpeg->add_video_filter("pullup");
		$ffmpeg->add_video_filter("fps=fps=24000/1001");

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

		$ffmpeg->add_video_filter('showinfo,cropdetect');

		$ffmpeg->disable_stats();

		$ffmpeg_command = $ffmpeg->get_executable_string();

		$ffmpeg_command .= " &> $filename";

	}

}
