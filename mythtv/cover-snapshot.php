#!/usr/bin/php
<?
	require_once '/var/media/php/inc.mysql.php';

	// By default, save files in ~/.mplayer/playback/
	$home = getenv('HOME');
	$save_files_to = "$home/.mplayer/snapshots/";

	// Check to see the directory exists.  If not, try and create it.
	if(!is_dir($save_files_to)) {
		if(!mkdir($save_files_to)) {
			fwrite(STDOUT, "Please make sure the directory $save_files_to exists and is both readable and writable by this user\n");
			die;
		}
	}

	// Make sure I can save playback files
	if(!is_writable($save_files_to) || !is_readable($save_files_to)) {
		fwrite(STDOUT, "I can't read from and/or write to $save_files_to\n");
		die;
	}

	// If they didn't pass any arguments, exit .. stage left
	if($argc === 1)
		die;
	
	// Correct *some* human error ;)
	if(substr($save_files_to, -1, 1) != '/')
		$save_files_to .= '/';

	// grep the filename to play
	// add more extensions if you like :)
	$movie = end(preg_grep('/\.(mk[av]|vob|mpeg|mp[34gc]|og[gm]|avi|wav|midi?|wm[av]|mov|asf|yuv|ram?|aac|nuv|m4[av]|flac|au|m2v|mp4v|qt|rm(vb)?|flv|pls|m3u)$/i', $argv));

	// If we can't get the movie name, just quit
	if(empty($movie)) {
		fwrite(STDERR, "mplayer-resume: No recognized filename to playback.\n");
		die;
	}

	// Drop the binary command
	unset($argv[0]);
	
	// Put the arguments back together again
	$str_args = implode(' ', $argv);

	// Remove arguments which will break the script
	// -really-quiet means no output to our slave commands
	$str_args = str_replace('-really-quiet', '', $str_args);

	// Keep the movie filename separate from args
	$str_args = str_replace($movie, '', $str_args);
	
	// Where the seek position will be saved
	$txt = $save_files_to.basename($movie).".txt";

	// Add quotes around the movie filename if it has spaces in it
	if(strpos($movie, ' ') !== false) {
		$file = "\"$movie\"";
	} else {
		$file =& $movie;
	}
	
	// Build the execution string
	$exec = escapeshellcmd("mplayer $flags $str_args $file -quiet -vf screenshot -lircconf /home/steve/.mplayer/myth-snapshot -input conf=/home/steve/.mplayer/snapshot.conf");

	// Execute the command, save output to an array
	exec($exec, $arr);

	// If the file didn't even exist, mplayer will die, and so will me
	if(!file_exists($movie)) {
		fwrite(STDERR, "mplayer-resume: Couldn't find the filename $movie\n");
		die;
	}

	// Grep out the details we need from output
	#$key_position = current(preg_grep('/^ANS_TIME_POSITION\b/', $arr));
	$key_filename = current(preg_grep('/^ANS_FILENAME\b/', $arr));

	// Get the filename of the movie we were playing
	// Original format is ANS_FILENAME='Your_Movie.avi'
	$slave_filename = preg_replace(
		array('/^ANS_FILENAME\=\'/', '/\'$/'), 
		array('', ''),
		$key_filename);

	$arr = glob('*.png');
	if(count($arr)) {
		$img = end($arr);

		$coverfile = "/var/media/posters/$slave_filename.jpg";
		exec("convert -resize 360x ".escapeshellcmd($img)." ".escapeshellcmd($coverfile));
		unlink($img);

		$filename = getcwd().'/'.$slave_filename;

		$filename = mysql_escape_string($filename);
		$coverfile = mysql_escape_string($coverfile);

		#$sql = "UPDATE videometadata SET coverfile = '$coverfile' WHERE filename = '$filename';";
		
		#$mysql->query($sql);
	}
?>
