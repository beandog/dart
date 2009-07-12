<?

	class DVDTrack {
	
		private $device;
		private $lsdvd;
		private $id;
		private $track;
		private $num_chapters = 0;
	
		function __construct($track = 1, $device = "/dev/dvd") {
		
			if(!is_null($track))
				$this->setTrack($track);
			
			if(!is_null($device))
				$this->setDevice($device);
			else
				$this->setDevice = "";
				
			bcscale(3);
			
		}
		
		/** Hardware **/
		function setDevice($str) {
			$str = trim($str);
			if(is_string($str))
				$this->device = $str;
		}
		
		function getDevice() {
			return $this->device;
		}
		
		function setTrack($track) {
			$track = abs(intval($track));
			if($track)
				$this->track = $track;
		}
		
		function getTrack() {
			return $this->track;
		}
		
		/** Metadata **/
		private function lsdvd() {
		
			if(empty($this->lsdvd['output'])) {
				$str = "lsdvd -Ox -v -a -s -c -t ".$this->getTrack()." ".$this->getDevice();
				$arr = shell::cmd($str);
				$str = implode("\n", $arr);
				
				// Fix broken encoding on langcodes, standardize output
				$str = str_replace('Pan&Scan', 'Pan&amp;Scan', $str);
				$str = str_replace('P&S', 'P&amp;S', $str);
				$str = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $str);
				
				$this->xml = $str;
				
				$this->sxe = simplexml_load_string($str) or die("Couldn't parse lsdvd XML output");
				
				$this->length = (float)$this->sxe->track->length;
				$this->fps = (float)$this->sxe->track->fps;
				$this->format = (string)$this->sxe->track->format;
				$this->aspect_ratio = (string)$this->sxe->track->aspect;
				$this->width = (int)$this->sxe->track->width;
				$this->height = (int)$this->sxe->track->height;
				
				// Audio Tracks
				foreach($this->sxe->track->audio as $audio) {
					$this->num_audio_tracks++;
					$this->audio[(int)$audio->ix] = array(
						'langcode' => (string)$audio->langcode,
						'language' => (string)$audio->language,
						'format' => (string)$audio->format,
						'channels' => (int)$audio->channels,
						'stream_id' => (string)$audio->streamid,
					);
				}
				
				// Subtitles
				foreach($this->sxe->track->subp as $subp) {
					$this->num_subtitles++;
					$this->subtitles[(int)$subp->ix] = array(
						'langcode' => (string)$subp->langcode,
						'language' => (string)$subp->language,
						'stream_id' => (string)$subp->streamid,
					);
				}
				
				// Chapters
				$start_pos = 0.00;
				
				foreach($this->sxe->track->chapter as $chapter) {
					$length = (float)$chapter->length;
					$this->chapter[(int)$chapter->ix] = $length;
				}
				
				$this->num_chapters = count($this->chapter);
				
			}
		
		}
		
		public function getXML() {
			if(!$this->xml)
				$this->lsdvd();
			return $this->xml;
		}
		
		public function getLength() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->length;
		}
		
		public function getFPS() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->fps;
		}
		
		public function getFormat() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->format;
		}
		
		public function getAspectRatio() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->aspect_ratio;
		}
		
		public function getWidth() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->width;
		}
		
		public function getHeight() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->height;
		}
		
		/** Chapters **/
		
		public function getNumChapters() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->num_chapters;
		}
		
		private function secondsToHMS($float) {
			$int = intval($float);
			$remainder = bcsub($float, $int);
			
			$m = (int)($float / 60);
			$s = $float % 60;
			$h = (int)($m / 60);
			$m = $m % 60;
			
			$s = bcadd($s, $remainder);
			
			$h = str_pad($h, 2, 0, STR_PAD_LEFT);
			$m = str_pad($m, 2, 0, STR_PAD_LEFT);
			$s = str_pad($s, 6, 0, STR_PAD_LEFT);
			
			return "$h:$m:$s";
		}
		
		public function getDvdxchapFormat($starting_chapter = 1, $ending_chapter = null) {
			if(!$this->sxe)
				$this->lsdvd();
			
			// Typically, a track with only 2 chapters is
			// just badly authored.
			if($this->getNumChapters() <= 1)
				return "";
				
			$starting_chapter = abs(intval($starting_chapter));
			$ending_chapter = abs(intval($ending_chapter));
			
			if(!$starting_chapter)
				$starting_chapter = 1;
			
			$offset = $starting_chapter - 1;
			
			if($ending_chapter)
				$length = ($ending_chapter - $starting_chapter) + 1;
			else
				$length = $this->getNumChapters();
				
			$arr = array_slice($this->chapter, $offset, $length);
			
			// If grabbing all the chapters, ignore the last one
			// similar to dvdxchap
			if(!$ending_chapter)
				array_pop($arr);
			
			// Create chapter 1 manually
			$hms = "00:00:00.000";
			$str = "CHAPTER01=00:00:00.000\n";
			$str .= "CHAPTER01NAME=Chapter 01\n";
			
			$start_pos = 0.000;
			$chapter = 2;
			
			foreach($arr as $value) {
				$start_pos = bcadd($start_pos, $value);
				$hms = $this->secondsToHMS($start_pos);
				
				$pad = str_pad($chapter, 2, 0, STR_PAD_LEFT);
				
				$str .= "CHAPTER$pad=$hms\n";
				$str .= "CHAPTER${pad}NAME=Chapter $pad\n";
				
				$chapter++;
			}
			
			return $str;
		}
	}
?>
