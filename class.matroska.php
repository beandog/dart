<?php

	class Matroska {

		private $filename = 'matroska.mkv';
		private $sxe;
		private $aspect_ratio;
		private $flags = array();
		private $args = array();
		private $streams = array();
		private $chapters = array();
		private $cmd;
		private $debug = false;
		private $verbose = false;

		function __construct($filename = null) {

			if(!is_null($filename))
				$this->setFilename($filename);

		}

		/** Filename **/
		public function setFilename($str) {
			$this->filename = strval($str);
		}

		public function getFilename() {
			return $this->filename;
		}

		function setDebug($bool = true) {
			$this->debug = $this->verbose = boolval($bool);
		}

		/** Streams **/
		private function add($filename, $type = null) {
			if($type) {
				$this->streams[] = array(
					'filename' => $filename,
					'type' => $type,
				);
			} else {
				$this->streams[] = array(
					'filename' => $filename,
				);
			}
		}

		public function addFile($filename) {
			$this->add($filename);
		}

		public function addVideo($filename) {
			$this->add($filename, 'video');
		}

		public function addAudio($filename) {
			$this->add($filename, 'audio');
		}

		public function addSubtitles($filename) {
			$this->add($filename, 'subtitles');
		}

		public function addGlobalTags($filename) {
			$this->add($filename, 'global_tags');
		}

		/** Chapters **/

		public function addChapter($time) {

			$time = abs(floatval(bcadd($time, 0, 3)));

			$this->chapters[] = $time;

		}

		public function getChapters() {

			sort($this->chapters);

			if(floatval($this->chapters[0]) != 0) {

				array_unshift($this->chapters, 0);

			}

			return $this->chapters;

		}

		public function getFormattedChapters() {

			$chapters = $this->getChapters();
			$format = array();

			foreach($chapters as $key => $breakpoint) {

				$chapter_number = $key + 1;

				$breakpoint = bcadd($breakpoint, 0, 3);

				$time_index = gmdate("H:i:s", $breakpoint);
				$arr = explode('.', $breakpoint);
				$ms = str_pad(end($arr), 3, 0, STR_PAD_RIGHT);
				$time_index .= ".$ms";

				$chapter_prefix = "CHAPTER".str_pad($chapter_number, 2, 0, STR_PAD_LEFT);
				$chapter_time_index = $chapter_prefix."=".$time_index;
				$chapter_name = $chapter_prefix."NAME=Chapter $chapter_number";

				$format[] = $chapter_time_index;
				$format[] = $chapter_name;

			}

			$str = implode("\n", $format)."\n";

			return $str;

		}

		public function addChaptersFilename($filename) {
			$this->add($filename, 'chapters');
		}

		/** Metadata **/
		public function setTitle($str) {
			$str = trim(strval($str));
			if(strlen($str))
				$this->title = $str;
		}

		public function getTitle() {
			return strval($this->title);
		}

		public function setAspectRatio($str) {
			$str = trim(strval($str));
			if(strlen($str))
				$this->aspect_ratio = $str;
		}

		public function getAspectRatio() {
			return strval($this->aspect_ratio);
		}


		/** Global Tags **/
		function createXML() {

			$str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Tags>
</Tags>
XML;

			$this->sxe = new SimpleXMLElement($str);

		}

		function addTag() {

			if(!is_object($this->sxe))
				$this->createXML();

			$this->tag = $this->sxe->addChild("Tag");
		}

		function addSimpleTag($name, $string, $language = "eng", $tag_language = false) {

			$string = trim($string);

			if(!strlen($string))
				return false;

			$this->simple = $this->tag->addChild("Simple");
			$this->simple->addChild("Name", $name);
			$this->simple->addChild("String", $string);
			if($tag_language)
				$this->simple->addChild("TagLanguage", $language);

			return true;

		}

		function addTarget($value, $type) {
			$this->targets = $this->tag->addChild("Targets");
			$this->targets->addChild("TargetTypeValue", $value);
			$this->targets->addChild("TargetType", $type);
		}

		function getXML() {
			$doc = new DOMDocument("1.0");
			$doc->preserveWhiteSpace = false;
			$doc->loadXML($this->sxe->asXML());
			$doc->formatOutput = true;
			return $doc->saveXML();
		}

		/** Muxing **/

		private function arguments() {

			$flags = array();
			$args = array();

			$flags['output'] = $this->getFilename();

			// FIXME in v4.2.0, this doesn't set English
			// on all tracks.  Need to specify it manually
			// with --language TID:eng
			$flags['default-language'] = 'eng';

			// Added for spec dlna-usb-1
			$flags['engage'] = array('no_cue_duration', 'no_cue_relative_position');

			if($this->title)
				$flags['title'] = $this->title;

			// FIXME Clean this up so you can pass
			// multiple options to one file.
			foreach($this->streams as $arr) {

				if(!array_key_exists('type', $arr))
					$arr['type'] = null;

				switch($arr['type']) {

					case 'video':
					 	if($this->getAspectRatio()) {
							$flags['aspect-ratio'] = "0:".$this->getAspectRatio();
						}
						$flags['no-audio'] = $arr['filename'];
						break;

					case 'audio':
						$flags['no-video'][] = $arr['filename'];
						break;

					case 'subtitles':
						$flags['default-track 0:no'][] = $arr['filename'];
						break;

					case 'chapters':
						$flags['chapters'] = $arr['filename'];
						$flags['chapter-language'] = 'eng';
						break;

					case 'global_tags':
						$flags['global-tags'] = $arr['filename'];
						break;

					default:
						$args[] = $arr['filename'];
						break;

				}
			}

			$this->flags = $flags;
			$this->args = $args;

		}

		public function getCommandString() {
			$this->arguments();

			$cmd[] = "mkvmerge";

			if($this->verbose || $this->debug)
				$cmd[] = "-v";

			foreach($this->flags as $option => $mixed) {

				if(is_array($mixed))
					foreach($mixed as $argument)
						$cmd[] = "--$option ".escapeshellarg($argument);
				else
					$cmd[] = "--$option ".escapeshellarg($mixed);
			}

			foreach($this->args as $argument) {
				$cmd[] = escapeshellarg($argument);
			}

			$str = implode(" ", $cmd);

			$this->exec = $str;

			return $str;

		}

		public function mux() {

			$cmd = $this->getCommandString();

			if($this->debug)
				echo "* mux(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvmerge succeeds on exit codes of 0 or 1
			if($retval === 0) {
				return true;
			} elseif($retval === 1) {
				echo "* mux(): mkvmerge succeeded, but with warnings\n";
				return true;
			} else {
				return false;
			}

		}

		public function mkvpropedit($xml, $mkv) {

			$title = escapeshellarg($this->title);
			$mkv = escapeshellarg($mkv);
			$xml = escapeshellarg($xml);

			$cmd = "mkvpropedit -s title=$title $mkv";

			if($this->debug)
				echo "* mkvpropedit(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvpropedit succeeds on exit codes of 0 or 1
			if($retval === 1) {
				echo "* mkvpropedit(): mkvpropedit succeeded, but with warnings\n";
			} else {
				return false;
			}

			$cmd = "mkvpropedit -t global:$xml $mkv";

			if($this->debug)
				echo "* mkvpropedit(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvpropedit succeeds on exit codes of 0 or 1
			if($retval === 1) {
				echo "* mkvpropedit(): mkvpropedit succeeded, but with warnings\n";
			} else {
				return false;
			}

			return true;

		}

	}
?>
