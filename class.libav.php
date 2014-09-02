<?php

class LibAV {

	// Filenames
	public $source;
	public $basename;
	public $dirname;
	public $output;
	public $log;
	public $chapters_file;

	// Timestamps
	public $min_start_point = 1;
	public $min_stop_point = 1;
	public $duration = 0;

	// libav
	public $metadata;
	public $avconv_blackframe_amount = 98;

	// Scanning
	public $min_pblack = 98;
	public $min_frames = 30;
	public $max_timestamp_diff = 1;
	public $possible_breaks = array();

	// Chapters
	public $chapters = array();

	public function __construct($source) {

		$this->source = $source;
		$this->basename = basename($source, '.mkv');
		$this->dirname = dirname($source);

		$this->log = $this->dirname.'/'.$this->basename.'.avconv.out';
		$this->chapters_file = $this->dirname.'/'.$this->basename.'.chapters.txt';

		$this->avprobe();

	}

	/**
	 * avprobe scans the media file and return a JSON object
	 *
	 * @return array
	 */
	public function avprobe() {

		$arg_filename = escapeshellarg($this->source);

		$cmd = "avprobe -show_format -of json $arg_filename 2> /dev/null";

		exec($cmd, $output, $retval);

		if($retval)
			return false;

		$output = implode(' ', $output);
		$json = json_decode($output, true);
		$this->metadata = $json['format'];

		$this->duration = $this->metadata['duration'];

		return $this->metadata;

	}

	/**
	 * Scan the source media file for black frames using the av filter.
	 *
	 * @return boolean success on execution of command
	 */
	public function scan_blackframes() {

		$arg_filename = escapeshellarg($this->source);
		$arg_blackframe_amount = $this->avconv_blackframe_amount;
		$arg_log = escapeshellarg($this->log);

		$cmd = "avconv -i $arg_filename -vf blackframe=$arg_blackframe_amount -f rawvideo -y /dev/null 2>&1 | grep blackframe > $arg_log";
		exec($cmd, $output, $retval);

		if($retval)
			return false;
		else
			return true;

	}

