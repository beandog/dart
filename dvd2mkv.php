#!/usr/bin/php
<?
	
	require_once 'class.dvd.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.dvdvob.php';
	require_once 'class.matroska.php';
	require_once 'class.shell.php';
	require_once 'class.mediainfo.php';
	
	$args = shell::parseArguments();
	
	if($args['h'] || $args['help']) {
	
		shell::msg("Options:");
		shell::msg("  --title <title>\tMovie title");
		shell::msg("  --track <track>\tSpecify track number to rip");
		shell::msg("  --vobsub\t\tRip DVD subtitles");
		shell::msg("  --cc\t\t\tRip Closed Captioning");
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
	
	$config_dir = getenv('HOME')."/.dvd2mkv/";
	$lock_file = $config_dir."lock";
	
	// Locking functions
	function lock() {
		global $lock_file;
		touch($lock_file);
	}
	
	function unlock() {
		global $lock_file;
		if(isLocked())
			unlink($lock_file);
	}
	
	function isLocked() {
		global $lock_file;
		return file_exists($lock_file);
	}
	
	function beep() {
		shell::cmd("aplay /home/steve/beep.wav");
	}
	
	$rip_subs = $rip_cc = $mux_subs = $mux_cc = false;
	
	/** Get the configuration options */
	$config = $config_dir."config";
	if(file_exists($config)) {
		$arr_config = parse_ini_file($config);
	} else {
		trigger_error("No config file found, using defaults", E_USER_WARNING);
		$arr_config = array();
	}
	
	// Subtitle options
	// Command line args override home config
	
	// Closed Captioning
	if($arr_config['rip_cc'] || $arr_config['mux_cc'] || $args['cc'])
		$rip_cc = true;
	if($args['cc'] || $arr_config['mux_cc'])
		$mux_cc = true;
	if($args['nocc'])
		$rip_cc = $mux_cc = false;
	$min_cc_filesize = 15;
	
	// DVD Subs (VobSubs)
	if($arr_config['rip_subs'] || $arr_config['mux_subs'] || $args['subs'])
		$rip_subs = true;
	if($args['subs'] || $arr_config['mux_subs'])
		$mux_subs = true;
	if($args['nosubs'])
		$rip_subs = $mux_subs = false;
	
	if($args['debug'] || $arr_config['debug'])
		$verbose = $debug = true;
	if($args['v'] || $args['verbose'])
		$verbose = true;
	if($args['force'])
		$force = true;
	
	if($arr_config['eject'] || $args['eject'])
		$eject = true;
	if($args['noeject'])
		$eject = false;
		
	if($args['lock'])
		lock();
	if($args['unlock'])
		unlock();
	
	if(isLocked()) {
		shell::msg("Waiting for lock to be removed ...");
		while(isLocked()) {
			sleep(2);
		}
	}
	
	lock();
		
// 	$arr_all_args = array(
// 		'Ripping Options' => array(
// 			'title' => 'Movie title',
// 			'track' => 'DVD track number',
// 			'sub' => 'Rip VobSub subtitles',
// 			'cc' => 'Rip Closed Captioning subtitles',
// 			'v' => 'Verbose output',
// 			'debug' => 'Debugging output',
// 		),
// 		
// 		'Dumping options' => array(
// 			'vob' => 'Dump Audio+Video (VOB)',
// 			'vobsub' => 'Rip VobSub subtitles (IDX, SUB)',
// 			'srt' => 'Rip Closed Captioning subtitles (SRT)',
// 			'chapters' => 'Dump chapters (TXT)',
// 		),
// 	);
 		
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
		shell::msg("[Video] Length: ".$dvdtrack->secondsToHMS($dvdtrack->getLength()));
		shell::msg("[Subtitles] VobSub: ".($dvdtrack->hasSubtitles() ? "Yes" : "No"));
		shell::msg("[Audio] Track: ".$dvdtrack->getAudioAID());
		shell::msg("[Audio] Format: ".$dvdtrack->getAudioFormat());
		shell::msg("[Audio] Channels: ".$dvdtrack->getAudio());
		
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
		if(!file_exists($idx) && $dvdtrack->hasSubtitles() && $rip_subs) {
			shell::msg("[Subtitles] Ripping VobSub");
			$dvdtrack->dumpSubtitles();
		}
		
		// Eject
		if(!$debug && $eject) {
			$dvd->eject();
		}
		
		$dvdvob = new DVDVOB($vob);
		$dvdvob->setAID($dvdtrack->getAudioAID());
		
		$mediainfo = new MediaInfo($vob);
		
		// SRT Subtitles
		if(!file_exists($srt) && $rip_cc) {
			if($mediainfo->hasCC())
				shell::msg("[Subtitles] Ripping Closed Captioning");
			else
				shell::msg("[Subtitles] Checking for Closed Captioning");
			$dvdvob->dumpSRT();
			
			if(!$mediainfo->hasCC() && file_exists($srt)) {
				if(filesize($srt) > $min_cc_filesize)
					shell::msg("[Subtitles] Found Closed Captioning stream");
				else
					shell::msg("[Subtitles] No Closed Captioning available");
			} 
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
		
		if(file_exists($idx) && $mux_subs)
			$matroska->addSubtitles($idx);
		// ccextractor will dump an empty .srt output file
		// if there are no subtitles
		if(file_exists($srt) && filesize($srt) > $min_cc_filesize && $mux_cc)
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
		if($mux_subs) {
			file_exists($idx) && unlink($idx);
			file_exists($sub) && unlink($sub);
		}
		if($mux_cc)
			file_exists($srt) && unlink($srt);
	}
	
	unlock();
	
?>