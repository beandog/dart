<?php

	class DVDSubs {

		// 2 character code for the language
		private $langcode;

		// Full string for the language
		private $language;

		// Subtitle format (VobSub, CC)
		private $format;

		// Stream ID for audio
		private $stream_id;

		// Helper variables for mplayer playback
		private $sid;

		// Sequential index
		private $ix;

		// Content
		private $content;

		function __construct($xml, $stream_id = "0x20") {
			$this->parse_xml($xml, $stream_id);
		}

		/** Metadata **/
		private function parse_xml($str, $stream_id) {

			$sxe = simplexml_load_string($str) or die("Couldn't parse lsdvd XML output");

			// Subtitles
			foreach($sxe->track->subp as $subp) {

				$xml_stream_id = (string)$subp->streamid;

				if($xml_stream_id == $stream_id) {

					$this->langcode = (string)$subp->langcode;
					$this->language = (string)$subp->language;
					$this->stream_id = (string)$subp->streamid;
					$this->ix = (int)$subp->ix;
					$this->content = (string)$subp->content;

				}

			}

		}

		public function getXMLIX() {
			return $this->ix;
		}

		public function getIX() {
			return $this->ix;
		}

		public function getLanguage() {
			return $this->language;
		}

		public function getLangcode() {
			return $this->langcode;
		}

		public function getContent() {
			return $this->content;
		}

		/**
		 * Helper function to get the subtitle index for mplayer (sid).
		 *
		 * Starts at index 1.
		 * 0x20 => 1, etc.
		 *
		 * @return int
		 */
		public function getSID() {

			$int = end(explode("x", $this->stream_id));

			$sid = $int - 19;

			return $sid;

		}

	}

?>
