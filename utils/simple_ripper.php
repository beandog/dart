<?php
	// A very simple DVD ripper
	// Grabs the longest track from a DVD and encodes it to an MP4 file
	// using the default parameters for Handbrake, adding options
	// like decombing, autocrop and chapters.
	
	$format = 'mp4';
	$extension = 'mp4';
	$video_quality = 18;
	$audio_bitrate = 196;
	$audio_encoder = 'faac';
	$handbrake_preset = 'Universal';
	$dump_iso = true;
	
	require_once '/home/steve/git/bend/class.dvd.php';
	require_once '/home/steve/git/bend/class.handbrake.php';

	$device = '/dev/dvd';

	$dvd = new DVD($device);
	$dvd->close_tray();
	$dvd->load_css();

	$title = $dvd->getTitle();
	$track = $dvd->getLongestTrack();
	$iso = "$title.iso";
	$filename = "$title.$extension";

	if($dump_iso) {
		$dvd->dump_iso($iso);
		$handbrake = new Handbrake($iso);
	} else {
		$handbrake = new Handbrake($device);
	}

	$handbrake->verbose(true);
	$handbrake->output_filename($filename);
	$handbrake->input_track($track);
	$handbrake->output_format($format);
	$handbrake->add_chapters();
	$handbrake->set_video_quality($video_quality);
	$handbrake->add_audio_encoder($audio_encoder);
	$handbrake->autocrop();
	$handbrake->decomb();
	$handbrake->set_preset($handbrake_preset);

	echo $handbrake->get_executable_string();
