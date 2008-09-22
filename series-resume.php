#!/usr/bin/env php
<?

	// By default, save files in ~/.mplayer/playback/
	$home = getenv('HOME');
	$save_files_to = "$home/.mplayer/series/";

	/**
	 *
	 * @author Steve Dibb <steve dot dibb at gmail dot com>
	 * @copyright public-domain
	 * @date 2008-09-21
	 * @version 1.0
	 * @homepage http://spaceparanoids.org/trac/bend/wiki/mplayer-resume
	 * 
	 * Run time dependencies:
	 * PHP >= 4.3.0 with CLI and PCRE - http://php.net/
	 * MPlayer - http://mplayerhq.hu/
	 *
	 * See README for documentation on usage
	 *
	 */
	
	/**
	 * Sends a string to stdout with a terminating line break
	 *
	 * @param string
	 * 
	 */
	function stdout($str) {
		if(!empty($str) && is_string($str)) {
			$str = rtrim($str);
			fwrite(STDOUT, "playlist-resume: $str\n");
			return true;
		} else
			return false;
	}
	
	/**
	 * Sends a string to stderr with a terminating line break
	 *
	 * @param string
	 * 
	 */
	function stderr($str) {
		if(!empty($str) && is_string($str)) {
			$str = rtrim($str);
			fwrite(STDERR, "playlist-resume: $str\n");
			return true;
		} else
			return false;
	}
	
	function trimArray($arr) {
		$tmp = array();
		foreach($arr as $key => $value)
			$tmp[$key] = trim($value);
		return $tmp;
	}

	// Check to see the directory exists.  If not, try and create it.
	if(!is_dir($save_files_to)) {
		if(!mkdir($save_files_to)) {
			stderr("Please make sure the directory $save_files_to exists and is both readable and writable by this user");
			exit(1);
		}
	}

	// Make sure I can save playback files
	if(!is_writable($save_files_to) || !is_readable($save_files_to)) {
		stderr("I can't read from and/or write to $save_files_to");
		exit(1);
	}

	// If they didn't pass any arguments, exit .. stage left
	if($argc === 1)
		exit(1);
	
	// Correct *some* human error ;)
	if(substr($save_files_to, -1, 1) != '/')
		$save_files_to .= '/';
		
	$key = array_search('-playlist', $argv);
	$movie = $argv[($key + 1)];
	unset($argv[$key]);
	
	// If we can't get the movie name, just quit
	if(!file_exists($movie)) {
		stderr("Couldn't find file $movie");
		exit(1);
	}
	
// 	echo $movie; die;

	// Drop the binary command
	unset($argv[0]);
	
	// Put the arguments back together again
	$str_args = implode(' ', $argv);

	// Remove arguments which will break the script
	// -really-quiet means no output to our slave commands
	$str_args = str_replace('-quiet', '', $str_args);

	// Keep the movie filename separate from args
	$str_args = str_replace($movie, '', $str_args);
	
	// Where the seek position will be saved
	$txt = $save_files_to.basename($movie).".txt";
	
	// If there is already a playback file, read it and start
	// from that position.
	$key_start = 0;
	if(file_exists($txt)) {
	
		// Get filename and time position of last playback
		// $filename and $time_pos
		extract(parse_ini_file($txt));
		
		$arr_playlist = trimArray(file($movie));
		
		$start = preg_grep("/$filename$/", $arr_playlist);
		if($start) {
			$key_start = key($start);
			
			// One more check for garbage
			if(is_numeric($time_pos) && $time_pos > 0)
				$flags = " -ss $time_pos ";
		}
	} else {
		$arr_playlist = trimArray(file($movie));
	}
	
//  	print_r($arr_playlist);
 	
//  	var_dump($key_start);
	
	$movie = $arr_playlist[$key_start];
// 	var_dump($movie);
		
	// Build the execution string
	$exec = escapeshellcmd("mplayer -quiet $flags $str_args ").escapeshellarg($movie);
	$flags = '';
	
// 	echo "$exec\n";

	// Execute the command, save output to an array, and grab
	// return code to see if mplayer throws an error.
	exec($exec, $arr, $return);
	
	// If mplayer dies with a positive exit code, then it failed.
	// Don't write to or delete the saved position, and die
	// with the same exit code.
	if($return !== 0) {
		stderr("mplayer died unexpectedly");
		exit($return);
	}

	// If the file didn't even exist, mplayer will die, and so will me
	if(!file_exists($movie)) {
		stderr("Couldn't find the filename $movie");
		exit(1);
	}
	
// 	print_r($arr);
	
	// Get the filename of the movie we were playing
	// Original format is ANS_FILENAME='Your_Movie.avi'
	$key_filename = current(preg_grep('/^ANS_FILENAME\b/i', $arr));
	$filename = preg_replace('/^ANS_FILENAME\=/i', '', $key_filename);

	// Get the position (in seconds) of the movie
	// Original format is ANS_TIME_POSITION=123.45
	$key_position = current(preg_grep('/^ANS_TIME_POS(ITION)?\b/i', $arr));
	$time_pos = preg_replace('/^ANS_TIME_POS(ITION)?\=/i', '', $key_position);

	// On stop, set the resume file to open the next
	// filename in the playlist at start position
	if(empty($time_pos)) {
		if($arr_playlist[($key_start + 1)]) {
			$filename = basename($arr_playlist[($key_start + 1)]);
			$contents = "filename=$filename";
		// If no more files to play, kill the resume playlist
		} else {
			unlink($txt);
			exit(0);
		}
		
	}
	// Otherwise, if exit, just resume where we left off.
	else
		$contents .= "filename=$filename\ntime_pos=$time_pos";
		
// 	var_dump($contents);
		
	// Use PHP 5's fancy new functions, if possible. :)
	if(function_exists('file_put_contents'))
		file_put_contents($txt, $contents) or stderr($error_msg);
	// Otherwise fallback on PHP 4's functions
	else {
		fwrite(fopen($txt, 'w'), $contents) or stderr($error_msg);
	}
		
	
	
?>
