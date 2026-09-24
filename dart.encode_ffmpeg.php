<?php

/** Encode DVDs with ffmpeg **/

if($disc_type == 'dvd' && $dvd_encoder == 'ffmpeg') {

	$ffmpeg = new FFMpeg();

	if($debug)
		$ffmpeg->debug();

	if($verbose)
		$ffmpeg->verbose();

	if($quiet || $opt_encode)
		$ffmpeg->quiet();

	$ffmpeg->input_filename($device);
	$ffmpeg->input_track($tracks_model->ix);

	$arr_metadata = array();

	$video_deint = $series_model->bwdif;
	$dvd_deint = $dvds_model->get_deint();
	if($dvd_deint)
		$video_deint = $dvd_deint;

	if($opt_test_existing)
		$ffmpeg->overwrite(false);
	else
		$ffmpeg->overwrite(true);

	/** Video **/

	$digital_video = boolval($series_model->get_preset_digital());
	$ivtc_video = boolval($series_model->get_preset_ivtc());
	$crop_video = boolval($series_model->get_preset_crop_video());

	// Only supporting HEVC NVENC

	$vf = '';

	if($ivtc_video)
		$vf = "fieldmatch=order=tff:combpel=100:combmatch=full,bwdif=deint=$video_deint";
	else
		$vf = "bwdif=deint=$video_deint";

	if($video_format == 'pal')
		$vf = "bwdif=deint=$video_deint";

	if($crop_video) {
		$vf_crop = $episodes_model->crop;
		if($vf_crop)
			$vf .= ",crop=$vf_crop";
	}

	if($arg_vf)
		$vf .= ",$arg_vf";

	// Can use chapters when not piping
	if(!$use_pipe) {
		$starting_chapter = $episodes_model->starting_chapter;
		if($starting_chapter && $disc_type == 'dvd')
			$ffmpeg->set_chapters($starting_chapter, NULL);
		$ending_chapter = $episodes_model->ending_chapter;
		if($ending_chapter && $disc_type == 'dvd')
			$ffmpeg->set_chapters(NULL, $ending_chapter);
		if($starting_chapter && $disc_type == 'bluray')
			$ffmpeg->set_chapters($starting_chapter, NULL);
	}

	$ffmpeg->add_argument('vf', $vf);
	$ffmpeg->add_argument('vcodec', 'hevc_nvenc');
	$ffmpeg->add_argument('preset', 'p7');
	$ffmpeg->add_argument('tune', 'hq');
	$ffmpeg->add_argument('rc', 'vbr');
	$ffmpeg->add_argument('cq', '18');
	$ffmpeg->add_argument('b:v', '0');
	$ffmpeg->add_argument('maxrate:v', '0');
	$ffmpeg->add_argument('rc-lookahead', '32');
	$ffmpeg->add_argument('spatial-aq', '1');
	if($digital_video)
		$ffmpeg->add_argument('aq-strength', '6');
	else
		$ffmpeg->add_argument('aq-strength', '10');
	// Disable features not available on GTX 1060
	if($hostname != 'tobe') {
		$ffmpeg->add_argument('temporal-aq', '1');
		$ffmpeg->add_argument('bf', '3');
		$ffmpeg->add_argument('b_ref_mode', 'middle');
	}
	$ffmpeg->add_argument('multipass', 'fullres');
	$ffmpeg->add_argument('map', 'v');

	/** Audio **/
	$audio_streamid = $tracks_model->get_first_english_streamid();
	if(!$audio_streamid)
		$audio_streamid = '0x80';
	$ffmpeg->add_argument('map', "i:$audio_streamid?");

	$acodec = $series_model->get_acodec();

	if($acodec == 'mp3' || $arg_acodec == 'mp3') {
		$ffmpeg->add_argument('acodec', 'libmp3lame');
		$ffmpeg->add_argument('q:a', '0');
	} elseif($acodec == 'aac' || $arg_acodec == 'aac') {
		$ffmpeg->add_argument('acodec', 'aac');
		$ffmpeg->add_argument('vbr', '5');
	} else {
		$ffmpeg->add_argument('acodec', 'copy');
	}

	/** Subtitles **/
	if($encode_subtitles) {

		// ffmpeg doesn't always have indexing properly from DVD, and it looks like there are
		// some off by one (see MOTU2), so keep it simple. If there is *any* English one, then
		// map them. Normally there is only one, of course, but this will net all of them.
		if($tracks_model->get_num_active_subp_tracks('en')) {

			$ffmpeg->add_argument('scodec', 'copy');

			// MOTU2 is one I've run into that has two English ones. One is labeled as
			// 'Widescreen' and another as 'Letterbox'.
			$ffmpeg->add_argument('map', 's:m:language:eng');

		}

		// Remove closed captioning. There are only 367 cartoon episodes that have CC and *not* vobsub
		// TMNT '87 (208), TMNT 2012 (119), Droopy, and Scooby-Doo Show
		// It adds an extra step to encoding because they have to be extracted first.
		// Another reason they are being removed is that ffmpeg garbles them, they do not play
		// at the correct index time.
		// See 'view_episode_eng_subs' database view
		// https://trac.ffmpeg.org/wiki/HowToExtractAndRemoveClosedCaptions
		if($tracks_model->has_closed_captioning())
			$ffmpeg->add_argument('bsf:v', 'filter_units=remove_types=39');

	}

	// Change matroska muxer to write to header faster and avoid muxing errors (possibly) by
	// making sure they are all interleaved correctly
	$ffmpeg->add_metadata('max_interleave_delta', '0');

	if($prefix)
		$filename = $prefix.$filename;

	if($opt_experimental)
		$filename = "alpha-$filename";

	$str_metadata = "encoder_settings=ffmpeg=$ffmpeg_version";
	$str_metadata .= ",ivtc=".intval($ivtc_video);
	$str_metadata .= ",deint=$video_deint";
	if($crop_video)
		$str_metadata .= ",crop=$vf_crop";
	else
		$str_metadata .= ",crop=no";
	if($use_pipe)
		$str_metadata .= ',use_pipe';
	if($remux_video)
		$str_metadata .= ',remux';
	$ffmpeg->add_argument('metadata', $str_metadata);

	if($opt_qa)
		$ffmpeg->add_argument('t', '30');

	$ffmpeg->add_argument('metadata:s', 'language=eng');

	$ffmpeg->output_filename($filename);

	$ffmpeg->set_encoder($dvd_encoder);

	$ffmpeg->use_pipe($use_pipe);

	$ffmpeg->remux_video($remux_video);

	if($use_pipe) {

		$ffmpeg->input_filename('-');

		$dvd_copy = new DVDCopy();

		$dvd_copy->input_filename($device);
		$dvd_copy->output_filename('-');
		$dvd_copy->input_track($tracks_model->ix);

		$dvd_copy_command = $dvd_copy->get_executable_string();

		$dvd_copy_command .= ' 2> /dev/null';

	}

	// Might need this, disabling for now
	// $ffmpeg->add_argument('max_interleave_delta', '0');

	$ffmpeg_command = $ffmpeg->get_executable_string();

	if($use_pipe)
		$ffmpeg_command = "$dvd_copy_command | $ffmpeg_command";

	if($opt_log_progress)
		$ffmpeg_command .= " -progress /tmp/$episode_id.txt";

	if($opt_time)
		$ffmpeg_command = "tout $ffmpeg_command";

	if(!$opt_encode && $opt_encode_info)
		fprintf(STDERR, "# ".escapeshellarg($filename)."\n");
	if($verbose || $opt_encode_info)
		echo "$ffmpeg_command\n";

	require 'dart.encode_episode.php';

}
