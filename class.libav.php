<?php

class LibAV {

	// Filenames
	public $source;
	public $basename;
	public $dirname;
	public $output;
	public $log;

	// Timestamps
	public $min_start_point = 1;

	// libav
	public $avconv_blackframe_amount = 98;

	// Scanning
	public $min_pblack = 98;
	public $min_frames = 30;
	public $possible_breaks = array();

	public function __construct($source) {

		$this->source = $source;
		$this->basename = basename($source, '.mkv');
		$this->dirname = dirname($source);

		$this->log = $this->dirname.'/'.$this->basename.'.avconv.out';

	}

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

	public function scan_breakpoints() {

		if(file_exists($this->log))
			$this->output = file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		else
			return false;

		$this->min_pblack = abs(intval($this->min_pblack));
		if($this->min_pblack > 100)
			$this->min_pblack = $this->default_min_pblack;
		$this->min_frames = abs(intval($this->min_frames));
		if(!$this->min_frames)
			$this->min_frames = $this->default_min_frames;

		// Scan the avconv output and arrange into an array of values
		// that match the given minimum pblack amount.

		$seconds = array();

		foreach($this->output as $line) {

			$tmp = explode(' ', $line);

			$pblack = end(explode(':', $tmp[4]));
			$t = end($tmp);
			$timestamp = end(explode(':', end($tmp)));

			// Only keep track of 100% black frames
			if($pblack >= $this->min_pblack && intval($timestamp) >= $this->min_start_point) {
				$seconds[intval($timestamp)][] = $timestamp;
			}

		}

		// Get the timestamps for something that starts and stops within a second

		$start_point = current(array_keys($seconds));
		$last_point = $start_point;
		$stop_point = 0;
		$points = array();
		$points_index = 0;

		foreach($seconds as $key => $arr) {

			foreach($arr as $timestamp)
				$points[$points_index]['timestamps'][] = $timestamp;

			if($key == $start_point + 1 || $key == $stop_point + 1) {
				$stop_point = $key;
				$points[$points_index]['start'] = $start_point;
				$points[$points_index]['stop'] = $stop_point;
				ksort($points[$points_index]);
				$points_index++;
			} else
				$start_point = $key;

		}

		// Find the probable break points by first counting how many pblack entries
		// there are in a point sequence, and seeing if they are over 15 frames
		// (half a second).  Then, give the middle number between the start and
		// stop points to get the place where it is in the middle.

		$possible_breaks = array();

		foreach($points as $sequence) {

			$min_frames = $this->min_frames;
			$num_frames = count($sequence['timestamps']);
			if($num_frames > $min_frames) {

				$start_timestamp = current($sequence['timestamps']);
				$stop_timestamp = end($sequence['timestamps']);
				$possible_break = $stop_timestamp - $start_timestamp;
				$breakpoint = $start_timestamp + $possible_break;

				$time_index = gmdate("H:i:s", $breakpoint);
				$ms = end(explode('.', $breakpoint));
				$time_index .= ".$ms";

				$possible_breaks[] = array(
					'breakpoint' => $breakpoint,
					'time_index' => $time_index,
					'num_frames' => $num_frames,
				);

			}

		}

		$this->possible_breaks = $possible_breaks;

		return true;

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

}
