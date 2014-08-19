<?php

	class MediaInfo {

		private $filename;
		private $xml;
		private $sxe;
		public $opened;

		function __construct($filename) {

			$filename = realpath($filename);

			if(!file_exists($filename)) {

				echo "! construct(): opening $filename FAILED\n";
				$this->opened = false;
				return false;

			}

			// Get XML

			$cmd = "mediainfo --output=XML ".escapeshellarg($this->filename)." 2> /dev/null";

			exec($cmd, $output, $retval);

			if($retval !== 0) {
				echo "! setFilename(): $cmd FAILED\n";
				$this->opened = false;
				return false;
			}

 			$this->xml = implode("\n", $output);
 			$this->sxe = simplexml_load_string($this->xml);

			$this->opened = true;
			return true;

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
