#!/usr/bin/php
<?
	$save_files_to = "/home/steve/.mplayer/playback/";

	/**
	 *
	 * @author Steve Dibb <steve dot dibb at gmail dot com>
	 * @copyright public-domain
	 * @date 2006-07-16
	 * @version 1.0
	 * 
	 * Run time dependencies: PHP 5
	 *
	 * MPLayer playback / resume position script
	 * 
	 * 	This PHP script will save the playback position of a file
	 * 	you use with MPLayer.  Once you start playing the same file
	 * 	again, it will resume from last position.
	 *
	 * 	The *only* way this script will work is if you pass the
	 * 	"get_time_pos" command to MPlayer, either through LIRC or
	 * 	through the slave stdin.
	 *
	 *	See http://www.mplayerhq.hu/DOCS/tech/slave.txt for commands
	 *	that work on both backends.
	 *
	 * LIRC:
	 *
	 * 	Setup your ~/.lircrc file to print out the playback position
	 *  	and then quit playing the file.
	 * 
	 *		begin
	 *			prog = mplayer
	 *			button = exit
	 *			config = get_time_pos
	 *		end
	 *		begin
	 *			prog = mplayer
	 *			button = exit
	 *			config = quit
	 *		end
	 *
	 * Slave:
	 *
	 * 	You can also use mplayer -slave.  Just send 'get_time_pos'
	 * 	and then quit.  Useful for debugging.
	 *
	 *  		$ mplayer -slave -quiet foo-bar.avi
	 *  		get_time_pos
	 *  		quit
	 *
	 * Configuration:
	 *
	 *	This script will save a text file for each movie you
	 * 	watch using this script.  The files are saved in the
	 * 	config variable $save_files_to defined above, which
	 *	must be a directory that this script can read / write to.
	 *
	 * 	Note that using ~ for your home directory in PHP won't work.
	 *	
	 * Usage:
	 *
	 * 	To execute this program, either call it using the PHP
	 * 	binary, or make it executable.  Whichever you like best. :)
	 *
	 *	$ php mplayer-playback.php foo-bar.avi
	 *
	 *	The script should parses any arguments that you want to send
	 * 	mplayer at the same time.
	 *
	 *	$ php mplayer-playback.php foo-bar.avi -vo xv
	 * 
	 *	The *only* extra argument that this script will add to your
	 *	mplayer command is -ss to seek to resume a file.
	 *
	 *
	 */

	// Make sure I can save playback files
	if(!is_writable($save_files_to) || !is_readable($save_files_to)) {
		fwrite(STDOUT, "I can't read from or write to $save_files_to\n");
		fwrite(STDOUT, "Please make sure the directory exists and is both readable and writable by this user\n");
		die;
	}
	
	// Correct *some* human error ;)
	if(strpos($save_files_to, -1, 1) != '/')
		$save_files_to .= '/';

	// grep the filename to play
	// add more extensions if you like :)
	$movie = end(preg_grep('/\.(mk[av]|vob|mpeg|mp[34g]|og[gm]|avi|wav|midi?|wm[av]|mov|asf|yuv|ram?|aac|nuv|m4a)$/i', $argv));

	// Drop the binary command
	unset($argv[0]);
	
	// Put the arguments back together again
	$str_args = implode(' ', $argv);
	
	// Where the seek position will be saved
	$txt = $save_files_to."$movie.txt";

	// If there is already a playback file, read it and start
	// from that position.
	if(file_exists($txt)) {
		$ss = trim(file_get_contents($txt));
		// One more check for garbage
		if(is_numeric($ss) && $ss > 0)
			$flags = " -ss $ss ";
	}
	
	// Build the execution string
	$exec = "mplayer $movie $flags $str_args";

	// Execute the command, save output to an array
	exec($exec, $arr);
	
	// grep out the output we want
	$arr = preg_grep('/^ANS_TIME_POSITION\b/', $arr);

	// the endpos when quitting
	$endpos = str_replace("ANS_TIME_POSITION=", '', end($arr));

	// Seeking isn't perfect, so throw it back 3 seconds to correct it
	$endpos -= 3;

	// If it's a negative value, that means you've seeked
	// past/to the end of the file, so just remove the old one.
	if($endpos < 1)
		unlink($txt);
	// Save the (positive) playback position to the file
	else
		file_put_contents($txt, $endpos);
?>
