<?

	/**
	 * A few words about chapters
	 *
	 * The goal here is to store the chapter information for the tracks in a
	 * agnostic approach.  Normally, I would try to mimic dvdxchap's format
	 * which would only cause problems down the road.  So, while it's one
	 * thing to simply add a function to export to that format, all information
	 * stored in here will simply contain chapter numbers, indexes, and lengths
	 * as it would normally be accessed: incrementally starting with one, and
	 * storing each chapter's length in seconds.
	 *
	 * dvdxchap's format is slightly different as the first chapter is really
	 * the starting point of the stream.  Or, 0 minutes, 0 seconds.  This means
	 * that in that format, the starting point of the second chapter would be
	 * equal to the length of the first chapter.
	 */

	class DVDTrack {
	
		private $device;
		private $lsdvd;
		private $id;
		private $track;
		private $num_chapters = 0;
		private $aspect_ratio;
		
		private $verbose = false;
		private $debug = false;
		
		public $chapters;
	
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
		
			if(is_null($this->xml)) {
				$exec = "lsdvd -Ox -v -a -s -c -t ".$this->getTrack()." ".$this->getDevice();
				$arr = shell::cmd($exec);
				$str = implode("\n", $arr);
				
				// Fix broken encoding on langcodes, standardize output
				$str = str_replace('Pan&Scan', 'Pan&amp;Scan', $str);
				$str = str_replace('P&S', 'P&amp;S', $str);
				$str = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $str);
				
				$this->xml = $str;
			}
			
			if(is_null($this->sxe)) {
				
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
				
				$chapter_number = 1;
				 
				foreach($this->sxe->track->chapter as $obj) {
					$length = (float)$obj->length;
					$this->chapters[$chapter_number] = array(
						'length' => $length,
						'name' => "",
					);
					
					$chapter_number++;
					
					$last_chapter_length = $length;
				}
				
				// I've seen this regularly on some sources, especially
				// cartoons where the last chapter is really short.  It
				// makes navigation annoying, because you want to skip
				// to the next entry in a playlist, and instead it
				// jumps to the last second or two of the episode.
				// So, I'm going to specify a hard limit, and if the
				// length of the last one is less than that, don't
				// add the chapter.
				
				// Current fixed length: two seconds
 				if($last_chapter_length < 2)
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
		
		function getNumAudioTracks() {
			if(is_null($this->num_audio_tracks))
				$this->lsdvd();
			
			return $this->num_audio_tracks;
		}
		
		/**
		 * Select the first audio stream with the preset langcode
		 */
		function setAudioIndex() {
			if(!$this->sxe)
				$this->lsdvd();
				
			if(!$this->langcode)
				$this->setLangCode();
			
			// Selecting the *correct* audio track can be tricky.  Generally,
			// you want the *first* one that is your language, with the
			// highest number of audio channels.  If there are two of the
			// same language and number of channels, then one of them is
			// likely to be an audio commentary track or score only.  In
			// those cases, pick the first one in the index.
			
			$max_channels = 0;
			$arr_tracks = array();
			
			// Look at all the channels, and for the ones that match the
			// language code, add it to an array to inspect.
			foreach($this->audio as $idx => $arr) {
				if($arr['langcode'] == $this->getLangCode()) {
					$max_channels = max($max_channels, $arr['channels']);
					$arr_tracks[$arr['stream_id']] = array('idx' => $idx, 'channels' => $arr['channels']);
				}
			}
			
			// For all the possible audio channels, find the first one with
			// the highest number of channels ordering by the stream_id.  This
			// should be the correct track.
 			foreach($arr_tracks as $arr) {
 				if($arr['channels'] == $max_channels) {
 					$this->audio_index = $arr['idx'];
 					break;
 				}
 			}

			// If there aren't any set, or if there is only one, set it to the default.
			if(count($this->audio) == 1 || is_null($this->audio_index)) {
				$this->audio_index = 1;
			}
			
		}
		
		function getAudio() {
			
			$audio_codec = $this->getAudioFormat();
			$num_channels = $this->getAudioChannels();
			
			if($audio_codec == "dts")
				$str = "DTS ";
			elseif($audio_codec == "ac3")
				$str = "Dolby ";
			else
				$str = $audio_codec;
			
			switch($num_channels) {
				case 1:
					$str .= 'Mono';
					break;
				case 2:
					$str .= 'Surround';
					break;
				case 4:
					$str .= 'SR';
				case 6:
					if($audio_codec == 'ac3')
						$str .= 'Digital ';
					$str .= ' 5.1';
					break;
				default:
					$str .= "$num_channels channels";
					break;
			}
			
			return $str;
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
		
		function getAudioLanguage() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['language'];
		}
		
		function getAudioLangCode() {
			if(!$this->audio_index)
				$this->setAudioIndex();
				
			return $this->audio[$this->getAudioIndex()]['langcode'];
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
		 
		function getNumSubtitles() {
			if(!$this->sxe)
				$this->lsdvd();
				
			if(!$this->langcode)
				$this->setLangCode();
			
			if($this->subtitles) {
				$this->num_subtitles = count($this->subtitles);
			} else {
				$this->num_subtitles = 0;
			}
			
			return $this->num_subtitles;
			
		}
		
		function hasSubtitles() {
			if($this->getNumSubtitles()) {
				if(is_null($this->getSubtitlesIndex()))
					return false;
				else
					return true;
			} else {
				return false;
			}
		}
		 
		function setSubtitlesIndex() {
			if(!$this->sxe)
				$this->lsdvd();
				
			if(!$this->langcode)
				$this->setLangCode();
			
			// Subtitles
			if($this->getNumSubtitles()) {
				foreach($this->subtitles as $idx => $arr) {
					if($arr['langcode'] == $this->getLangCode() && !$this->subtitle_index)
						$this->subtitle_index = $idx;
				}
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
		
		public function secondsToHMS($float) {
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
			// Remove 1 from the index, since
			// in dvdxchap, everything is one chapter behind.
 			$offset = $starting_chapter - 1;
			
			// How many chapters to fetch
 			$length = $ending_chapter - $starting_chapter + 1;
			
			// Preserve the keys when grabbing, as we index the key as chapter #
 			$arr_slice = array_slice($this->chapters, $offset, $length, true);

			// Manually add the first chapter
			$str = "CHAPTER01=00:00:00.000\n";
			$chapter_name = $this->getChapterName($key);
			if(!$chapter_name)
				$chapter_name = "Chapter 1";
			$str .= "CHAPTER01NAME=$chapter_name\n";
			$chapter_number = 2;
			
			$start_pos = 0;
			
			foreach($arr_slice as $key => $arr) {
				$start_pos = bcadd($start_pos, $this->getChapterLength($key));
				$hms = $this->secondsToHMS($start_pos);
				
				$pad = str_pad($chapter_number, 2, 0, STR_PAD_LEFT);
				
				$str .= "CHAPTER$pad=$hms\n";
				
				// See if the chapter name has something manually set.
				// If not, just name it "Chapter X"
				$chapter_name = $this->getChapterName($key);
				if(!$chapter_name)
					$chapter_name = "Chapter $chapter_number";
				
				$str .= "CHAPTER${pad}NAME=$chapter_name\n";
				
				$chapter_number++;
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
			
			return trim($this->chapters[$chapter]['name']);
		
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
			$str = str_replace(" ", "_", $str);
			
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
			
			$track_number = $this->getTrack();
			$device = $this->getDevice();
			$basename = $this->getBasename();
			$slang = $this->getLangCode();
			$starting_chapter = $this->getStartingChapter();
			$ending_chapter = $this->getEndingChapter();
			
			$flags[] = "dvd://$track_number";
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
