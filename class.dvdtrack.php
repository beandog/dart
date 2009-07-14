<?

	class DVDTrack {
	
		private $device;
		private $lsdvd;
		private $id;
		private $track;
		private $num_chapters = 0;
		
		private $verbose = false;
		private $debug = false;
	
		function __construct($track = 1, $device = "/dev/dvd") {
		
			$this->setTrack($track);
			
			if(!is_null($device))
				$this->setDevice($device);
			else
				$this->setDevice = "";
			
			$this->setBasename();
			
			$this->setLangCode();
				
			bcscale(3);
			
		}
		
		/** Helper output **/
		function setVerbose($bool) {
			if($bool === true)
				$this->verbose = true;
			else
				$this->verbose = false;
		}
		
		function setDebug($bool) {
			if($bool === true)
				$this->debug = $this->verbose = true;
			else
				$this->debug = false;
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
		
		private function setTrack($track) {
			$track = abs(intval($track));
			if($track) {
				$this->track = $track;
			} else {
				$this->track = 1;
			}
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
				$this->video_format = (string)$this->sxe->track->format;
				$this->aspect_ratio = (string)$this->sxe->track->aspect;
				$this->width = (int)$this->sxe->track->width;
				$this->height = (int)$this->sxe->track->height;
				
				// Audio Tracks
				foreach($this->sxe->track->audio as $audio) {
					$this->num_audio_tracks++;
					
					// Get the AID
 					$aid = end(explode("x", (string)$audio->streamid)) + 48;
					
					$this->audio[(int)$audio->ix] = array(
						'langcode' => (string)$audio->langcode,
						'language' => (string)$audio->language,
						'format' => (string)$audio->format,
						'channels' => (int)$audio->channels,
						'stream_id' => (string)$audio->streamid,
						'aid' => $aid,
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
				
				/**
				 * lsdvd workaround:
				 *
				 * lsdvd has a small bug (I think, loosely verified) where
				 * it adds too many chapters.  You can verify it by comparing
				 * the output of the normal output and the XML output.  The
				 * last chapter will always be the same length as the first one.
				 *
				 * Since the first chapter is really 00:00:00.000, the entire index is
				 * off by one (compared to dvdxchap).  I want to retain that first chapter
				 * so if you want to jump back *all* the way to the beginning, you can.
				 *
				 * So, the workaround is to first insert the fake chapter at the front,
				 * and then, when finished, drop the last chapter.
				 */
				 
				$this->chapters[1] = array(
					'length' => 0.00,
					'name' => "Chapter 1"
				);
				$chapter = 2;
				 
				foreach($this->sxe->track->chapter as $obj) {
					$length = (float)$obj->length;
					$this->chapters[(int)$obj->ix + 1] = array(
						'length' =>$length,
						'name' => "",
					);
					
					$chapter++;
				}
				
 				array_pop($this->chapters);
				
				$this->num_chapters = count($this->chapters);
				
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
		
		/** Video **/
		public function getFPS() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->fps;
		}
		
		public function getVideoFormat() {
			if(!$this->sxe)
				$this->lsdvd();
			return $this->video_format;
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
		
		/** Language **/
		public function setLangCode($str = "en") {
			$str = trim($str);
			if(!empty($str) && is_string($str))
				$this->langcode = $str;
		}
		
		public function getLangCode() {
			return $this->langcode;
		}
		
		/** Audio **/
		
		/**
		 * Select the first audio stream with the preset langcode
		 */
		function setAudioIndex() {
			if(!$this->sxe)
				$this->lsdvd();
				
			if(!$this->langcode)
				$this->setLangCode();
			
			// Subtitles
			foreach($this->audio as $idx => $arr) {
				if($arr['langcode'] == $this->getLangCode() && !$this->audio_index)
					$this->audio_index = $idx;
			}
		}
		
		function getAudioIndex() {
			if(!$this->audio_index)
				$this->setAudioIndex();
			return $this->audio_index;
		}
		
		function getAudioChannels() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['channels'];
		}
		
		function getAudioFormat() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['format'];
		}
		
		function getAudioStreamID() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['stream_id'];
		}
		
		function getAudioAID() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['aid'];
		}
		
		/** Subtitles **/
		
		/**
		 * Select the first subtitle stream with the preset langcode
		 */
		function setSubtitlesIndex() {
			if(!$this->sxe)
				$this->lsdvd();
				
			if(!$this->langcode)
				$this->setLangCode();
			
			// Subtitles
			foreach($this->subtitles as $idx => $arr) {
				if($arr['langcode'] == $this->getLangCode() && !$this->subtitle_index)
					$this->subtitle_index = $idx;
			}
		}
		
		function getSubtitlesIndex() {
			if(!$this->subtitle_index)
				$this->setSubtitlesIndex();
			return $this->subtitle_index;
		}
		
		function getSubtitlesStreamID() {
			if(!$this->subtitle_index)
				$this->setSubtitlesIndex();
				
			return $this->subtitles[$this->getSubtitlesIndex()]['stream_id'];
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
		
		public function getDvdxchapFormat() {
			if(!$this->sxe)
				$this->lsdvd();
			
			// Typically, a track with only 2 chapters is
			// just badly authored.
			if($this->getNumChapters() <= 1)
				return "";
				
			$starting_chapter = $this->getStartingChapter();
			$ending_chapter = $this->getEndingChapter();
			
			if(!$starting_chapter)
				$starting_chapter = 1;
			
			if(!$ending_chapter)
				$ending_chapter = $this->getNumChapters();
			
			// More error checking
			if($starting_chapter >= $ending_chapter)
				return "";
			
			// Where to start the slice
			$offset = $starting_chapter - 1;
			
			// How many chapters to fetch
			$length = $ending_chapter - $starting_chapter + 1;
			
			// Preserve the keys when grabbing, as we index the key as chapter #
			$arr_slice = array_slice($this->chapters, $offset, $length, true);
			
			$chapter = 1;
			
			foreach($arr_slice as $key => $arr) {
				$start_pos = bcadd($start_pos, $this->getChapterLength($key));
				$hms = $this->secondsToHMS($start_pos);
				
				$pad = str_pad($chapter, 2, 0, STR_PAD_LEFT);
				
				$str .= "CHAPTER$pad=$hms\n";
				
				// See if the chapter name has something manually set.
				// If not, just name it "Chapter X"
				$chapter_name = $this->getChapterName($key);
				if(!$chapter_name)
					$chapter_name = "Chapter $chapter";
				
				$str .= "CHAPTER${pad}NAME=$chapter_name\n";
				
				$chapter++;
			}
			
			return $str;
		}
		
		public function setStartingChapter($int) {
			$int = abs(intval($int));
			if($int) {
				$this->starting_chapter = $int;
			}
		}
		
		public function getStartingChapter() {
			return $this->starting_chapter;
		}
		
		public function setEndingChapter($int) {
			$int = abs(intval($int));
			if(!$int)
				$int = null;
			$this->ending_chapter = $int;
		}
		
		public function getEndingChapter() {
			return $this->ending_chapter;
		}
		
		public function setChapterName($chapter, $name = "") {
			$chapter = abs(intval($chapter));
			if($chapter && !($chapter > $this->getNumChapters())) {
				$this->chapters[$chapter]['name'] = $name;
			}
		}
		
		public function getChapterName($chapter) {
		
			$chapter = abs(intval($chapter));
			if(!$chapter)
				return "";
			if($chapter > $this->getNumChapters())
				return "";
			
			return $this->chapters[$chapter]['name'];
		
		}
		
		public function getChapterLength($chapter) {
		
			$chapter = abs(intval($chapter));
			if(!$chapter)
				return "";
			if($chapter > $this->getNumChapters())
				return "";
			
			return $this->chapters[$chapter]['length'];
		
		}
		
		/** Basename for dumping **/
		public function setBasename($str = "") {
			$str = trim($str);
			
			if(empty($str) || !is_string($str))
				$this->basename = "dvd";
			else
				$this->basename = $str;
		}
		
		public function getBasename() {
			return $this->basename;
		}
		
		/** Dump Stuff **/
		function dumpStream() {
		
			$track = $this->getTrack();
			$device = $this->getDevice();
			$starting_chapter = $this->getStartingChapter();
			$ending_chapter = $this->getEndingChapter();
			$vob = $this->getBasename().".vob";
			$aid = $this->getAudioAID();
			
			$flags[] = "dvd://$track";
			$flags[] = "-dvd-device $device";
			$flags[] = "-aid $aid";
			$flags[] = "-dumpstream -dumpfile \"$vob\"";
			if($starting_chapter > 1 || $ending_chapter) {
				$flags[] = "-chapter $starting_chapter-$ending_chapter";
			}
			$flags[] = "-quiet";
			
			$str = "mplayer ".implode(' ', $flags);
			
			if($this->verbose || $this->debug)
				shell::msg("Executing: $str");
			
 			shell::cmd($str);
		}
		
		function dumpSubtitles() {
			
			$track = $this->getTrack();
			$device = $this->getDevice();
			$basename = $this->getBasename();
			$slang = $this->getLangCode();
			$starting_chapter = $this->getStartingChapter();
			$ending_chapter = $this->getEndingChapter();
			
			$flags[] = "dvd://$track";
			$flags[] = "-dvd-device $device";
			$flags[] = "-ovc copy";
			$flags[] = "-nosound";
			$flags[] = "-vobsubout \"$basename\"";
			$flags[] = "-o /dev/null";
			$flags[] = "-slang $slang";
			$flags[] = "-quiet";
			
			if($starting_chapter > 1 || $ending_chapter) {
				$flags[] = "-chapter $starting_chapter-$ending_chapter";
			}
			
			$str = "mencoder ".implode(' ', $flags);
			
			if($this->debug)
				shell::msg("Executing: $str");
			
 			shell::cmd($str, !$this->debug);
		}
		
		function dumpChapters() {
		
			$txt = $this->getBasename().".txt";
			
			$str = $this->getDvdxchapFormat();
			
			if(!empty($str))
				file_put_contents($txt, $str);
		
		}
		
	}
?>
