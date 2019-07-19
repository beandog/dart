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

		// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
		$ffmpeg->input_filename('-');
		$ffmpeg->output_filename($filename);

		$video_quality = $series_model->get_crf();

		$ffmpeg_opts = "crf=$video_quality:colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m";
		// if($preset_opts)
		//	$ffmpeg_opts .= ":$preset_opts";

		$x264_tune = $series_model->get_x264_tune();

		if($video_encoder == 'x264') {
			$ffmpeg->set_vcodec('libx264');
			$ffmpeg_opts = "-x264-params '$ffmpeg_opts'";
			if($x264_tune)
				$ffmpeg_opts .= " -tune $x264_tune";
		} else if($video_encoder == 'x265') {
			$ffmpeg->set_vcodec('libx265');
			$ffmpeg_opts = "-x265-params '$ffmpeg_opts'";
			if($x264_tune == 'animation')
				$ffmpeg_opts .= " -tune 'animation'";
		}

		$x264_preset = $series_model->get_x264_preset();
		if($x264_preset != 'medium' && strlen($x264_preset))
			$ffmpeg_opts .= " -preset '$x264_preset'";

		$ffmpeg->add_video_filter('bwdif,fps=fps=60');

		$ffmpeg->set_acodec('copy');

		$audio_streamid = $tracks_model->get_first_english_streamid();
		if($audio_streamid)
			$ffmpeg->add_audio_stream($audio_streamid);

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

		// $ffmpeg->input_opts("-probesize '67108864' -analyzeduration '60000000'");
		$ffmpeg->input_filename('-');
		$ffmpeg->output_filename('-');

		$ffmpeg->add_video_filter('showinfo,blackframe,cropdetect');

		$ffmpeg_command = $ffmpeg->get_executable_string();

		$ffmpeg_command .= " &> $filename";

	}

}