	/**
	 * Scan the data acquired by the avfilter, and assemble breakpoints.
	 *
	 * @param string type of breakpoint assembly (see the source code for details)
	 * @return array of possible breakpoints
	 */
	public function scan_breakpoints($type = 'general') {

		// Scale everything to 3 decimal points
		bcscale(3);

		if(file_exists($this->log))
			$this->output = file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		else
			return false;

		// Scan the avconv output and arrange into an array of values
		// that match the given minimum pblack amount.

		$seconds = array();
		$timestamp = 0;
		$previous_timestamp = 0;

		$timestamp_diff = 0;
		$ranges = array();
		$range_index = null;

		foreach($this->output as $line) {

			$tmp = explode(' ', $line);

			$pblack = end(explode(':', $tmp[4]));
			$t = end($tmp);

			$previous_timestamp = $timestamp;

			$timestamp = bcadd(end(explode(':', end($tmp))), 0);

			$timestamp_diff = bcsub($timestamp, $previous_timestamp);

			if($timestamp_diff > $this->max_timestamp_diff) {
				$match_diff = false;
				$range_index = null;
			} else {
				$match_diff = true;
			}

			// Check if the current timestamp is equal to or after the minimum
			// starting point.
			if($timestamp < $this->min_start_point)
				$before_min_start_point = true;
			else
				$before_min_start_point = false;

			// Check if the current timestamp is far away enough from the minimum
			// stopping point.
			if(bcsub($this->duration, $timestamp) < $this->min_stop_point)
				$after_min_stop_point = true;
			else
				$after_min_stop_point = false;

			// Only keep track of minimum amount of black frames and minimum starting point
			if($pblack >= $this->min_pblack && !$before_min_start_point && !$after_min_stop_point) {

				// Two different arrays of values to track are generated here.  The first
				// uses the timestamps from the first integer value of the first timestamp
				// that matches the parameters.  This means that any timestamps following this
				// one that also match the parameters for the blackframe detection are
				// ignored.  This means that the breakpoint is going to be closest to the
				// *very beginning* of the first blackframes.  Specifically, within the middle
				// of all the ones that match that first second.
				//
				// These values are used by default, and is probably the preferred method
				// as starting a breakpoint earlier means that there's less chance that it
				// will start while the content is beginning to fade back in.
				//
				// As a matter of *personal preference*, these are the breakpoints I use
				// in my media library.

				// General Timestamps
				$key = floor($timestamp);
				$seconds[$key][] = $timestamp;

				// This second array is more precise in that it calculates a breakpoint for
				// in the middle of *all* the blackframes that meet the requested parameters
				// and also fit within the range of times to examine specified by the user.
				// As a result, this can have more or less frames to use as a base of
				// calculation, even with the same gap size of seconds beetween the two
				// arrays.
				//
				// These values are created as a proof-of-concept, and are not used by
				// default.  This is probably not the best method to use, since a fade-in
				// from blackframes could be preceded by dialogue, entry music, etc.

				// Precision Timestamps
				if($match_diff) {

					if(is_null($range_index))
						$range_index = $timestamp;

					$ranges[$range_index][] = $timestamp;

				}

			}

		}

		/** General Timestamps **/
		// Get the timestamps for something that starts and stops within a second

		$start_point = reset(array_keys($seconds));
		$last_point = $start_point;
		$stop_point = $start_point;
		$points = array();
		$points_index = 0;

		foreach($seconds as $key => $arr) {

			foreach($arr as $timestamp)
				$points[$points_index]['timestamps'][] = $timestamp;

			// If the new index is just one second after the previous one, then
			// include it in the first range.
			if($key == $start_point + 1) {
				$stop_point = $key;
				$points[$points_index]['start'] = $start_point;
				$points[$points_index]['stop'] = $stop_point;
				ksort($points[$points_index]);
				$points_index++;
			} else {
				$start_point = $key;
				$stop_point = $start_point;
			}

		}

		// Find the probable break points by first counting how many pblack entries
		// there are in a point sequence, and seeing if they are over the minimum
		// frames amount.  Then, give the middle number between the start and
		// stop points to get the best place to break.

		$possible_breaks = array();

		foreach($points as $sequence) {

			$min_frames = $this->min_frames;
			$num_frames = count($sequence['timestamps']);
			if($num_frames > $min_frames) {

				$start_timestamp = reset($sequence['timestamps']);
				$stop_timestamp = end($sequence['timestamps']);
				$possible_break = bcsub($stop_timestamp, $start_timestamp, 3);

				// Number of seconds + milliseconds of breakpoint in entire file
				$breakpoint = bcadd($start_timestamp, $possible_break);

				$time_index = gmdate("H:i:s", $breakpoint);
				$ms = str_pad(end(explode('.', $breakpoint)), 3, 0, STR_PAD_RIGHT);

				// Time index in format hh:mm:ss.ms, which can be used as
				// values for Matroska chapters
				$time_index .= ".$ms";

				$possible_breaks[] = array(
					'breakpoint' => $breakpoint,
					'time_index' => $time_index,
					'num_frames' => $num_frames,
				);

			}

		}

		$this->possible_breaks = $possible_breaks;

		/** Precision Timestamps **/

		foreach($ranges as $range) {

			$num_frames = count($range);

			$start_timestamp = reset($range);
			$stop_timestamp = end($range);

			$breakpoint = bcadd($start_timestamp, bcsub($stop_timestamp, $start_timestamp));

			$time_index = gmdate("H:i:s", $breakpoint);
			$ms = str_pad(end(explode('.', $breakpoint)), 3, 0, STR_PAD_RIGHT);
			$time_index .= ".$ms";

			$precise_breaks[] = array(
				'breakpoint' => $breakpoint,
				'time_index' => $time_index,
				'num_frames' => $num_frames,
			);

		}

		$this->precise_breaks = $precise_breaks;

		if($type == 'general')
			return $this->possible_breaks;
		elseif($type == 'precision')
			return $this->precise_breaks;

	}

	/**
	 * avconv uses the blackframe filter to detect frames that are (almost)
	 * completely black.  The percentage here is the amount of pixels that match
	 * the threshhold, and are reported in the log file.  The default is 98.
	 *
	 * !!! YOU DO NOT NEED TO CHANGE THIS VALUE unless you are *LOWERING* the
	 * percentage.  The reason being that the functions in this class that examine
	 * the breakpoints based on user input for the minimimum percentage anyway,
	 * which can be changed at any time.  Scanning the input source file always
	 * has to be done, and by changing this value, you're only shooting yourself
	 * in the foot by limiting the amount of data you have.  Nevertheless, it is
	 * a value that avconv supports, and changing it is allowed here.
	 *
	 * @param integer
	 */
	function set_avconv_blackframe_amount($percentage) {

		$percentage = abs(intval($percentage));

		if($percentage <= 100)
			$this->avconv_blackframe_amount = $percentage;

	}

	/**
	 * Set the minimum percentage that a frame must be black before either
	 * logging it or examining it for breakpoints.  Defaults to 98, which is
	 * the default for libav's blackframe filter.
	 *
	 * Best practice is to start this at 100% to remove any false positives,
	 * and *then* to go lower from here if there are not as many expected
	 * breakpoints.
	 *
	 * @param integer
	 */
	function set_min_pblack($percentage = 98) {

		$percentage = abs(intval($percentage));

		if($percentage <= 100)
			$this->min_pblack = $percentage;

	}

