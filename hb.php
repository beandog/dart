#!/usr/bin/php
<?php
	require_once 'Console/CommandLine.php';

	$parser = new Console_CommandLine();
	$parser->description = "Handbrake Simpler";
	$parser->addArgument('input_filename', array(
		'optional' => true,
	));
	$parser->addOption('output_filename', array(
		'short_name' => '-o',
		'long_name' => '--output',
		'description' => 'Output to filename',
		'action' => 'StoreString',
		'default' => null,
	));
	$parser->addOption('dry_run', array(
		'short_name' => '-n',
		'long_name' => '--dry-run',
		'description' => 'Dry run',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('input_track', array(
		'short_name' => '-t',
		'long_name' => '--track',
		'description' => 'DVD track',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('first_chapter', array(
		'short_name' => '-c',
		'long_name' => '--first-chapter',
		'description' => 'First chapter',
		'action' => 'StoreInt',
		'default' => 1,
	));
	$parser->addOption('last_chapter', array(
		'short_name' => '-C',
		'long_name' => '--last-chapter',
		'description' => 'Final chapter',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('video_quality', array(
		'short_name' => '-q',
		'long_name' => '--video-quality',
		'description' => 'Video Quality (CRF)',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('audio_encoder', array(
		'short_name' => '-E',
		'long_name' => '--ae',
		'description' => 'Audio encoder',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('audio_fallback', array(
		'short_name' => '-F',
		'long_name' => '--af',
		'description' => 'Audio fallback',
		'action' => 'StoreString',
		'default' => '',
	));
	$parser->addOption('audio_bitrate', array(
		'short_name' => '-B',
		'long_name' => '--ab',
		'description' => 'Audio Bitrate (kbps)',
		'action' => 'StoreInt',
		'default' => 96,
	));
	$parser->addOption('subtitles', array(
		'short_name' => '-s',
		'long_name' => '--subtitles',
		'description' => 'Subtitles',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('subtitle_track', array(
		'short_name' => '-S',
		'long_name' => '--st',
		'description' => 'Subtitle track',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('x264_preset', array(
		'short_name' => '-p',
		'long_name' => '--x264-preset',
		'description' => 'x264 preset',
		'action' => 'StoreString',
		'default' => 'medium',
	));
	$parser->addOption('x264_tune', array(
		'short_name' => '-u',
		'long_name' => '--x264-tune',
		'description' => 'x264 tune',
		'action' => 'StoreString',
		'default' => 'film',
	));
	$parser->addOption('grayscale', array(
		'short_name' => '-g',
		'long_name' => '--grayscale',
		'description' => 'Grayscale video',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('animation', array(
		'short_name' => '-a',
		'long_name' => '--animation',
		'description' => 'Animated video',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('film', array(
		'short_name' => '-f',
		'long_name' => '--film',
		'description' => 'Film tuning',
		'action' => 'StoreTrue',
		'default' => true,
	));
	$parser->addOption('grain', array(
		'short_name' => '-r',
		'long_name' => '--grain',
		'description' => 'Grain tuning',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('overwrite', array(
		'short_name' => '-x',
		'long_name' => '--overwrite',
		'description' => 'Overwrite existing file',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('verbose', array(
		'short_name' => '-v',
		'long_name' => '--verbose',
		'description' => 'Be verbose',
		'action' => 'Counter',
		'default' => 0,
	));
	$result = $parser->parse();
	extract($result->args);
	extract($result->options);

	function d_yes_no($var) {
		if($var)
			return "yes";
		else
			return "no";
	}

	// Handle options, defaults

	if(is_null($input_filename))
		$input_filename = '/dev/dvd';
	else {
		if(!file_exists($input_filename))
			die("* Can't open $input_filename\n");
	}
	$input_track = abs(intval($input_track));
	$first_chapter = abs(intval($first_chapter));
	$last_chapter = abs(intval($last_chapter));
	$video_quality = abs(intval($video_quality));
	$audio_bitrate = abs(intval($audio_bitrate));
	$audio_encoder = trim($audio_encoder);
	$subtitle_track = abs(intval($subtitle_track));
	$verbose = abs(intval($verbose));

	// Defaults
	$add_chapters = true;
	if(!$audio_encoder)
		$audio_encoder = 'fdk_aac';
	$deinterlace = false;
	$decomb = true;
	$detelecine = true;
	$h264_profile = 'high';
	if($film)
		$x264_tune = 'film';
	if($animation)
		$x264_tune = 'animation';
	if($grain)
		$x264_tune = 'grain';
	$video_encoder = 'x264';

	require_once 'class.handbrake.php';

	$hb = new Handbrake();

	$arr_fn = array();
	$arr_fn[] = $video_encoder;
	if($video_encoder == 'x264') {
		$arr_fn[] = $x264_preset;
		$arr_fn[] = $x264_tune;
	}
	if($video_quality)
		$arr_fn[] = $video_quality."q";
	if($video_encoder == 'x264') {
		$arr_fn[] = $h264_profile;
	}
	if($grayscale)
		$arr_fn[] = 'grayscale';
	if($audio_encoder)
		$arr_fn[] = $audio_encoder;
	if($audio_bitrate && $audio_encoder != 'copy')
		$arr_fn[] = $audio_bitrate."k";
	if($audio_fallback && ($audio_fallback != $audio_encoder))
		$arr_fn[] = "fallback-$audio_fallback";
	if($last_chapter)
		$arr_fn[] = "chap-$first_chapter-$last_chapter";
	elseif($first_chapter)
		$arr_fn[] = "chap-$first_chapter-final";

	if(is_null($output_filename))
		$output_filename = implode($arr_fn, '-').".mp4";

	// Minimum filesize, 1 MB
	$min_filesize = 1048576;

	// Remove old file if it's not larger than 1 MBs
	if(file_exists($output_filename) && filesize($output_filename) < $min_filesize) {
		unlink($output_filename);
	}

	if(file_exists($output_filename) && !$overwrite) {
		echo "Output file $output_filename exists, and overwrite is not enabled.  Quitting.\n";
		exit(1);
	}

	$hb->verbose($verbose);
	$hb->input_filename($input_filename);
	if($input_track)
		$hb->input_track($input_track);
	$scan = $hb->scan();
	if(!$scan) {
		echo "* Scanning $input_track FAILED\n";
		exit(1);
	}
	$has_closed_captioning = $hb->closed_captioning;
	$hb->output_filename($output_filename);
	$hb->add_chapters($add_chapters);
	if($first_chapter || $last_chapter)
		$hb->set_chapters($first_chapter, $last_chapter);
	$hb->set_video_encoder($video_encoder);
	if($video_quality)
		$hb->set_video_quality($video_quality);
	$hb->add_audio_encoder($audio_encoder);
	if($audio_encoder != 'copy')
		$hb->set_audio_bitrate($audio_bitrate);
	if($audio_encoder != $audio_fallback)
		$hb->set_audio_fallback($audio_fallback);
	if($subtitles && $subtitle_track)
		$hb->add_subtitle_track($subtitle_track);
	elseif($subtitles && $has_closed_captioning)
		$hb->add_subtitle_track($hb->get_closed_captioning_ix);
	$hb->decomb($decomb);
	$hb->detelecine($detelecine);
	$hb->deinterlace($deinterlace);
	$hb->grayscale($grayscale);
	$hb->set_h264_profile($h264_profile);
	$hb->set_x264_preset($x264_preset);
	if($x264_tune)
		$hb->set_x264_tune($x264_tune);

	$d_video_quality = $video_quality;
	if(!$video_quality)
		$d_video_quality = "(default)";
	$d_input_track = $input_track;
	if(!$input_track)
		$d_input_track = "(default)";
	$d_audio_bitrate = "$audio_bitrate kbps";
	if(!$audio_bitrate || $audio_encoder == 'copy')
		$d_audio_bitrate = "(default)";
	$d_audio_encoder = $audio_encoder;
	if(!$audio_encoder)
		$d_audio_encoder = "(default)";
	$d_subtitle_track = $subtitle_track;
	if(!$subtitle_track)
		$d_subtitle_track = "(default)";

	echo "// General //\n";
	echo "* Source: $input_filename\n";
	echo "* Target: $output_filename\n";
	echo "* Track: $d_input_track\n";
	if($last_chapter)
		echo "* Chapters: 1-$last_chapter\n";
	echo "// Video //\n";
	echo "* Quality: $d_video_quality\n";
	echo "* Deinterlace: ".d_yes_no(intval($deinterlace))."\n";
	echo "* Decomb: ".d_yes_no(intval($decomb))."\n";
	echo "* Detelecine: ".d_yes_no(intval($detelecine))."\n";
	echo "* Grayscale: ".d_yes_no(intval($grayscale))."\n";
	echo "* Animation: ".d_yes_no(intval($animation))."\n";
	if($video_encoder == 'x264') {
		echo "* H.264 profile: $h264_profile\n";
		echo "* x264 preset: $x264_preset\n";
		echo "* x264 tune: $x264_tune\n";
	}
	echo "// Audio //\n";
	echo "* Encoder: $d_audio_encoder\n";
	if($audio_fallback)
		echo "* Fallback: $audio_fallback\n";
	echo "* Bitrate: $d_audio_bitrate\n";
	if($subtitles) {
		echo "// Subtitles //\n";
		if($subtitle_track)
			echo "* Track: $d_subtitle_track\n";
		if($subtitles && !$subtitle_track && $has_closed_captioning)
			echo "* Closed Captioning\n";
	}

	$command = $hb->get_executable_string();

	if($dry_run || $verbose)
		echo "* Handbrake command: $command\n";

	if($input_filename && !$dry_run)
		$hb->encode();
