<?

	class DVDVOB {
	
		function __construct($vob = "movie.vob") {
		
			$this->setFilename($vob);
			$this->aid = 128;
			$this->debug = false;
		
		}
		
		function setFilename($vob) {
			$this->filename = $vob;
		}
		
		function getFilename() {
			return $this->filename;
		}
		
		function setAID($int = 128) {
			$int = abs(intval($int));
			$this->aid = $int;
		}
		
		function getAID() {
			return $this->aid;
		}
		
		function setDebug($bool = true) {
			$this->debug = (boolean)$bool;
		}

		/**
		* Extract the raw video stream from a media file
		*
		* @param string destination filename
		*/
		function rawvideo($dest) {
		
			$flags = array('"'.$this->getFilename().'"', "-ovc copy", "-of rawvideo", "-nosound", "-quiet", "-o \"$dest\"");
			
			$str = "mencoder ".implode(' ', $flags);
			
			if($this->debug)
				shell::msg("Executing: $str");
			
			$start = time();
			shell::cmd($str, !$this->debug);
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
			
		}
		
		/**
		* Extract the raw audio stream from a media file
		*
		* @param string destination filename
		*/
		function rawaudio($dest) {
		
			$aid = abs(intval($aid));
			
			$flags = array('"'.$this->getFilename().'"', "-oac copy", "-of rawaudio", "-ovc frameno", "-quiet", "-aid ".$this->getAID(), "-o \"$dest\"");
			
			$str = "mencoder ".implode(' ', $flags);
			
			if($this->debug)
				shell::msg("Executing: $str");
			
			$start = time();
			shell::cmd($str, !$this->debug);
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
		
		}
		
		/**
		 * Extract the SRT subtitles from a media file
		 *
		 * @param string destination filename
		 */
		 function dumpSRT() {
		 	
		 	$str = "ccextractor --no_progress_bar \"".$this->getFilename()."\"";
		 	
		 	if($this->debug)
				shell::msg("Executing: $str");
			
			$start = time();
			shell::cmd($str, !$this->debug);
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
		 	
		 }
	}
?>