	/**
	 * Part of the calcuation of a possible breakpoint depends on the minimum
	 * that would have to exist for a sequential number of black frames.
	 *
	 * So with 29.97 frames per second, and looking for at least half a second
	 * minimum of black frames, the value would be 15.
	 *
	 * This is helpful because sometimes the amount of possible breakpoints
	 * returned is not what was expected.  Tweaking this number can help
	 * determine what the most accurate average number of frames a break has.
	 *
	 * Default is 30, or 30 frames for NTSC video.
	 *
	 * @param integer
	 */
	public function set_min_frames($frames = 30) {

		$frames = abs(intval($frames));

		$this->min_frames = $frames;

	}

	/**
	 * Set the cutoff value where we should *start* looking for break
	 * points after this number of seconds.  Defaults to 0.
	 *
	 * For example, if we know that the intro to a show doesn't end until
	 * at least 60 seconds (Batman: The Animated Series), then ignore any
	 * blackframe results before that time.
	 *
	 * @param integer
	 */
	public function set_min_start_point($seconds = 0) {

		$seconds = abs(intval($seconds));

		$this->min_start_point = $seconds;

	}

	/**
	 * Like setting a minimum start point to avoid blackfames that are possible
	 * during a movie / episode opening sequence, this function does the same,
	 * but for the ending.
	 *
	 * As the video ends, there are going to be blackframes as everything fades out.
	 * If the length between those blackframes and the end timestamp of the video are
	 * less than this parameter, then don't include it as a possible breakpoint.
	 *
	 * This function is here so that your number of chapter points can be accurate.
	 * Recommended value would be on a individual basis.  For TV shows, the ending
	 * credits could be anywhere from 30 seconds to a minute, usually.  And for a movie,
	 * this is going to be much longer.
	 *
	 * The default value is safe, at only 1 second, guaranteeing to trim the last final
	 * fade-out so that there's not a chapter that jumps to the very end of the video.
	 *
	 * If I had to pick a number to suggest, I'd say between 5 to 10 seconds would be
	 * good. :)
	 *
	 * @param integer
	 */
	public function set_min_stop_point($seconds) {

		$seconds = abs(intval($seconds));

		$this->min_stop_point = $seconds;

	}

	/**
	 * Set the maximum amount of a difference in seconds between comparing
	 * timestamps to see if they fit in one range or not.  Defaults to 1
	 * second.
	 *
	 * Increase this if you are getting valid results, but the breakpoints
	 * are very close to each other, and you are trying to close the gap
	 * to have only as many chapters as necessary.
	 *
	 * For example, if you have one breakpoint at 63.997 and the next one
	 * at 65.131, the difference is 1.134 seconds.  Changing the value to
	 * 1.5 seconds will close that gap and only return one breakpoint.
	 *
	 * @param float
	 */
	public function set_max_timestamp_diff($seconds) {

		// Get correct precision for timestamps
		$seconds = bcadd($seconds, 0, 3);

		if($seconds > 0)
			$this->max_timestamp_diff = $seconds;

	}

	/**
	 * Helper function to create basic values for a chapter file to be muxed
	 * directly into a Matroska file using mkvpropedit.
	 *
	 * Example: mkvpropedit -c chapters.txt movie.mkv
	 */
	public function get_chapters() {

		$chapters[] = "CHAPTER01=00:00:00.000";
		$chapters[] = "CHAPTER01NAME=Chapter 1";

		foreach($this->possible_breaks as $key => $arr) {

			$chapter = $key + 2;
			$time_index = $arr['time_index'];

			$chapter_prefix = "CHAPTER".str_pad($chapter, 2, 0, STR_PAD_LEFT);
			$chapter_time_index = $chapter_prefix."=".$time_index;
			$chapter_name = $chapter_prefix."NAME=Chapter $chapter";

			$chapters[] = $chapter_time_index;
			$chapters[] = $chapter_name;

		}

		$this->chapters = $chapters;

		return $this->chapters;

	}

	/**
	 * Helper function to create chapters file directly.
	 *
	 * Can optionally write out to a specific filename.  If none is given, it
	 * uses the basename of the original source file.
	 *
	 * Like the get_chapters() function, this outputs only the very basic format
	 * syntax for a chapters file, and not the full XML which mkvmerge can use.
	 *
	 * @param filename
	 */
	public function create_chapters_file($filename = null) {

		if(is_null($filename))
			$filename = $this->chapters_file;

		$chapters = $this->get_chapters();

		$contents = implode("\n", $chapters)."\n";

		$int = file_put_contents($filename, $contents);

		if($int === false)
			return false;
		else
			return true;

	}

}
