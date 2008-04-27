<?

	function ask($string, $default = false) {
		if(is_string($string)) {
			fwrite(STDOUT, "$string ");
			$input = fread(STDIN, 255);
			#fclose($handle);
			
			if($input == "\n") {
				return $default;
			}
			else {
				$input = trim($input);
				return $input;
			}
		}
	}
	
	function msg($string = '', $stderr = false, $debug = false) {
		
		if($debug === true) {
			$string = "[Debug] $string";
		}
	
		if(!empty($string)) {
			if($stderr === true) {
				fwrite(STDERR, "$string\n");
			}
			else {
				fwrite(STDOUT, "$string\n");
			}
		} else {
			echo "\n";
		}
		return true;
	}
	
	require_once 'inc.mythtv.php';
	
	msg("[MythVideo Admin]");
	
	while(1) {
	
		msg(" 1. Update video database");
		msg(" 2. Update episode covers");
		msg(" 0. Quit");
		
		$select = ask("Which action would you like to perform?", 2);
		
		switch($select) {
			case 1:
			
				$arr_files = $arr_db = $arr_insert = $arr_delete = array();
				foreach(glob('/var/media/dvds/*', GLOB_MARK) as $dir) {
					$arr_files = array_merge($arr_files, glob($dir.'*.mkv'));
				}
				
				// Cleanup dead entries
				$sql = "SELECT intid, filename FROM videometadata WHERE filename LIKE ('%.mkv') ORDER BY filename;";
				$arr_db = $db->getAssoc($sql);
				
				$arr_insert = array_diff($arr_files, $arr_db);
				$arr_delete = array_diff($arr_db, $arr_files);
				
				if(count($arr_insert)) {
					$sth = $db->prepare("INSERT INTO videometadata (title,director,plot,rating,year,userrating,length,filename,showlevel,coverfile,inetref,browse) VALUES (?, '', '', 'NR', 1895, 0, 0, ?, 1, ?, '00000000', 1);");
					
					foreach($arr_insert as $filename) {
					
						// Check for existing cover
						
						$coverfile = '';
						
						$poster_dir = basename(dirname($filename));
						
						$tmp1 = "/var/media/posters/".$poster_dir."/".basename($filename).".jpg";
						$tmp2 = "/var/media/posters/".$poster_dir."/".basename($filename, ".mkv").".jpg";
						
						if(file_exists($tmp))
							$coverfile = $tmp;
						elseif(file_exists($tmp2))
							$coverfile = $tmp2;
							
						#print_r($coverfile); die;
					
						// Clean title for display
						$title = basename($filename, '.mkv');
						$title = str_replace('_', ' ', $title);
						$title = str_replace('.', ' ', $title);
						msg($title);
						$db->execute($sth, array($title, $filename, $coverfile));
					}
				}
				
				msg("Inserted ".count($arr_insert)." videos into database.");
				
				if(count($arr_delete)) {
					foreach($arr_delete as $id => $filename) {
						msg("Deleting \"$filename\" from database");
						$sql = "DELETE FROM videometadata WHERE intid = $id;";
						$db->query($sql);
					}
				}
				
				msg("Deleted ".count($arr_delete)." videos from database.");
				
				break;
				
			case 2:
				
				$count = 0;
				$sql = "SELECT intid, filename, coverfile FROM videometadata WHERE coverfile != 'No Cover' AND coverfile != '' ORDER BY coverfile;";
				$arr = $db->getAssoc($sql);
				
				#print_r($arr); die;
				$arr_dirs = array();
				foreach($arr as $id => $tmp) {
				
					if(!file_exists($tmp['filename'])) {
						$sql = "UPDATE videometadata SET coverfile = '' WHERE intid = $id;";
						$db->query($sql);
						$count++;
					}
				}
				
				msg("Deleted $count cover files from the database");
				
				$arr_dirs = glob('/var/media/dvds/*', GLOB_ONLYDIR);
				$arr_dirs = preg_replace('/\/var\/media\/dvds\//', '', $arr_dirs);
				
				#print_r($arr_dirs);
				
				// Find directories that are missing files
				$arr_update_dirs = array();
				foreach($arr_dirs as $dir) {
				
					if(!is_dir("/var/media/posters/".basename($dir)))
						mkdir("/var/media/posters/".basename($dir));
				
					$count_videos = count(glob("/var/media/dvds/$dir/*.mkv"));
					$count_posters = count(glob("/var/media/posters/$dir/*.jpg"));
					
					if($count_posters < $count_videos) {
						$arr_update_dirs[] = $dir;
					}
				}
				
				for($x = 1; $x < count($arr_update_dirs) + 1; $x++) {
					msg("\t$x. ".$arr_update_dirs[($x-1)]);
				}
				
				$ask = ask("Which series do you want to update?", 1);
				
				$ask = abs(intval($ask));
				
				if($ask == 0 || !$arr_update_dirs[$ask])  {
					continue 2;
				}
				
				$dir = $arr_update_dirs[$ask];
				
				$arr_videos = glob("/var/media/dvds/$dir/*.mkv");
				
				$arr_update_videos = array();
				
				foreach($arr_videos as $filename) {
					$tmp1 = "/var/media/posters/".$dir."/".basename($filename).".jpg";
					$tmp2 = "/var/media/posters/".$dir."/".basename($filename, ".mkv").".jpg";
					
					msg($tmp1);
					msg($tmp2);
					
					if(!(file_exists($tmp1) || file_exists($tmp2))) {
						$arr_update_videos[] = $filename;
					}
				}
				
				if(count($arr_update_videos)) {
				
					chdir("/var/media/dvds/$dir");
				
					foreach($arr_update_videos as $filename) {
				
						$exec = escapeshellcmd("mplayer -quiet -vf softskip,pullup,screenshot -lircconf /home/steve/.mplayer/myth-snapshot -input conf=/home/steve/.mplayer/snapshot.conf").addslashes($filename);
						
						exec($exec);
						$arr = glob('shot*.png');
						
						print_r($arr); die;
						
						if(count($arr)) {
							$img = end($arr);
							
							$jpg = addslashes(basename($filename, '.mkv')).".jpg";
					
							$coverfile = "/var/media/posters/$dir/$jpg";
							$exec = "convert -resize 360x $img $coverfile";
							
							msg($exec);
							
							unlink($img);
					
							$filename = getcwd().'/'.$slave_filename;
					
							$filename = mysql_escape_string($filename);
							$coverfile = mysql_escape_string($coverfile);
					
							#$sql = "UPDATE videometadata SET coverfile = '$coverfile' WHERE filename = '$filename';";
							
							#$mysql->query($sql);
						}
					
					}
				
				}
				
				print_r($arr_update_videos);
				
				
				
				
				break;
				
			default:
				die;
		}
	}
	
?>