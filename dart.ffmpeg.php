<?php

if($opt_rip_info && $episode_id) {

	$ffmpeg = new FFMpeg();
	$ffmpeg->set_binary('ffmpeg');

	if($debug)
		$ffmpeg->debug();

	if($verbose)
		$ffmpeg->verbose();

	if($dry_run)
		$ffmpeg->dry_run();

	$ffmpeg->input_filename('-');
	$ffmpeg->output_filename($filename);

	$video_quality = $series_model->get_crf();

	// Improve video quality when using ffmpeg + x265
	if($video_encoder == 'x265')
		$video_quality -= 2;

	$ffmpeg_opts = "crf=$video_quality:$preset_opts:colorprim=smpte170m:transfer=smpte170m:colormatrix=smpte170m";

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

	$ffmpeg->add_video_filter('bwdif,fps=fps=60');

	$ffmpeg->set_acodec('copy');

	$audio_streamid = $tracks_model->get_first_english_streamid();
	if($audio_streamid)
		$ffmpeg->add_audio_stream($audio_streamid);

	$ffmpeg_opts .= " -metadata:s:a:0 'language=eng'";

	$ffmpeg->add_opts($ffmpeg_opts);

	$ffmpeg_command = $ffmpeg->get_executable_string();

}
