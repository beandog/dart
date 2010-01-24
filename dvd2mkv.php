#!/usr/bin/php
<?
	
	require_once 'class.dvd.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.dvdvob.php';
	require_once 'class.matroska.php';
	require_once 'class.shell.php';
	
	$args = shell::parseArguments();
	
	if($args['h'] || $args['help']) {
	
		shell::msg("Options:");
		shell::msg("  --title <title>\tMovie title");
		shell::msg("  --track <track>\tSpecify track number to rip");
		shell::msg("  -s, --sub\t\tRip subtitles");
		shell::msg("  -v\t\t\tVerbose output");
		echo "\n";
// 		shell::msg("Encoding:");
// 		shell::msg("  --bitrate\t\tVideo bitrate");
// 		echo "\n";
// 		shell::msg("Debugging:");
// 		shell::msg("  --force\t\tOverwrite files");
// 		shell::msg("  --debug\t\tDebug output");
	
		die;
	}
	
	$dvd =& new DVD();
	$dvd->mount();
	
	/** Get the configuration options */
	$config = getenv('HOME').'/.dvd2mkv/config';
	if(file_exists($config)) {
		$arr_config = parse_ini_file($config);
	} else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
		$arr_config = array();
	}
	
	if($args['debug'])
		$verbose = $debug = true;
	if($args['v'] || $args['verbose'])
		$verbose = true;
	if($args['force'])
		$force = true;
	if($args['nosub'])
		 $subs = false;
	else
		$subs = true;
	
	if($arr_config['eject'] || $args['eject'])
		$eject = true;
	if($args['noeject'])
		$eject = false;
		
	$arr_all_args = array(
		'Ripping Options' => array(
			'title' => 'Movie title',
			'track' => 'DVD track number',
			'sub' => 'Rip VobSub subtitles',
			'cc' => 'Rip Closed Captioning subtitles',
			'v' => 'Verbose output',
			'debug' => 'Debugging output',
		),
		
		'Dumping options' => array(
			'vob' => 'Dump Audio+Video (VOB)',
			'vobsub' => 'Rip VobSub subtitles (IDX, SUB)',
			'srt' => 'Rip Closed Captioning subtitles (SRT)',
			'chapters' => 'Dump chapters (TXT)',
		),
	);
		
	$title =& $args['title'];
	
	if(strlen($dvd->getID()) != '32')
		die("Couldn't get disc ID!\n");
		
	if($verbose) {
		shell::msg("[DVD] ID: ".$dvd->getID());
		shell::msg("[DVD] Disc Title: ".$dvd->getTitle());
	}
	
	if($args['track']) {
		$track = abs(intval($args['track']));
	} else {
		$track = $dvd->getLongestTrack();
	}
	
	$dvdtrack = new DVDTrack($track);
	
	if($debug)
		shell::msg("[DVD] Track: $track");
		
	if(empty($title)) {
		$title = ucwords(strtolower(str_replace('_', ' ', $dvd->getTitle())));
		$title = str_replace(' And ', ' and ', $title);
		$title = str_replace(' Of ', ' of ', $title);
		$title = str_replace(' The ', ' the ', $title);
		$title = shell::ask("Enter a movie title: [$title]", $title);
	} else {
		$title = $args['title'];
	}
	
	if($verbose) {
		shell::msg("[DVD] Title: $title");
	}
	
	$dvdtrack->setBasename($title);
	$file = $dvdtrack->getBasename();
// 	print_r($dvdtrack);
	
	$vob = "$file.vob";
	$mpg = "$file.mpg";
	$ac3 = "$file.ac3";
	$sub = "$file.sub";
	$idx = "$file.idx";
	$srt = "$file.srt";
	$txt = "$file.txt";
	$avi = "$file.avi";
	$mkv = "$file.mkv";

	$scandir = preg_grep('/(vob|sub|idx|txt|avi|mkv|mpg|ac3)$/', scandir('./'));
		
	if($force)
		$scandir = array();

	if(!in_array($mkv, $scandir)) {
		
		// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
		// so we use scandir and in_array instead
		shell::msg("[Video] Track number: $track");
		shell::msg("[Video] Aspect ratio: ".$dvdtrack->getAspectRatio());
		shell::msg("[Video] Length: ".$dvdtrack->getLength());
		shell::msg("[Subtitles] VobSub: ".($dvdtrack->hasSubtitles() ? "Yes" : "No"));
		shell::msg("[Audio] Track: ".$dvdtrack->getAudioAID());
		shell::msg("[Audio] Format: ".$dvdtrack->getAudioFormat());
		shell::msg("[Audio] Channels: ".$dvdtrack->getAudioChannels());
		
		// VOB
		if(!in_array($vob, $scandir)) {
			shell::msg("[DVD] Ripping VOB");
			$dvdtrack->dumpStream();
		}
			
		// Chapters
		if(!file_exists($txt)) {
			$dvdtrack->dumpChapters();
		}
		
		// VobSub Subtitles
		if(!file_exists($idx) && $dvdtrack->hasSubtitles()) {
			shell::msg("[Subtitles] Ripping VobSub");
			$dvdtrack->dumpSubtitles();
		}
		
		// Eject
		if(!$debug && $eject) {
			$dvd->eject();
		}
		
		$dvdvob = new DVDVOB($vob);
		$dvdvob->setAID($dvdtrack->getAudioAID());
		
		// SRT Subtitles
		if(!$dvdtrack->hasSubtitles() && !file_exists($srt)) {
			shell::msg("[Subtitles] Ripping SRT");
			$dvdvob->dumpSRT();
		}
		
		// Raw Video
		if(!in_array($mpg, $scandir)) {
			shell::msg("[MPG] Demuxing VOB to Raw Video");
			$dvdvob->rawvideo($mpg);
		}
		
		// Raw Audio
		if(!in_array($ac3, $scandir)) {
			shell::msg("[AC3] Demuxing VOB to Raw Audio");
			$dvdvob->rawaudio($ac3);
		}
		
		// Matroska
		shell::msg("[MKV] Creating Matroska file");
		
		$matroska = new Matroska($mkv);
		$matroska->setTitle($title);
		$matroska->setAspectRatio($dvdtrack->getAspectRatio());
		$matroska->addVideo($mpg);
		$matroska->addAudio($ac3);
		
		if(file_exists($idx))
			$matroska->addSubtitles($idx);
		// ccextractor will dump an empty .srt output file
		// if there are no subtitles
		if(file_exists($srt) && filesize($srt) > 15)
			$matroska->addSubtitles($srt);
		if(file_exists($txt))
			$matroska->addChapters($txt);
		
		$matroska->mux();
			
	}
	
	$scandir = preg_grep('/(vob|sub|idx|txt|avi|mkv|mpg|ac3)$/', scandir('./'));

	if(in_array($mkv, $scandir) && !$debug) {
		if($verbose)
			shell::msg('Deleting temporary files');
		in_array($vob, $scandir) && unlink($vob);
		in_array($mpg, $scandir) && unlink($mpg);
		in_array($ac3, $scandir) && unlink($ac3);
		in_array($avi, $scandir) && unlink($avi);
		file_exists($txt) && unlink($txt);
		file_exists($idx) && unlink($idx);
		file_exists($sub) && unlink($sub);
		file_exists($srt) && unlink($srt);
	}
	
	
?>