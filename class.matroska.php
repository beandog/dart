<?php

	class Matroska {

		private $filename;
		private $sxe;
		private $aspect_ratio;
		private $flags = array();
		private $args = array();
		private $streams = array();
		private $dtd;
		private $exec;
		private $debug;
		private $verbose;

		function __construct($filename = null) {

			if(!is_null($filename))
				$this->setFilename($filename);

			$this->dtd = "/usr/local/share/matroska/xml/matroskatags.dtd";

		}

		/** Filename **/
		public function setFilename($str) {
			if(is_string($str))
				$this->filename = $str;
		}

		public function getFilename() {
			return $this->filename;
		}

		function setDebug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
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
			if(file_exists($filename)) {
				$this->add($filename);
			}
		}

		public function addVideo($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'video');
			}
		}

		public function addAudio($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'audio');
			}
		}

		public function addSubtitles($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'subtitles');
			}
		}

		public function addChapters($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'chapters');
			}
		}

		public function addGlobalTags($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'global_tags');
			}
		}

		/** Metadata **/
		public function setTitle($str) {
			$str = trim($str);
			if(is_string($str) && strlen($str)) {
				$this->title = $str;
			}
		}

		public function getTitle() {
			return (string)$this->title;
		}

		public function setAspectRatio($str) {
			$str = trim($str);
			if(is_string($str) && strlen($str)) {
				$this->aspect_ratio = $str;
			}
		}

		public function getAspectRatio() {
			return (string)$this->aspect_ratio;
		}


		/** Global Tags **/
		function createXML() {

			$dtd =& $this->dtd;

			$str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE Tags SYSTEM "$this->dtd">
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

			// FIXME in v4.2.0, this doesn't set English
			// on all tracks.  Need to specify it manually
			// with --language TID:eng
			$flags['default-language'] = 'eng';
			$flags['output'] = $this->getFilename();
			$args = array();

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
						break;

					case 'global_tags':
						$flags['global-tags'] = $arr['filename'];
						break;

					default:
						$args[] = $arr['filename'];
						break;

				}
			}

			$this->args = $args;
			$this->flags = $flags;

		}

		public function getCommandString() {
			$this->arguments();

			$exec[] = "mkvmerge";

			if($this->verbose || $this->debug)
				$exec[] = "-v";

			foreach($this->args as $argument) {
				$exec[] = escapeshellarg($argument);
			}

			foreach($this->flags as $option => $mixed) {

				if(is_array($mixed))
					foreach($mixed as $argument)
						$exec[] = "--$option ".escapeshellarg($argument);
				else
					$exec[] = "--$option ".escapeshellarg($mixed);
			}

			$str = implode(" ", $exec);

			$this->exec = $str;

			return $str;

		}

		public function mux() {

			$cmd = $this->getCommandString();

			if($this->debug)
				echo "! mux(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvmerge succeeds on exit codes of 0 or 1
			if($retval === 0) {
				return true;
			} elseif($retval === 1) {
				echo "! mux(): mkvmerge succeeded, but with warnings\n";
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
				echo "! mkvpropedit(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvpropedit succeeds on exit codes of 0 or 1
			if($retval === 1) {
				echo "! mkvpropedit(): mkvpropedit succeeded, but with warnings\n";
			} else {
				return false;
			}

			$cmd = "mkvpropedit -t global:$xml $mkv";

			if($this->debug)
				echo "! mkvpropedit(): Executing: $cmd";

			if($debug)
				$cmd .= " 2>&1";
			else
				$cmd .= " 2> /dev/null";

			exec($cmd, $output, $retval);

			// mkvpropedit succeeds on exit codes of 0 or 1
			if($retval === 1) {
				echo "! mkvpropedit(): mkvpropedit succeeded, but with warnings\n";
			} else {
				return false;
			}

			return true;

		}

	}
?>
