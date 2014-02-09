<?php

	require_once 'class.shell.php';

	class MediaInfo {

		private $filename;
		private $xml;
		private $sxe;
		private $debug;

		function __construct($filename) {

			$this->setFilename($filename);
			$this->debug = false;

		}

		function setFilename($filename) {
			$this->filename = $filename;

			// Get XML

			$exec = "mediainfo --output=XML \"".$this->filename."\"";

 			$arr = shell::cmd($exec);
 			$this->xml = implode("\n", $arr);
 			$this->sxe = simplexml_load_string($this->xml);

		}

		function getFilename() {
			return $this->filename;
		}


		/**
		 * Determine if a file has closed captioning
		 *
		 * @return boolean
		 */
		function hasCC() {

			foreach($this->sxe->File->track as $track) {
				$type = (string)$track['type'];
				if($type == "Text") {
					$format = (string)$track->Format;
					if($format == "EIA-608")
						return true;
				}
			}

			return false;

		}

	}
?>
