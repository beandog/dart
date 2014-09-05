<?php

	/**
	 * The minimum frames and minimum seconds are not set by default, which will result
	 * in a lot of break points.  Recommended to set at least one of these.  Safe starting
	 * values for minimum seconds would be between 0.5 to 1.0.  Likewise, frames would match
	 * your fps times number of seconds, so 30 for 1 second on NTSC video.
	 *
	 * Setting minimum start point is recommended as well.  Default is 1 seconds, but it's
	 * extremely likely there's other break points before the feature title starts.
	 *
	 * Set the minimum stop point to not create chapters from fade-outs at the end of the
	 * video.  Default is 1 second.
	 *
	 * Example syntax:
	 *
	 * $libav = new LibAV($filename);
	 * $libav->set_min_seconds(1);
	 * $libav->set_min_start_point(15);
	 * $libav->set_min_stop_point(1);
	 * $libav->scan_breakpoints();
	 * $libav->create_chapters_file('chapters.txt');
	 *
	 */

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
	public $min_frames = null;
	public $min_seconds = null;
	public $max_timestamp_diff = 1;
	public $precise_breaks = array();
	public $blackframes = array();
	public $blackframe_ranges = array();

	// Chapters
	public $chapters = array();
	public $min_chapter_gap = 0;

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
	 * @return array of possible breakpoints
	 */
	public function scan_breakpoints() {

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

		// Find the probable break points by first counting how many pblack entries
		// there are in a point sequence, and seeing if they are over the minimum
		// frames amount.  Then, give the middle number between the start and
		// stop points to get the best place to break.

		foreach($this->output as $line) {

			$previous_timestamp = $timestamp;

			$tmp = explode(' ', $line);

			$frame = intval(end(explode(':', $tmp[3])));
			$pblack = intval(end(explode(':', $tmp[4])));
			$pts = intval(end(explode(':', $tmp[6])));
			$timestamp = floatval(bcadd(end(explode(':', end($tmp))), 0));

			$blackframes[] = array(
				'frame' => $frame,
				'pblack' => $pblack,
				'pts' => $pts,
				'timestamp' => $timestamp,
			);

			$timestamp_diff = floatval(bcsub($timestamp, $previous_timestamp));

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
			if(floatval(bcsub($this->duration, $timestamp)) < $this->min_stop_point)
				$after_min_stop_point = true;
			else
				$after_min_stop_point = false;

			// Only keep track of minimum amount of black frames and minimum starting point
			if($pblack >= $this->min_pblack && !$before_min_start_point && !$after_min_stop_point) {

				// General Timestamps
				$key = floor($timestamp);
				$seconds[$key][] = $timestamp;

				// Precision Timestamps
				if($match_diff) {

					if(is_null($range_index))
						$range_index = $timestamp;

					$ranges[$range_index][] = $timestamp;

				}

			}

		}

		$this->blackframes = $blackframes;
		$this->blackframe_ranges = $ranges;

		$precise_breaks = array();

		// Create break points

		$previous_breakpoint = null;
		$breakpoint_diff = null;

		foreach($ranges as $range) {

			$num_frames = count($range);

			// Check if frames meets requested minimum
			if($this->min_frames && ($num_frames < $this->min_frames))
				continue;

			$start_timestamp = reset($range);
			$stop_timestamp = end($range);
			$timestamp_diff = floatval(bcsub($stop_timestamp, $start_timestamp));

			$previous_timestamp_diff = $timestamp_diff;

			// Check if seconds meets requested minimum
			if($this->min_seconds && ($timestamp_diff < $this->min_seconds))
				continue;

			$timestamp_adjustment = floatval(bcdiv($timestamp_diff, 2));
			$breakpoint = floatval(bcadd($start_timestamp, $timestamp_adjustment));

			// Check for the minimum gap between two chapters, and drop the next one
			// if it does not meet this value.
			if($this->min_chapter_gap) {
				if($previous_breakpoint) {
					$breakpoint_diff = floatval(bcsub($breakpoint, $previous_breakpoint));
					if($breakpoint_diff < $this->min_chapter_gap) {
						$previous_breakpoint = $breakpoint;
						continue;
					}
				}
				$previous_breakpoint = $breakpoint;
			}

			$time_index = gmdate("H:i:s", $breakpoint);
			$ms = str_pad(end(explode('.', $breakpoint)), 3, 0, STR_PAD_RIGHT);
			$time_index .= ".$ms";

			$precise_breaks[] = array(
				'breakpoint' => $breakpoint,
				'time_index' => $time_index,
				'num_frames' => $num_frames,
				'start_timestamp' => $start_timestamp,
				'stop_timestamp' => $stop_timestamp,
				'timestamp_diff' => $timestamp_diff,
			);

		}

		$this->precise_breaks = $precise_breaks;

		return $this->precise_breaks;

	}

	/**
	 * FRAME DETECTION FUNCTIONS
	 *
	 * These functions change the way that break points are calculated, and affect the
	 * source data to begin with.
	 */

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
	 * Set this if the break points are too short, and the blackframes
	 * are longer than 1 second.
	 *
	 * For example, if you have one breakpoint at 63.997 and the next one
	 * at 65.131, the difference is 1.134 seconds.  Changing the value to
	 * 1.5 seconds will close that gap and only return one breakpoint.
	 *
	 * If you are not having long periods of blackframes that need to be
	 * monitored, and instead don't like chapters closely following
	 * each other, you can set the minimum length between chapters instead
	 * with the set_min_chapter_gap() function.
	 *
	 * @param float
	 */
	public function set_max_timestamp_diff($seconds) {

		bcscale(3);

		// Get correct precision for timestamps
		$seconds = floatval(bcadd($seconds, 0));

		if($seconds > 0)
			$this->max_timestamp_diff = $seconds;

	}

	/**
	 * CHAPTER CREATION FUNCTIONS
	 *
	 * These set parameters that can be changed when deciding when a break point
	 * becomes a chapter.
	 */

	/**
	 * Specify the minimum amount of frames that a break point should have
	 * before it's considered to be a chapter.
	 *
	 * Remember that this number of frames is going to match the other
	 * parameters as well, such as percent black, so this could create invalid
	 * results.
	 *
	 * Setting the minimum amount of seconds for a chapter may be a better
	 * option depending on what you're trying to do.
	 *
	 * Default is 30 frames, or 1 second for NTSC video.
	 *
	 * If you don't want to measure by frames, then use set_min_seconds() instead.
	 *
	 * @param integer
	 */
	public function set_min_frames($frames = 30) {

		$frames = abs(intval($frames));

		$this->min_frames = $frames;

	}

	/**
	 * Set the minimum amount of seconds for a break point's length to be
	 * considered to be a chapter.
	 *
	 * Part of the calcuation of a possible breakpoint depends on the minimum
	 * that would have to exist for a sequential number of black frames.
	 *
	 * Sequences of blackframes are very common, and it's the length of those
	 * seconds that determines whether it's a break point that could be used as
	 * a chapter index or not.  For example, just because something breaks for
	 * 3 frames (.1 seconds) doesn't mean it's a fade-in, fade-out sequence
	 * that would be suitable for a chapter point.
	 *
	 * If you don't want to measure by seconds, then use set_min_frames() instead.
	 *
	 * @param float
	 */
	public function set_min_seconds($seconds = 1, $format = 'NTSC') {

		bcscale(3);

		$seconds = floatval(bcadd($seconds, 0));

		$this->min_seconds = $seconds;

	}

	/**
	 * Set the minimum amount of time that should exist between two
	 * chapters.
	 *
	 * By default this is unset, and lets the chapters create as they
	 * normally would.  However, if you are having chapter points that are
	 * too close to each other, and only want the first one, then set this
	 * value to how long of a gap there should be between two chapters at
	 * a minimum.
	 *
	 * An example where this would be useful, is an episode has a break
	 * point at 60 seconds, where the intro sequence fades out and the
	 * feature title begins.  If shortly after that, there's a fade-out and
	 * back in at 66 seconds, then it would create a second break point for
	 * that event as well.  A fix for that would be to say that chapter
	 * breaks should at least be a certain amount apart (6 seconds is hardly
	 * being unreasonable).
	 *
	 * If the chapter points are starting too early to begin with in any
	 * case, regardless of break points in the beginning, then set the
	 * minimum starting point instead with set_min_start_point(), so that
	 * it will never create a break point in the chapters for anything
	 * before that time index.
	 *
	 * When comparing two chapters, this one defaults to using the *first*
	 * match.  Using the same example as before, it would use the 60 seconds
	 * breaking point, and ignore the 66 seconds one if this value was set
	 * to anything over 6 seconds.
	 *
	 * @param float
	 */
	public function set_min_chapter_gap($seconds) {

		// Get correct precision for timestamps
		$seconds = floatval(bcadd($seconds, 0, 3));

		if($seconds > 0)
			$this->min_chapter_gap = $seconds;

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

		foreach($this->precise_breaks as $key => $arr) {

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
