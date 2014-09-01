<?php

class MP3 {

	// Filenames
	public $source;
	public $basename;
	public $dirname;
	public $mp3;
	public $wav;
	public $scan_log;
	public $log;

	// mp3splt parameters
	public $params = array();

	public function __construct($source) {

		$this->source = $source;
		$this->basename = basename($source, '.mkv');
		$this->dirname = dirname($source);

		$this->mp3 = $this->dirname.'/'.$this->basename.'.mp3';
		$this->wav = $this->dirname.'/'.$this->basename.'.wav';
		$this->log = $this->dirname.'/'.$this->basename.'.log';
		$this->scan_log = $this->dirname.'/mp3splt.log';

	}

	public function create_wav_file() {

		$arg_source = escapeshellarg($this->source);
		$arg_wav = escapeshellarg($this->wav);

		$cmd = "avconv -y -i $arg_source $arg_wav &> /dev/null";

		exec($cmd, $arr, $retval);

		if($retval)
			return false;
		else
			return true;

	}

	public function create_mp3_file() {

		$arg_wav = escapeshellarg($this->wav);
		$arg_mp3 = escapeshellarg($this->mp3);

		$cmd = "lame -b 192 --cbr -V 0 $arg_wav $arg_mp3";

		exec($cmd, $arr, $retval);

		if($retval)
			return false;
		else
			return true;

	}

	public function scan_mp3_silence() {

		if(file_exists($this->scan_log))
			unlink($this->scan_log);

		$arg_mp3 = escapeshellarg($this->mp3);
		$params = $this->get_params();

		// -s split on silence
		// -n no MP3 tags
		// -x no Xing header
		$cmd = "mp3splt $arg_mp3 -s -n -x";

		if(strlen($params))
			$cmd .= " -p $params";

		exec($cmd, $arr, $retval);
		echo "\n";

		if(file_exists($this->scan_log))
			rename($this->scan_log, $this->log);

		if($retval)
			return false;
		else
			return true;

	}

	public function get_seek_points() {

		$log = file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		$seek_lines = array_slice($log, 2);

		sort($seek_lines, SORT_NUMERIC);

		foreach($seek_lines as $line) {

			$arr = preg_split('/\s+/', $line);
			$start = $arr[0];
			$stop = $arr[1];

			$this->seek_points[] = array(
				'start' => $start,
				'stop' => $stop
			);

		}

		return $this->seek_points;

	}

	public function add_param($key, $value) {

		$this->params[$key] = $value;

	}

	public function get_params() {

		foreach($this->params as $key => $value)
			$arr[] = "$key=$value";

		$params = implode(',', $arr);

		return $params;

	}

}
