<?

	class DVDAudio {
	
		// 2 character code for the language
		private $langcode;
		
		// Full string for the language
		private $language;	
		
		// Audio codec format (AC3, DTS)
		private $format;
		
		// Number of audio channels
		private $channels;
		
		// Stream ID for audio
		private $stream_id;
		
		// Helper variables for mplayer playback
		private $aid;
		private $ix;
		
		private $frequency;
		private $quantization;
		private $ap_mode;
		private $content;
		private $streamid;
		
		function __construct($xml, $stream_id = "0x80") {
			$this->parse_xml($xml, $stream_id);
		}
		
		/** Metadata **/
		private function parse_xml($str, $stream_id) {
		
			$sxe = simplexml_load_string($str) or die("Couldn't parse lsdvd XML output");
			
			// Audio Tracks
			foreach($sxe->track->audio as $audio) {
				
				$xml_stream_id = (string)$audio->streamid;
				
				if($xml_stream_id == $stream_id) {
					$this->ix = (int)$audio->ix;
					$this->langcode = (string)$audio->langcode;
					$this->language = (string)$audio->language;
					$this->format = (string)$audio->format;
					$this->frequency = (int)$audio->frequency;
					$this->quantization = (string)$audio->quantization;
					$this->channels = (int)$audio->channels;
					$this->ap_mode = (int)$audio->ap_mode;
					$this->content = (string)$audio->content;
					$this->streamid = $this->stream_id = (string)$audio->streamid;
				}
				
			}
		
		}
		
		public function getLanguage() {
			return $this->language;
		}
		
		public function getLangcode() {
			return $this->langcode;
		}
		
		function getChannels() {
			return $this->channels;
		}
		
		function getFormat() {
			return $this->format;
		}
		
		public function getXMLIX() {
			return $this->ix;
		}
		
		function getFrequency() {
			return $this->frequency;
		}
		
		function getQuantization() {
			return $this->quantization;
		}
		
		function getAPMode() {
			return $this->ap_mode;
		}
		
		function getContent() {
			return $this->content;
		}
		
		function getStreamID() {
			return $this->stream_id;
		}
		
		/**
		 * Helper function to get the audio index for mplayer.
		 *
		 * Starts at index 128.
		 * 0x80 => 128, etc.
		 *
		 * @return int
		 */
		public function getAID() {
		
			$int = end(explode("x", $this->stream_id));
			
			$aid = $int + 48;
			
			return $aid;
		
		}
		
		/**
		 * Helper function to get the index of the audio track,
		 * in relation to the other ones.
		 *
		 * 0x80 => 1 (first track)
		 *
		 * @return int
		 */
		public function getIX() {
		
			$int = end(explode("x", $this->stream_id));
			
			$ix = $int - 79;
			
			return $ix;
		
		}
		
		
	}
	
?>
