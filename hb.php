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
	$parser->addOption('last_chapter', array(
		'short_name' => '-l',
		'long_name' => '--last-chapter',
		'description' => 'Final chapter',
		'action' => 'StoreInt',
		'default' => 0,
	));
	$parser->addOption('video_bitrate', array(
		'short_name' => '-b',
		'long_name' => '--vb',
		'description' => 'Video Bitrate (kbps)',
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
	$parser->addOption('audio_bitrate', array(
		'short_name' => '-B',
		'long_name' => '--ab',
		'description' => 'Audio Bitrate (kbps)',
		'action' => 'StoreInt',
		'default' => 96,
	));
	$parser->addOption('video_quality', array(
		'short_name' => '-q',
		'long_name' => '--video-quality',
		'description' => 'Video Quality (CRF)',
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
		'short_name' => '-t',
		'long_name' => '--x264-tune',
		'description' => 'x264 tune',
		'action' => 'StoreString',
		'default' => 'film',
	));
	$parser->addOption('two_pass', array(
		'short_name' => '-w',
		'long_name' => '--two-pass',
		'description' => 'Two-pass encode',
		'action' => 'StoreTrue',
		'default' => false,
	));
	$parser->addOption('two_pass_turbo', array(
		'short_name' => '-T',
		'long_name' => '--turbo',
		'description' => 'Two-pass turbo encode',
		'action' => 'StoreTrue',
		'default' => false,
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
		'short_name' => '-g',
		'long_name' => '--grain',
		'description' => 'Grain tuning',
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
	$last_chapter = abs(intval($last_chapter));
	$video_bitrate = abs(intval($video_bitrate));
	$video_quality = abs(intval($video_quality));
	$audio_bitrate = abs(intval($audio_bitrate));
	$audio_encoder = trim($audio_encoder);
	$verbose = abs(intval($verbose));

	// Defaults
	$add_chapters = true;
	if(!$audio_encoder)
		$audio_encoder = 'fdk_aac';
	$deinterlace = false;
	$decomb = true;
	$detelecine = true;
	$h264_profile = 'high';
	$h264_level = '3.1';
	if($film)
		$x264_tune = 'film';
	if($animation)
		$x264_tune = 'animation';
	if($grain)
		$x264_tune = 'grain';
	$output_format = 'mkv';
	$http_optimize = true;
	$video_encoder = 'x264';
	$autocrop = true;

	require_once 'class.handbrake.php';

	$hb = new Handbrake();

	$arr_fn = array();
	$arr_fn[] = $video_encoder;
	if($video_encoder == 'x264') {
		$arr_fn[] = $x264_preset;
		$arr_fn[] = $x264_tune;
	}
	if($video_bitrate)
		$arr_fn[] = $video_bitrate."k";
	if($two_pass) {
		$arr_fn[] = "2pass";
		if($two_pass_turbo)
			$arr_fn[] = "turbo";
	}
	if($video_encoder == 'x264') {
		$arr_fn[] = $h264_profile;
		$arr_fn[] = $h264_level;
	}
	if($grayscale)
		$arr_fn[] = 'grayscale';
	if($audio_encoder)
		$arr_fn[] = $audio_encoder;
	if($audio_bitrate)
		$arr_fn[] = $audio_bitrate."k";
	if($last_chapter)
		$arr_fn[] = "chap-1-$last_chapter";

	if(is_null($output_filename))
		$output_filename = implode($arr_fn, '-').".mkv";

	$hb->verbose($verbose);
	$hb->input_filename($input_filename);
	if($input_track)
		$hb->input_track($input_track);
	$hb->output_filename($output_filename);
	$hb->output_format($output_format);
	$hb->add_chapters($add_chapters);
	if($last_chapter)
		$hb->set_chapters(1, $last_chapter);
	if($video_bitrate)
		$hb->set_video_bitrate($video_bitrate);
	$hb->set_video_encoder($video_encoder);
	if($video_quality)
		$hb->set_video_quality($video_quality);
	$hb->set_two_pass($two_pass);
	$hb->set_two_pass_turbo($two_pass_turbo);
	$hb->add_audio_encoder($audio_encoder);
	$hb->autocrop($autocrop);
	$hb->decomb($decomb);
	$hb->detelecine($detelecine);
	$hb->deinterlace($deinterlace);
	$hb->grayscale($grayscale);
	$hb->set_h264_profile($h264_profile);
	$hb->set_h264_level($h264_level);
	$hb->set_x264_preset($x264_preset);
	if($x264_tune)
		$hb->set_x264_tune($x264_tune);
	$hb->set_http_optimize($http_optimize);

	$d_video_quality = $video_quality;
	if(!$video_quality)
		$d_video_quality = "(default)";
	$d_video_bitrate = "$video_bitrate kbps";
	if(!$video_bitrate)
		$d_video_bitrate = "(default)";
	$d_input_track = $input_track;
	if(!$input_track)
		$d_input_track = "(default)";
	$d_audio_bitrate = "$audio_bitrate kbps";
	if(!$audio_bitrate)
		$d_audio_bitrate = "(default)";
	$d_audio_encoder = $audio_encoder;
	if(!$audio_encoder)
		$d_audio_encoder = "(default)";

	echo "// General //\n";
	echo "* Source: $input_filename\n";
	echo "* Target: $output_filename\n";
	echo "* Track: $d_input_track\n";
	echo "// Video //\n";
	echo "* Quality: $d_video_quality\n";
	echo "* Bitrate: $d_video_bitrate\n";
	echo "* Deinterlace: ".intval($deinterlace)."\n";
	echo "* Decomb: ".intval($decomb)."\n";
	echo "* Detelecine: ".intval($detelecine)."\n";
	echo "* Grayscale: ".intval($grayscale)."\n";
	echo "* Animation: ".intval($animation)."\n";
	echo "* Autocrop: ".intval($autocrop)."\n";
	if($video_encoder == 'x264') {
		echo "* H.264 profile: $h264_profile\n";
		echo "* H.264 level: $h264_level\n";
		echo "* x264 preset: $x264_preset\n";
		echo "* x264 tune: $x264_tune\n";
	}
	echo "// Audio //\n";
	echo "* Encoder: $audio_encoder\n";
	echo "* Bitrate: $d_audio_bitrate\n";

	$command = $hb->get_executable_string();

	if($input_filename && !$dry_run)
		$hb->encode();
