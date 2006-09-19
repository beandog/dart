#!/usr/bin/php
<?

	$save_files_to = "/home/steve/.mplayer/playback/";

	/**
	 *
	 * @author Steve Dibb <steve dot dibb at gmail dot com>
	 * @copyright public-domain
	 * @date 2006-07-26
	 * @version 1.2
	 * @homepage http://wonkabar.org/bend/
	 * 
	 * Run time dependencies: PHP >= 4.3.0 with CLI
	 *
	 * MPlayer playback / resume position script
	 * 
	 * 	This PHP script will save the playback position of a file
	 * 	you watch with MPlayer.  Once you start playing the same file
	 * 	again, it will resume from last playback position.
	 *
	 * 	The *only* way this script will work is if you pass the
	 * 	"get_time_pos" command to MPlayer through an input event,
	 *	mapped by either LIRC or the keyboard.
	 *	
	 *	The script captures the output and saves it to a file in
	 *	the playback directory for each media file.
	 *
	 *	See http://www.mplayerhq.hu/DOCS/tech/slave.txt for more
	 *	commands that work on both backends.
	 *
	 * LIRC:
	 *
	 * 	Setup your ~/.lircrc file to print out the playback position
	 *  	_and_ then quit playing the file.
	 * 
	 *		begin
	 *			prog = mplayer
	 *			button = stop
	 *			config = get_time_pos
	 *		end
	 *		begin
	 *			prog = mplayer
	 *			button = stop
	 *			config = quit
	 *		end
	 *
	 * Keyboard:
	 *
	 *	Map a key with ~/.mplayer/input.conf to run 'get_time_pos'
	 *
	 *	Sample entry:
	 *
	 *		g get_time_pos
	 *
	 *	When you want to save the position, hit 'g', and then 'q'
	 *	to quit playback.
	 *
	 *	You can also do it manually using mplayer's slave mode,
	 *	which is useful for debugging:
	 *
	 *		$ mplayer -slave -quiet foo-bar.avi
	 *		get_time_pos
	 *		quit
	 *
	 * Configuration:
	 *
	 *	This script will save a text file for each media file you
	 * 	watch using this script.  The text files are saved in the
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
	 *	$ php mplayer-resume.php foo-bar.avi
	 *
	 *	# cp mplayer-resume.php /usr/local/bin/mplayer-resume
	 *	# chmod +x /usr/local/bin/mplayer-resume
	 *	$ mplayer-resume foo-bar.avi
	 *
	 *	The script should parses any arguments that you want to send
	 * 	mplayer at the same time, irregardless of whether you start
	 *	it as a standalone program or with php.
	 *
	 *	$ php mplayer-resume.php foo-bar.avi -vo xv
	 *
	 *	$ mplayer-resume foo-bar.avi -vo xv
	 * 
	 *	The *only* extra arguments that this script will add to your
	 *	mplayer command is -ss to seek to resume a file, and
	 *	-quiet so it can correctly grab the output.
	 *
	 * Notes:
	 *
	 *	If your CLI version of PHP is dumping version information
	 *	each time it runs, add -q to the top line to supress the
	 *	output.
	 *
	 *		#!/usr/bin/php -q
	 *
	 *	If you are starting the script from an external program,
	 *	don't put quotes around the filenames.  It won't work on
	 *	files with special characters.
	 *
	 *	Wrong:	$ mplayer-resume "$1"
	 *	Right:	$ mplayer-resume $1
	 *
	 * Bugs:
	 *
	 *	- Won't play files with spaces in them
	 *	- Doesn't save cwd with the file, so you can only have one
	 *	  entry per filename
	 *
	 */

	// Check to see the directory exists.  If not, try and create it.
	if(!is_dir($save_files_to)) {
		if(!mkdir($save_files_to)) {
			fwrite(STDOUT, "Please make sure the directory exists and is both readable and writable by this user\n");
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
	$movie = end(preg_grep('/\.(mk[av]|vob|mpeg|mp[34g]|og[gm]|avi|wav|midi?|wm[av]|mov|asf|yuv|ram?|aac|nuv|m4a)$/i', $argv));

	// If we can't get the movie name, just quit
	if(empty($movie)) {
		fwrite(STDERR, "mplayer-resume.php: No filename to playback.\n");
		die;
	}

	// Drop the binary command
	unset($argv[0]);
	
	// Put the arguments back together again
	$str_args = implode(' ', $argv);
	
	// Where the seek position will be saved
	$txt = $save_files_to.basename($movie).".txt";

	// If there is already a playback file, read it and start
	// from that position.
	if(file_exists($txt)) {
		$ss = trim(file_get_contents($txt));
		// One more check for garbage
		if(is_numeric($ss) && $ss > 0)
			$flags = " -ss $ss ";
	}
	
	// Build the execution string
	$exec = escapeshellcmd("mplayer $flags $str_args -quiet");
	
	// Execute the command, save output to an array
	exec($exec, $arr);

	// If the file didn't even exist, mplayer will die, and so will me
	if(!file_exists($movie)) {
		fwrite(STDERR, "mplayer-resume.php: Couldn't find the filename $movie\n");
		die;
	}
	
	// grep out the output we want
	$arr = preg_grep('/^ANS_TIME_POSITION\b/', $arr);

	// the endpos when quitting
	$endpos = str_replace("ANS_TIME_POSITION=", '', end($arr));

	// Seeking isn't perfect, so throw it back a little to correct it
	$endpos -= 2.5;

	// If it's a negative value, that means you've seeked
	// past/to the end of the file, so just remove the old one.
	if($endpos < 1 && file_exists($txt))
		unlink($txt);
	// Save the (positive) playback position to the file
	else {
		// Generic error message, fix your stupid permissions
		$error_msg = "Cannot save file, please check write permissions for this user in $save_files_to";

		// Use PHP 5's fancy new functions, if possible. :)
		if(function_exists('file_put_contents'))
			file_put_contents($txt, $endpos) or die($error_msg);
		// Otherwise fallback on PHP 4's functions
		else {
			fwrite(fopen($txt, 'w'), $endpos) or die($error_msg);
		}	
	}
?>
