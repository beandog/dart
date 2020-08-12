<?php

	class CD {

		public $device;
		public $dvd_info;
		public $is_iso;
		public $debug;
		public $dry_run;
		public $binary = '/usr/bin/dvd_info';

		public $opened;

		// DVD
		public $dvdread_id;
		public $title;
		public $title_tracks;
		public $longest_track;
		public $provider_id;
		public $size;
		public $side;

		// DVD Track
		public $title_track;
		public $title_track_index;
		public $title_track_length;
		public $title_track_msecs;
		public $title_track_vts;
		public $title_track_ttn;
		public $title_track_chapters;
		public $title_track_audio_tracks;
		public $title_track_subtitle_tracks;
		public $title_track_cells;

		// DVD Video
		public $video_codec;
		public $video_format;
		public $video_aspect_ratio;
		public $video_width;
		public $video_height;
		public $video_angles;
		public $video_fps;

		// DVD Audio
		public $audio_track;
		public $audio_active;
		public $audio_track_lang_code;
		public $audio_track_codec;
		public $audio_track_channels;
		public $audio_track_stream_id;

		// DVD Subtitle
		public $subtitle_track;
		public $subtitle_active;
		public $subtitle_track_lang_code;
		public $subtitle_track_stream_id;

		// DVD Chapter
		public $chapter;
		public $chapter_length;
		public $chapter_seconds;
		public $chapter_msecs;
		public $chapter_filesize;

		// DVD Cell
		public $cell;
		public $cell_length;
		public $cell_seconds;
		public $cell_msecs;
		public $cell_first_sector;
		public $cell_last_sector;

		// CD
		public $cd_id;
		public $cddb_id;

		function __construct($device = "/dev/dvd", $debug = false, $dry_run = false) {

			$this->device = realpath($device);
			$this->debug = boolval($debug);
			$this->dry_run = boolval($dry_run);

			if(!file_exists($this->device)) {
				$this->opened = false;
				return null;
			}

			$dirname = dirname($this->device);
			if($dirname != "/dev")
				$this->is_iso = true;
			else
				$this->is_iso = false;

			// Run dvd_info first and return if it passes or not
			$bool = $this->dvd_info();

			if($bool === false)
				return false;
			else
				$this->opened = true;

			return true;

		}

		/** Hardware **/

		private function dvd_info() {

			$arg_device = escapeshellarg($this->device);
			$cmd = "cd-discid $arg_device 2> /dev/null";

			if($this->debug)
				echo "* Executing: $cmd\n";

			exec($cmd, $output, $retval);

			if($retval !== 0 || !count($output)) {
				echo "* dvd_info(): FAILED\n";
				return false;
			}

			$str = implode('', $output);

			$cddb_id = substr($str, 0, 8);
			$this->dvdread_id = $cddb_id;
			$this->dvd_info['dvd']['dvdread id'] = $cddb_id;

			$this->title = '';

			return true;

			/*
			// TODO fetch E: ID_CDROM_MEDIA_TRACK_COUNT_AUDIO=13
			$arg_device = escapeshellarg($this->device);
			$command = "udevadm info $arg_device";
			$return = 0;
			exec($command, $arr, $return);

			if(in_array("E: ID_CDROM_MEDIA_DVD=1", $arr))
				$this->disc_type = "dvd";
			elseif(in_array("E: ID_CDROM_MEDIA_BD=1", $arr))
				$this->disc_type = "bluray";
			elseif(in_array("E: ID_CDROM_MEDIA_CD=1", $arr))
				$this->disc_type = "cd";
			*/


		}

		/** Metadata **/

		// Use dvd_info to get dvdread id
		public function dvdread_id() {

			if(!$this->opened)
				return null;

			$dvdread_id = $this->dvd_info['dvd']['dvdread id'];

			if(strlen($dvdread_id) != 8)
				return false;

			return $dvdread_id;

		}

		/** Tracks **/
		private function title_tracks() {

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info)) {

				if($this->debug) {
					echo "* DVD has no tracks!!! This is bad.\n";
				}

				return 0;

			}

			// dvd_info will skip over tracks that have invalid IFOs, so the
			// number of array entries will not always equal the number of
			// actual tracks in some cases. Because of that, always use the
			// number of tracks from the original DVD information, and do not
			// simply count the number of tracks in the JSON array.
			//
			// Example: The Black Cauldron (dvdread id b91668d201f6659e049caa4abf0a71b6)
			// has 99 title tracks, but tracks 2-8, and 97-99 have invalid IFOs.

			$title_tracks = $this->dvd_info['dvd']['tracks'];

			return $title_tracks;

		}

		public function title_track_index() {

			$title_track_index = array();

			foreach($this->dvd_info['tracks'] as $key => $arr) {

				$title_track = $arr['track'];

				$title_track_index[$title_track] = $key;

			}

			return $title_track_index;

		}

		public function longest_track() {

			if(!$this->opened)
				return null;

			// First make sure we can get tracks
			if(!array_key_exists('tracks', $this->dvd_info)) {

				if($this->debug) {
					echo "* DVD has no tracks!!! This is bad.\n";
				}

				return null;

			}

			// Loop through all the lengths of the tracks, and set the one
			// with the longest amount of msecs to the longest. If a following
			// one has equal length than an earlier one, then default to the first
			// one with that maximum length.

			$longest_track = 1;
			$longest_track_msecs = 0;

			foreach($this->dvd_info['tracks'] as $arr) {

				if(array_key_exists('msecs', $arr) && $arr['msecs'] > $longest_track_msecs) {

					$longest_track = $arr['track'];
					$longest_track_msecs = $arr['msecs'];

				}

			}

			return $longest_track;

		}

		public function size() {

			return 0;

		}

		/** CD Track **/

		public function load_title_track($title_track) {

			$title_track = abs(intval($title_track));

			if($title_track === 0 || $title_track > $this->title_tracks) {
				return false;
			}

			// dvd_info skips over title tracks that cannot be opened, but
			// sets the 'track' value individually. Check that variable to
			// see if we actually can open it.
			$title_track_key = null;
			foreach($this->dvd_info['tracks'] as $key => $arr) {

				if($this->dvd_info['tracks'][$key]['track'] == $title_track) {
					$title_track_key = $key;
					break;
				}

			}

			if(is_null($title_track_key))
				return false;

			$this->title_track = $title_track;
			$this->title_track_info = $this->dvd_info['tracks'][$title_track_key];

			$this->title_track_length = $this->title_track_length();
			$this->title_track_msecs = $this->title_track_msecs();
			$this->title_track_seconds = $this->title_track_seconds();
			$this->title_track_vts = $this->title_track_vts();
			$this->title_track_ttn = $this->title_track_ttn();
			$this->title_track_chapters = $this->title_track_chapters();
			$this->title_track_audio_tracks = $this->title_track_audio_tracks();
			$this->title_track_subtitle_tracks = $this->title_track_subtitle_tracks();
			$this->title_track_cells = $this->title_track_cells();
			$this->title_track_filesize = $this->title_track_filesize();

			$this->video_codec = $this->video_codec();
			$this->video_format = $this->video_format();
			$this->video_aspect_ratio = $this->video_aspect_ratio();
			$this->video_width = $this->video_width();
			$this->video_height = $this->video_height();
			$this->video_angles = $this->video_angles();
			$this->video_fps = $this->video_fps();

			return true;

		}

		private function title_track_length() {
			return $this->dvd_info_string($this->title_track_info, 'length');
		}

		private function title_track_msecs() {
			return $this->dvd_info_number($this->title_track_info, 'msecs');
		}

		private function title_track_seconds() {

			$msecs = abs(intval($this->title_track_msecs()));
			$seconds = 0;
			if($msecs)
				$seconds = floatval(bcdiv($msecs, 1000, 3));

			return $seconds;

		}

	}

