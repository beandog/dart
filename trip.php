<?

	/**
	 * TODO
	 *
	 * Include class.shell.php
	 * Check for PHP modules
	 * Check for tidy XML support
	 * Display error messages
	 * Cleanup variable names
	 * Split title output into chunks
	 * Document code
	 * Documentation
	 * Standalone binary
	 * Use internal PHP curl libs?
	 * Handle error states on Tivo access (sync, download), tivodecode
	 *
	 */
	

	require_once 'class.shell.php';

	$config_dir = getenv("HOME")."/.trip/";
	$config = $config_dir."config";
	$tivo_html = $config_dir."index.html";
	$tivo_xhtml = $config_dir."index.xhtml";
	
	if(is_dir($config_dir) && file_exists($config))
		$arr_config = parse_ini_file($config);
	else
		mkdir($config_dir) or die("Couldn't create $config_dir to store configuration settings");
	
	$debug = $verbose = false;
	
	$args = shell::parseArguments();

	if($args['h'] || $args['help']) {
	
		shell::msg("Tivo Ripper - Download recordings from your Tivo and remove the DRM");
		shell::msg("http://spaceparanoids.org/trac/bend/wiki/trip");
		echo "\n";
		shell::msg("Usage:");
		shell::msg("  trip [options]");
		echo "\n";
		shell::msg("  Running with no options will display a list of recordings to extract.");
		echo "\n";
		shell::msg("Options:");
		shell::msg("  -s, --sync\t\tSync with Tivo to get list of recordings");
		shell::msg("  -e, --episodes\tDisplay list of recordings and episodes of TV shows");
		shell::msg("  -v, --verbose\t\tVerbose output");
		shell::msg("  --debug\t\tEnable debugging");
		echo "\n";
		shell::msg("Configuration:");
		shell::msg("  -a, --address\t\tTivo IP Address");
		shell::msg("  -m, --mak\t\tTivo Media Access Key");
	
		die;
	}
	
	if($args['mak'])
		$media_access_key= $args['mak'];
	elseif($args['m'])
		$media_access_key = $args['m'];
	elseif($arr_config['mak'])
		$media_access_key = $arr_config['mak'];
	else
		die("Neeed the Media Access Key");
	
	if($args['address'])
		$ip = $args['address'];
	elseif($args['a'])
		$ip = $args['a'];
	elseif($arr_config['ip'])
		$ip = $arr_config['ip'];
	else
		die("Need the IP address of the TiVo");

	if($args['sync'] || $args['s'])
		$sync = true;
	if($args['list'] || $args['l'])
		$display_list = true;
	if($args['episodes'] || $args['e'])
		$display_episodes = true;
	if($args['debug'])
		$verbose = $debug = true;
	if($args['verbose'] || $args['v'])
		$verbose = true;
	
	if($sync || !file_exists($tivo_html)) {
		if($verbose)
			shell::msg("Syncing with Tivo");
		$exec = "curl --digest -k -u tivo:$media_access_key -c ".tempnam('/tmp', 'tivo')." -o $tivo_html \"https://$ip/nowplaying/index.html?Recurse=Yes\"";
 		shell::cmd($exec, !$debug);
	}
	
	// FIXME Don't run tidy unless xhtml file is old.
	$tidy = "tidy -asxhtml -numeric -w 0 < \"$tivo_html\" > \"$tivo_xhtml\"";
	if($verbose)
		shell::msg("Creating XHTML index");
	shell::cmd($tidy, true, true);
	
	/** Parse HTML **/
	$str = file_get_contents($tivo_xhtml);
	
	$str = str_replace("<a href=\"", "", $str);
	$str = str_replace("mpeg\">", "mpeg", $str);
	$str = str_replace("</a>", "", $str);
	$str = str_replace("Download MPEG-PS", "", $str);

	$xml = simplexml_load_string($str);
	
	unset($str);
	
	$tr = $td = 0;
	
	$arr_recordings = array();
	
	$arr_img_status = array(
		'expired-recording.png' => 'Expired',
		'expires-soon-recording.png' => 'Expires Soon',
		'in-progress-recording.png' => 'Recording',
		'save-until-i-delete-recording.png' => 'Keep',
	);
	
	foreach($xml->body->table->tr as $element) {
	
		// Ignore the first row
 		if($tr) {
 		
 			$arr = array();
 		
			foreach($element as $tmp) {
			
				switch($td) {
					// Status
 					case 0:
 						if($tmp->img) {
 							$attrs = $tmp->img->attributes();
							$img = (string)$attrs['src'];
							$arr['status'] = $arr_img_status[basename($img)];
						}
 						break;
					
					// Channel Logo
 					case 1:
 						if($tmp->img) {
 							$attrs = $tmp->img->attributes();
							$arr['channel'] = (string)$attrs['alt'];
						}
 						break;
 					
 					// Title
 					case 2:
 						if($tmp->b) {
 							$title = (string)$tmp->b;
 							
 							if(preg_match("/^.+: \".+\"$/", $title)) {
								$str = explode(": \"", $title);
								$title = $str[0];
								unset($str[0]);
								$episode = implode(": \"", $str);
								$episode = substr($episode, 0, -1);
								$arr['title'] = $title;
								$arr['episode'] = $episode;
							} else
								$arr['title'] = $title;
 							
 						}
 						break;
 					
 					// Url
 					case 5:
 						if($tmp->i)
 							$arr['status'] = (string)$tmp->i;
 						elseif($tmp[0])
 							$arr['url'] = (string)$tmp[0];
 						break;
				}
				
				$td++;
			}
 			
			$td = 0;
			$arr_recordings[] = $arr;
			
		}
		$tr++;
	}
	
	// Break down into titles => episodes => data
	foreach($arr_recordings as $arr) {
		// Ignore them if it's a recording in process
		if(!($arr['status'] == 'Recording' || !($arr['url']))) {
			$arr_titles[$arr['title']][] = $arr;
			$str = $arr['title'];
			if($arr['episode'])
				$str .= ": ".$arr['episode'];
			$episodes[] = $str;
		}
	}
	
 	$titles = array_keys($arr_titles);
  	sort($titles, SORT_STRING);
 	sort($episodes, SORT_STRING);
 	
	if($display_list) {
		print_r($arr_titles);
		exit(0);
	} elseif($display_episodes) {
		foreach($episodes as $value)
			shell::msg("$value");
		exit(0);
	}
	
	shell::msg("Which Tivo show do you want to download?");
	$x = 1;
	foreach($titles as $value) {
		echo "\t".$x++.". $value\n";
	}
	
  	$str = shell::ask("Title(s) #:");

	if($str) {
	
		$arr_title_recordings = array();
	
		$tmp = preg_split('/\D/', $str);
		foreach($tmp as $idx) {
			$idx--;
			$arr_title_recordings = array_merge($arr_title_recordings, $arr_titles[$titles[$idx]]);
		}
		
		
		// FIXME Download individual episodes
		
		// Download all shows selected
		foreach($arr_title_recordings as $arr) {
		
			extract($arr);
		
			if($arr['episode']) {
				$save_file_to = $title."/".$arr['episode'].".tivo";
				$dirname = str_replace(" ", "_", $title);
			} else
				$save_file_to = $title.".tivo";

			$save_file_to = str_replace(" ", "_", $save_file_to);
			$mpg = preg_replace('/tivo$/', 'mpg', $save_file_to);
			
			$basename = basename($mpg);
			
			if(!file_exists($dirname) && $arr['episode'])
				mkdir($dirname) or die("Couldn't create directory $dirname");
			
			if(!file_exists($save_file_to) && !file_exists($mpg)) {
			
				if($arr['episode'])
					$display_title = "$title: \"${arr['episode']}\"";
				else
					$display_title = "\"$title\"";
			
				shell::msg("Downloading $display_title");
				if($debug) {
					shell::msg("Source: $url");
					shell::msg("Destination: $save_file_to");
				}
 				downloadFile($url, $save_file_to, $media_access_key);
			}
			
			if(!file_exists($mpg) && file_exists($save_file_to)) {
					shell::msg("Removing DRM");
					tivoDecode($save_file_to, $mpg, $tivo_media_access_key);
			}
			
		}
		
	}
	
	/**
	 * Fetch a file from the Tivo server
	 */
	function downloadFile($src, $dest, $key) {
		$src = html_entity_decode($src);
		$exec = "curl --digest -k -u tivo:$key -c ".tempnam('/tmp', 'tivo')." -o \"$dest\" \"$src\"";
  		shell::cmd($exec, false);
	}
	
	/**
	 * Decode a .TiVo file
	 */
	function tivoDecode($src, $dest, $key) {
		$exec = "tivodecode -m $key \"$src\" -o \"$dest\"";
  		shell::cmd($exec, true, true);
  		unlink($src);
	}
?>
