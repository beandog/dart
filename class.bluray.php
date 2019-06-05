<?php

	class Bluray {

		public $device;
		public $is_iso;
		public $debug;
		public $dry_run;
		public $binary = '/usr/bin/bluray_info';

		public $opened;

		// Bluray
		public $dvdread_id;
		public $title;
		public $title_tracks;
		public $playlists;
		public $longest_playlist;
		public $main_playlist;
		public $size;

		// Bluray Playlist
		public $playlist_track;
		public $playlist_track_filesize;
		public $playlist_track_length;
		public $playlist_track_msecs;
		public $playlist_track_seconds;
		public $playlist_track_chapters;
		public $playlist_track_audio_tracks;
		public $playlist_track_subtitle_tracks;

		// Bluray Video
		public $video_track;
		public $video_stream;
		public $video_format;
		public $video_aspect_ratio;
		public $video_fps;

		// Bluray Audio
		public $audio_track;
		public $audio_stream;
		public $audio_track_lang_code;
		public $audio_track_codec;
		public $audio_track_format;
		public $audio_track_channels;

		// Bluray Subtitle
		public $subtitle_track;
		public $subtitle_stream;
		public $subplaylist_track_lang_code;

		// Bluray Chapter
		public $chapter;
		public $chapter_length;
		public $chapter_seconds;
		public $chapter_msecs;

		// All
		public $playlist_track_info;

		function __construct($device = "/dev/bluray", $debug = false, $dry_run = false) {

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

			// Run bluray_info first and return if it passes or not
			$bool = $this->bluray_json();

			if($bool === false)
				return false;
			else
				$this->opened = true;

			$this->title = $this->title();
			$this->longest_playlist = $this->longest_playlist();
			$this->main_playlist = $this->main_playlist();
			$this->playlist_tracks = $this->playlist_tracks();
			$this->playlists = count($this->playlist_tracks);
			$this->title_tracks = $this->title_tracks();
			// $this->size = $this->size();

			return true;

		}

		/** Hardware **/

		private function bluray_json() {

			if(file_exists("/usr/local/bin/bluray_info"))
				$this->binary = "/usr/local/bin/bluray_info";
			if(file_exists("/usr/bin/bluray_info"))
				$this->binary = "/usr/bin/bluray_info";

			$arg_device = escapeshellarg($this->device);
			$cmd = $this->binary." --json $arg_device 2> /dev/null";

			if($this->debug)
				echo "* Executing: $cmd\n";

			exec($cmd, $output, $retval);

			if($retval !== 0 || !count($output)) {
				echo "* bluray_info(): FAILED\n";
				return false;
			}

			$str = implode(" ", $output);

			// Create an assoc. array
			$json = json_decode($str, true);

			if(is_null($json)) {
				echo "* bluray_info(): json_decode() FAILED\n";
				return false;
			}

			$this->dvd_info = $json;

			// Uniq identifier is a sha1 sum of the main playlist number followed by the
			// filesize of the main playlist as a decimal.
			// sha1 is used to generate a 40 character string, so it visually
			// stands out in the database for dvdread_id

			$main_title = $this->dvd_info['bluray']['main title'];
			$main_playlist = $this->dvd_info['bluray']['main playlist'];
			$main_filesize = $this->dvd_info['titles'][$main_title - 1]['filesize'];

			$dvdread_id = sha1("$main_playlist.$main_filesize");

			$this->dvdread_id = $dvdread_id;

			return true;

		}

		protected function dvd_info_number($arr, $key) {

			if(!array_key_exists($key, $arr))
				return 0;
			else
				return $arr[$key];

		}

		/** Metadata **/

		// Use bluray_info to get dvdread id
		public function dvdread_id() {

			if(!$this->opened)
				return null;

			$dvdread_id = $this->dvd_info['bluray']['dvdread id'];

			if(strlen($dvdread_id) != 32)
				return false;

			return $dvdread_id;

		}

		/**
		 * Get the disc title
		 */
		private function title() {

			if(!$this->opened)
				return null;

			$title = $this->dvd_info['bluray']['disc name'];

			$title = trim($title);

			return $title;
		}

		/** Tracks **/
		private function title_tracks() {

			// First make sure we can get tracks
			if(!array_key_exists('playlists', $this->dvd_info)) {

				if($this->debug) {
					echo "* Blu-ray has no titles!!! This is bad.\n";
				}

				return 0;

			}

			$title_tracks = count($this->dvd_info['playlists']);

			return $title_tracks;

		}

		/**
		 * Get the longest playlist
		 */
		private function longest_playlist() {

			if(!$this->opened)
				return null;

			$longest_playlist = $this->dvd_info['bluray']['longest playlist'];

			return $longest_playlist;

		}

		/**
		 * Get the main playlist
		 */
		private function main_playlist() {

			if(!$this->opened)
				return null;

			$main_playlist = $this->dvd_info['bluray']['main playlist'];

			return $main_playlist;

		}

		/** Tracks **/
		private function playlist_tracks() {

			$this->dvd_info['playlists'] = array();

			// First make sure we can get tracks
			if(!array_key_exists('titles', $this->dvd_info)) {

				if($this->debug) {
					echo "* Blu-ray has no titles!!! This is bad.\n";
				}

				return 1;

			}

			foreach($this->dvd_info['titles'] as $arr) {
				$this->dvd_info['playlists'][$arr['playlist']] = $arr;
			}

			// Completely move from titles to playlists
			unset($this->dvd_info['titles']);

			ksort($this->dvd_info['playlists'], SORT_NATURAL);

			return $this->dvd_info['playlists'];

		}

		/**
		 * Get the size of the filesystem on the dev
				$num_sectors = file_get_contents("/sys/block/$block_device/size");
				$b_size = $num_sectors * 512;
			}

			if(!$b_size)
				return 0;

			$kb_size = $b_size / 1024;
			$mb_size = intval($kb_size / 1024);

			return $mb_size;

		}

		/** DVD Track **/

		public function load_playlist_track($playlist) {

			$playlist = abs(intval($playlist));

			$this->playlist_track = $playlist;
			$this->playlist_track_info = $this->dvd_info['playlists'][$playlist];

			$this->playlist_track_length = $this->playlist_track_length();
			$this->playlist_track_msecs = $this->playlist_track_msecs();
			$this->playlist_track_seconds = $this->playlist_track_seconds();
			$this->playlist_track_filesize = $this->playlist_track_filesize();

			$this->playlist_track_chapters = $this->playlist_track_chapters();
			$this->playlist_track_audio_tracks = $this->playlist_track_audio_tracks();
			$this->playlist_track_subtitle_tracks = $this->playlist_track_subtitle_tracks();

			$this->video_codec = $this->video_codec();
			$this->video_stream = $this->video_stream();
			$this->video_format = $this->video_format();
			$this->video_aspect_ratio = $this->video_aspect_ratio();
			$this->video_fps = $this->video_fps();

			return true;

		}

		private function playlist_track_length() {
			return $this->playlist_track_info['length'];
		}

		private function playlist_track_msecs() {
			return $this->dvd_info_number($this->playlist_track_info, 'msecs');
		}

		private function playlist_track_seconds() {

			$msecs = abs(intval($this->playlist_track_msecs()));
			$seconds = 0;
			if($msecs)
				$seconds = floatval(bcdiv($msecs, 1000, 3));

			return $seconds;

		}

		private function playlist_track_filesize() {
			return $this->dvd_info_number($this->playlist_track_info, 'filesize');
		}

		private function playlist_track_audio_tracks() {

			if(!array_key_exists('audio', $this->playlist_track_info))
				return 0;

			return count($this->playlist_track_info['audio']);

		}

		private function playlist_track_subtitle_tracks() {

			if(!array_key_exists('subtitles', $this->playlist_track_info))
				return 0;

			return count($this->playlist_track_info['subtitles']);

		}

		private function playlist_track_chapters() {

			if(!array_key_exists('chapters', $this->playlist_track_info))
				return 0;

			return count($this->playlist_track_info['chapters']);

		}

		private function video_codec() {
			if(count($this->playlist_track_info['video']))
				return strval($this->playlist_track_info['video'][0]['codec']);
			else
				return '';
		}

		private function video_stream() {
			if(count($this->playlist_track_info['video']))
				return strval($this->playlist_track_info['video'][0]['stream']);
			else
				return '';
		}

		private function video_format() {
			if(count($this->playlist_track_info['video']))
				return strval($this->playlist_track_info['video'][0]['format']);
			else
				return '';
		}

		private function video_aspect_ratio() {
			if(count($this->playlist_track_info['video']))
				return strval($this->playlist_track_info['video'][0]['aspect ratio']);
			else
				return '';
		}

		private function video_fps() {
			if(count($this->playlist_track_info['video']))
				return strval($this->playlist_track_info['video'][0]['framerate']);
			else
				return '';
		}

		/** DVD Audio Track **/

		public function load_audio_track($playlist, $audio_track) {

			$playlist = abs(intval($playlist));
			$audio_track = abs(intval($audio_track));

			$playlist_loaded = $this->load_playlist_track($playlist);

			if(!$playlist_loaded || $audio_track === 0 || $audio_track > $this->playlist_track_audio_tracks) {

				return false;
			}

			$this->audio_track = $audio_track;
			$this->audio_track_active = $this->audio_track_active();
			$this->audio_track_lang_code = $this->audio_track_lang_code();
			$this->audio_track_codec = $this->audio_track_codec();
			$this->audio_track_channels = $this->audio_track_channels();

			return true;

		}

		private function audio_track_lang_code() {
			return $this->audio_track_info['language'];
		}

		private function audio_track_codec() {
			return $this->audio_track_info['codec'];
		}

		/** DVD Subtitle Track **/

		public function load_subtitle_track($playlist, $subtitle_track) {

			$playlist = abs(intval($playlist));
			$subtitle_track = abs(intval($subtitle_track));

			$playlist_loaded = $this->load_playlist_track($playlist);

			if(!$playlist_loaded || $subtitle_track === 0 || $subtitle_track > $this->playlist_track_subtitle_tracks) {

				return false;
			}

			$this->subtitle_track = $subtitle_track;
			$this->subplaylist_track_active = $this->subplaylist_track_active();
			$this->subplaylist_track_lang_code = $this->subplaylist_track_lang_code();

			return true;

		}

		private function subplaylist_track_lang_code() {
			return $$this->subplaylist_track_info['language'];
		}

		/** DVD Chapter **/

		public function load_chapter($playlist, $chapter) {

			$playlist = abs(intval($playlist));
			$chapter = abs(intval($chapter));

			$playlist_loaded = $this->load_playlist_track($playlist);

			if(!$playlist_loaded || $chapter === 0 || $chapter > $this->playlist_track_chapters) {

				return false;
			}

			$this->chapter = $chapter;
			$this->chapter_length = $this->chapter_length();
			$this->chapter_msecs = $this->chapter_msecs();
			$this->chapter_seconds = $this->chapter_seconds();

			return true;

		}

		private function chapter_length() {
			return $this->chapter_info['length'];
		}

		private function chapter_msecs() {
			return $this->dvd_info_number($this->chapter_info, 'msecs');
		}

		private function chapter_seconds() {

			$msecs = abs(intval($this->chapter_msecs()));
			$seconds = 0;
			if($msecs)
				$seconds = floatval(bcdiv($msecs, 1000, 3));

			return $seconds;

		}

	}
