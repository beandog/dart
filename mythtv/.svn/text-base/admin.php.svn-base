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
	
	/**
	 * Return the filename that the poster should be
	 *
	 * @param string movie filename
	 * @return poster filename
	 */
	function posterFile($filename) {
		
		$filename = trim($filename);
	
		if(empty($filename))
			return $filename;
			
		$dirname = dirname($filename);
		$dirname = str_replace('/var/media/dvds', '/var/media/posters', $dirname);
			
		$jpg = $dirname.'/'.basename($filename, '.mkv').'.jpg';
		
		return $jpg;
	}
	
	require_once 'inc.mythtv.php';
	
	
	while(1) {
	
		#system('clear');
		msg("[MythVideo Admin]");
		msg(" 1. Cleanup video database of media files");
		msg(" 2. Create episode covers");
		msg(" 3. Check for deleted / recently added episode covers");
		msg(" 4. Generate series playlists, links");
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

				$arr_dirs = glob('/var/media/dvds/*', GLOB_ONLYDIR);
				$arr_dirs = preg_replace('/\/var\/media\/dvds\//', '', $arr_dirs);
				
				#print_r($arr_dirs);
				
				// Find directories that are missing files
				$arr_update_dirs = array();
				$x = 1;
				foreach($arr_dirs as $dir) {
				
					if(!is_dir("/var/media/posters/".basename($dir)))
						mkdir("/var/media/posters/".basename($dir));
				
					$count_videos = count(glob("/var/media/dvds/$dir/*.mkv"));
					$count_posters = count(glob("/var/media/posters/$dir/*.jpg"));
					
					if($count_posters < $count_videos) {
						$arr_update_dirs[$x] = $dir;
						$x++;
					}
				}

				foreach($arr_update_dirs as $key => $value) {
					msg("\t$key. $value");
				}
				msg("\t0. Return to main menu");
				
				$ask = ask("Which series do you want to update?", 2);
				
				$ask = abs(intval($ask));

				print_r($arr_update_dirs);
				
				if($ask == 0 || !$arr_update_dirs[$ask])  {
					continue 2;
				}
				
				$dir = $arr_update_dirs[$ask];
				
				$arr_videos = glob("/var/media/dvds/$dir/*.mkv");
				
				#print_r($arr_videos);
				
				$arr_update_videos = array();
				
				foreach($arr_videos as $filename) {
					$jpg = posterFile($filename);
					if(!file_exists($jpg))
						$arr_update_videos[] = $filename;
				}
				
				#print_r($arr_update_videos); die;
				
				if(count($arr_update_videos)) {
				
					chdir("/var/media/dvds/$dir");
					
					#msg(getcwd());
				
					foreach($arr_update_videos as $filename) {
				
						$exec = escapeshellcmd("mplayer -fs -really-quiet -quiet -vf softskip,pullup,screenshot -lircconf /home/steve/.mplayer/admin.lircrc -input conf=/home/steve/.mplayer/admin.conf ").addslashes($filename);

						if(file_exists('seconds.txt')) {
							$seconds = trim(file_get_contents('seconds.txt'));
							$exec .= " -ss $seconds";
						}
						
						$exec .= " 2> /dev/null";
						
						#msg($exec); die;
						
						exec($exec, $tmp);
						$arr = glob('shot*.png');

						if(count($arr)) {
							$img = end($arr);
							
							$coverfile = posterFile($filename);
							
							$jpg = addslashes($coverfile);
					
							$exec = "convert -resize 360x $img $jpg";
							exec($exec);	
							unlink($img);
					
							$coverfile = mysql_escape_string($coverfile);
							$filename = mysql_escape_string($filename);
							$sql = "UPDATE videometadata SET coverfile = '$coverfile' WHERE filename = '$filename';";
							#msg($sql); die;

							$db->query($sql);
							
						}
					
					}
				
				}
				
				break;

			case 3:

				$count = 0;
				$sql = "SELECT intid, filename, coverfile FROM videometadata WHERE coverfile != 'No Cover' AND coverfile != '' ORDER BY coverfile;";
				$arr = $db->getAssoc($sql);
				
				// Check for invalid / deleted cover files
				
				#print_r($arr); die;
				$arr_dirs = array();
				foreach($arr as $id => $tmp) {
				
					if(!file_exists($tmp['coverfile'])) {
						$sql = "UPDATE videometadata SET coverfile = '' WHERE intid = $id;";
						$db->query($sql);
						$count++;
					}
				}
				
				msg("Deleted $count cover files from the database");
				
				// Check for new cover files
				
				$arr = array();
				$count = 0;
				
				$sql = "SELECT intid, filename FROM videometadata WHERE (coverfile = 'No Cover' OR coverfile = '') ORDER BY filename;";
				$arr = $db->getAssoc($sql);
				
				foreach($arr as $id => $filename) {
				
					$filename = str_replace('/var/media/dvds/', '/var/media/posters/', $filename).'.jpg';
					
					#msg($filename);
					
					if(!file_exists($filename)) {
						$filename = str_replace(".mkv.jpg", ".jpg", $filename);
					}
					
					if(file_exists($filename)) {
						$filename = mysql_escape_string($filename);
						#msg($filename);
						$sql = "UPDATE videometadata SET coverfile = '$filename' WHERE intid = $id;";
						$db->query($sql);
						$count++;
					}
					
					$sql = "UPDATE videometadata SET coverfile = '$filename' WHERE intid = $id;";
				
				}
				
				msg("Added $count cover files into the database");

				break;
				
			case 4:
			
				$sql = "DELETE FROM videometadata WHERE filename LIKE '/var/media/mythvideo/%.sh';";
				$db->query($sql);
				
				$arr_dirs = glob('/var/media/dvds/*', GLOB_ONLYDIR);
				$arr_dirs = preg_replace('/\/var\/media\/dvds\//', '', $arr_dirs);
				
// 				print_r($arr_dirs);

				$count = 0;
				
				$sth = $db->prepare("INSERT INTO videometadata (title,director,plot,rating,year,userrating,length,filename,showlevel,coverfile,inetref,browse) VALUES (?, '', '', 'NR', 1895, 0, 0, ?, 1, ?, '00000000', 1);");
				
				foreach($arr_dirs as $x) {
				
					$sh = "/var/media/mythvideo/${x}.sh";
					if(file_exists($sh))
						unlink($sh);
				
					$glob = "/var/media/dvds/${x}/*.mkv";
					$tmp = glob($glob);
					
				
					if(count($tmp)) {
						$exec = "ln -s /var/media/bin/mkpls $sh";
						$exec = escapeshellcmd($exec);
// 						echo $exec."\n";
						exec($exec);
						$count++;
						
						// Insert new entry
						// Clean title for display
						$title = str_replace('_', ' ', $x);
						
						$coverfile = "/var/media/dvds/${x}/folder.jpg";
						if(!file_exists($coverfile))
							$coverfile = '';
						
// 						$db->execute($sth, array('Foo', $x, ''));
						
						$sql = "INSERT INTO videometadata (title, director, plot, rating, year, userrating, length, filename, showlevel, coverfile, inetref, browse) VALUES ('".mysql_escape_string($title)."', '', '', 'NR', 1895, 0, 0, '".mysql_escape_string($sh)."', 1, '".mysql_escape_string($coverfile)."', '00000000', 1);";
// 						print_r($sql);
						$db->query($sql);
					}
				}
				
				msg("Created $count new playlists");
				
			
				break;
				
			default:
				die;
		}
	}
	
?>
