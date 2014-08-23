<?php

	require_once dirname(__FILE__)."/class.dvd.php";

	class DVDTrack extends DVD {

		private $device;
		private $track;

		private $dvdnav = false;

		public $chapters;
		public $cells;

		public $opened;

		function __construct($device = "/dev/dvd", $track = 1, $debug = false) {

			if($device != $this->device || !$this->dvd_info)
				parent::__construct($device, $debug);

			$track = abs(intval($track));
			if($track === 0)
				$track = 1;

			if($track > $this->tracks)
				$this->opened = false;

			bcscale(3);

			$this->title_track_info = $this->dvd_info['tracks'][$track - 1];

			$this->title_track = $track;

			$this->title_track_length = $this->title_track_length();
			$this->title_track_msecs = $this->title_track_msecs();
			$this->title_track_vts = $this->title_track_vts();
			$this->title_track_ttn = $this->title_track_ttn();
			$this->title_track_chapters = $this->title_track_chapters();
			$this->title_track_cells = $this->title_track_cells();
			$this->title_track_audio_tracks = $this->title_track_audio_tracks();
			$this->title_track_subtitles = $this->title_track_subtitles();

			$this->video_codec = $this->video_codec();
			$this->video_format = $this->video_format();
			$this->video_aspect_ratio = $this->video_aspect_ratio();
			$this->video_width = $this->video_width();
			$this->video_height = $this->video_height();
			$this->video_angles = $this->video_angles();
			$this->video_fps = $this->video_fps();

			print_r($this);

			return true;

		}

		/** Hardware **/
		private function getProtocol() {
			if($this->dvdnav)
				return "dvdnav://";
			else
				return "dvd://";
		}

		/** Metadata **/

		public function title_track_length() {
			return $this->dvd_info_string($this->title_track_info, 'length');
		}

		public function title_track_msecs() {
			return $this->dvd_info_number($this->title_track_info, 'msecs');
		}

		public function title_track_vts() {
			return $this->dvd_info_number($this->title_track_info, 'vts');
		}

		public function title_track_ttn() {
			return $this->dvd_info_number($this->title_track_info, 'vts');
		}

		public function title_track_audio_tracks() {

			if(!array_key_exists('audio', $this->title_track_info))
				return 0;

			return count($this->title_track_info['audio']);

		}

		public function title_track_chapters() {

			if(!array_key_exists('chapters', $this->title_track_info))
				return 0;

			return count($this->title_track_info['chapters']);

		}

		public function title_track_cells() {

			if(!array_key_exists('cells', $this->title_track_info))
				return 0;

			return count($this->title_track_info['cells']);

		}


		/** Video **/
		public function video_codec() {
			return $this->dvd_info_string($this->title_track_info['video'], 'codec');
		}

		public function video_format() {
			return $this->dvd_info_string($this->title_track_info['video'], 'format');
		}

		public function video_aspect_ratio() {
			return $this->dvd_info_string($this->title_track_info['video'], 'aspect ratio');
		}

		public function video_width() {
			return $this->dvd_info_number($this->title_track_info['video'], 'width');
		}

		public function video_height() {
			return $this->dvd_info_number($this->title_track_info['video'], 'height');
		}

		public function video_angles() {
			return $this->dvd_info_number($this->title_track_info['video'], 'angles');
		}

		public function video_fps() {
			return $this->dvd_info_number($this->title_track_info['video'], 'fps');
		}

	}

?>
