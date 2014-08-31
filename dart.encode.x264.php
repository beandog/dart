<?php

if($encode && $episode_id) {

	/**
	 * Handbrake
	 *
	 * Gathers data from models, the MediaEpisode and the source itself to
	 * and builds a new HandBrake object.
	 */

	/**
	 * Encoding specification requirements
	 * Current version: dlna-usb-5 working spec
	 *
	 * Handbrake 0.9.9, upstream release
	 * mkvtoolnix 6.7.0
	 * libebml 1.3.0
	 * libmatroska 1.4.1
	 * chapters
	 * decomb
	 * detelecine
	 * two-pass
	 * turbo first pass
	 * no fixed video, audio codec bitrate
	 * audio codec fdk_aac
	 * fallback audio ac3,dts copy
	 * x264 preset medium
	 * x264 tune animation, film or grain
	 * x264 optional grayscale
	 * x264 options: keyint=30
	 * H.264 profile high
	 * H.264 level 3.1
	 */

	$deinterlace = false;
	$decomb = $detelecine = $autocrop = true;
	$h264_level = "3.1";
	$h264_profile = "high";

	$handbrake = new Handbrake;
	$handbrake->verbose($verbose);
	$handbrake->debug($debug);
	$handbrake->set_dry_run($dry_run);

	/** Files **/

	$handbrake->output_format('mkv');
	$handbrake->input_filename($episode->queue_iso_symlink);
	$handbrake->input_track($episode->metadata['track_ix']);
	$handbrake->output_filename($episode->queue_handbrake_x264);

	/** Encoding **/

	$handbrake->set_video_encoder('x264');
	$handbrake->deinterlace($deinterlace);
	$handbrake->decomb($decomb);
	$handbrake->detelecine($detelecine);
	$handbrake->autocrop($autocrop);
	// $handbrake->dvdnav($dvdnav);

	/** Video **/

	$video_bitrate = $series_model->get_video_bitrate();
	$video_quality = $series_model->get_crf();
	$video_two_pass = $series_model->get_two_pass();
	$grayscale = ($series_model->grayscale == 't');
	$handbrake->grayscale($grayscale);

	if($video_bitrate)
		$handbrake->set_video_bitrate($video_bitrate);
	if($video_quality)
		$handbrake->set_video_quality($video_quality);
	if($video_two_pass) {
		$handbrake->set_two_pass(true);
		$handbrake->set_two_pass_turbo(true);
	}

	/** H.264 **/

	$handbrake->set_h264_level('3.1');
	$handbrake->set_h264_profile('high');

	/** x264 **/

	$arr_x264_opts = array();
	$series_x264opts = $series_model->get_x264opts();
	if(strlen($series_x264opts))
		$arr_x264_opts[] = $series_x264opts();
	$arr_x264_opts[] = "keyint=30";
	$arr_x264_opts[] = "vbv-bufsize=1024:vbv-maxrate=1024";
	$x264_opts = implode(":", $arr_x264_opts);
	$handbrake->set_x264opts($x264_opts);
	$x264_preset = $series_model->get_x264_preset();
	if(!$x264_preset)
		$x264_preset = 'medium';
	$x264_tune = $series_model->get_x264_tune();
	$animation = ($x264_tune == 'animation');
	$handbrake->set_x264_preset($x264_preset);
	$handbrake->set_x264_tune($x264_tune);

	/** Audio **/

	// Find the audio track to use
	$best_quality_audio_streamid = $tracks_model->get_best_quality_audio_streamid();
	$first_english_streamid = $tracks_model->get_first_english_streamid();

	// Major FIXME -- The audio stream should never be guessed at this point in
	// the encode.  A *proper* call to the database should fetch it, and then
	// set the *track number*.

	$audio_stream_id = "0x80";

	// Do a a check for a dry run here, because HandBrake scans the source directly
	// which can take some time.
	if(!$dry_run) {

		if($handbrake->dvd_has_audio_stream_id($best_quality_audio_streamid)) {
			$handbrake->add_audio_stream($best_quality_audio_streamid);
			$audio_stream_id = $best_quality_audio_streamid;
		} elseif($handbrake->dvd_has_audio_stream_id($first_english_streamid)) {
			$handbrake->add_audio_stream($first_english_streamid);
			$audio_stream_id = $first_english_streamid;
		} else {
			$handbrake->add_audio_stream("0x80");
		}

	}

	$audio_details = $tracks_model->get_audio_details($audio_stream_id);
	$display_audio_passthrough = display_audio($audio_details['format'], $audio_details['channels']);

	$audio_encoder = $series_model->get_audio_encoder();
	$audio_bitrate = $series_model->get_audio_bitrate();
	if($audio_encoder == 'aac') {
		$handbrake->add_audio_encoder('fdk_aac');
		$handbrake->set_audio_fallback('copy');
		if($audio_bitrate)
			$handbrake->set_audio_bitrate($audio_bitrate);
	} elseif($audio_encoder == 'copy') {
		$handbrake->add_audio_encoder('copy');
	} else {
		$handbrake->add_audio_encoder('copy');
	}

	/** Subtitles **/

	// Check for a subtitle track
	$subp_ix = $tracks_model->get_first_english_subp();

	// If we have a VobSub one, add it
	// Otherwise, check for a CC stream, and add that
	if($subp_ix) {
		$handbrake->add_subtitle_track($subp_ix);
		$d_subtitles = "VOBSUB";
	} elseif($handbrake->has_closed_captioning()) {
		$handbrake->add_subtitle_track($handbrake->get_closed_captioning_ix());
		$d_subtitles = "Closed Captioning";
	} else {
		$d_subtitles = "None :(";
	}

	/** Chapters **/

	$handbrake->set_chapters($episode->metadata['episode_starting_chapter'], $episode->metadata['episode_ending_chapter']);
	$handbrake->add_chapters();

	/*
	if($dumpvob) {

		$vob = "$episode_filename.vob";

		if(!file_exists($vob)) {

			$tmpfname = tempnam(dirname($episode_filename), "vob.$episode_id.");
			$dvdtrack = new DvdTrack($track_number, $iso, $debug);
			$dvdtrack->getNumAudioTracks();
			$dvdtrack->setVerbose($verbose);
			$dvdtrack->setBasename($tmpfname);
			$dvdtrack->setStartingChapter($episode_starting_chapter);
			$dvdtrack->setEndingChapter($episode_ending_chapter);
			$dvdtrack->setAudioStreamID($default_audio_streamid);
			unlink($tmpfname);
			$dvdtrack->dumpStream();

			rename("$tmpfname.vob", $vob);

		}

		$handbrake->input_filename($episode->queue_iso_symlink);

	}
	*/

	$arr_video = array();
	$arr_h264 = array();
	$arr_x264 = array();
	$arr_audio = array();

	if($autocrop)
		$arr_video[] = "autocrop";
	if($deinterlace)
		$arr_video[] = "deinterlace";
	if($decomb)
		$arr_video[] = "decomb";
	if($detelecine)
		$arr_video[] = "detelecine";
	$arr_h264[] = "profile $h264_profile";
	$arr_h264[] = "level $h264_level";
	if($video_quality)
		$arr_x264[] = "crf $video_quality";
	if($video_bitrate) {
		$str = "${video_bitrate}k";
		if($video_two_pass)
			$str .= " two pass";
		$arr_x264[] = $str;
	}
	$arr_x264[] = "$x264_preset preset";
	$arr_x264[] = "$x264_tune";
	if($grayscale)
		$arr_x264[] = "grayscale";
	if($audio_encoder == "copy")
		$arr_audio[] = $display_audio_passthrough;
	else
		$arr_audio[] = strtoupper($audio_encoder)." ${audio_bitrate}k";

	$d_video = implode(", ", $arr_video);
	$d_h264 = implode(", ", $arr_h264);
	$d_x264 = implode(", ", $arr_x264);
	$d_audio = implode(", ", $arr_audio);
	$d_preset = $series_model->get_preset_name();

	echo "Collection:\t".$episode->metadata['collection_title']."\n";
	echo "Series:\t\t".$episode->metadata['series_title']."\n";
	echo "Episode:\t".$episode->metadata['episode_title']."\n";
	echo "Source:\t\t".$episode->dvd_iso."\n";
	echo "Target:\t\t".basename($episode->episode_mkv)."\n";
	if($debug || $dry_run) {
		echo "Episode ID:\t".$episode_id."\n";
	}
	echo "Preset:\t\t$d_preset\n";
	echo "Handbrake:\t$d_video\n";
	echo "Video:\t\t$d_x264\n";
	echo "Audio:\t\t$d_audio\n";
	echo "Subtitles:\t$d_subtitles\n";

	$handbrake_command = $handbrake->get_executable_string();

}
