<?php

	class DVDVOB {

		function __construct($vob = "movie.vob") {

			$this->setFilename($vob);
			$this->aid = 128;
			$this->debug = false;

		}

		function setFilename($vob) {
			$this->filename = $vob;
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

			$flags = array(escapeshellarg($this->filename), "-ovc copy", "-of rawvideo", "-nosound", "-quiet", "-o ".escapeshellarg($dest));

			$cmd = "mencoder ".implode(' ', $flags);

			if($this->debug)
				echo "Executing: $str\n";

			$start = time();

			//FIXME needs return value checking
			exec($cmd, $output, $retval);

			$finish = time();

			/*
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				echo "Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s";
			}
			*/

			return $retval;

		}

		/**
		* Extract the raw audio stream from a media file
		*
		* @param string destination filename
		*/
		function rawaudio($dest) {

			$flags = array(escapeshellarg($this->filename), "-oac copy", "-of rawaudio", "-ovc frameno", "-quiet", "-aid ".$this->getAID(), "-o ".escapeshellarg($dest));

			$cmd = "mencoder ".implode(' ', $flags);

			if($this->debug)
				echo "Executing: $str";

			// FIXME check return values
			exec($cmd, $output, $retval);

			return $retval;

		}

		/**
		 * Extract the SRT subtitles from a media file
		 *
		 * @param string destination filename
		 */
		 function dumpSRT() {

		 	$cmd = "ccextractor -unicode -nomyth -ps ".escapeshellarg($this->filename);

		 	if($this->debug)
				echo "Executing: $str\n";

			// FIXME check return values
			exec($cmd, $output, $retval);

			return $retval;

		 }
	}
?>
