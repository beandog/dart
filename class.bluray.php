<?php

	class Bluray {

		public $device;
		public $is_iso;
		public $debug;
		public $binary = '/usr/bin/bluray_info';

		public $opened;

		// Bluray
		public $dvdread_id;
		public $disc_id;
		public $disc_title;
		public $title;
		public $title_tracks;
		public $bd_playlists;
		public $playlists;
		public $longest_playlist;
		public $main_playlist;
		public $size;

		// Bluray Playlist
		public $playlist;
		public $playlist_filesize;
		public $playlist_blocks;
		public $playlist_length;
		public $playlist_msecs;
		public $playlist_seconds;
		public $title_track_chapters;
		public $title_track_audio_tracks;
		public $title_track_subtitle_tracks;

		// Bluray Video
		public $video_track;
		public $video_stream;
		public $video_resolution;
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
		public $subtitle_track_lang_code;

		// Bluray Chapter
		public $chapter;
		public $chapter_length;
		public $chapter_seconds;
		public $chapter_msecs;
		public $chapter_filesize;
		public $chapter_blocks;

		// Bluray Metadata
		public $provider_data;
		public $bdinfo_titles;
		public $hdmv_titles;
		public $bdj_titles;

		// All
		public $playlist_info;

		function __construct($device = "/dev/bluray", $debug = false) {

			$this->device = realpath($device);
			$this->debug = boolval($debug);

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
			$this->playlists = $this->playlists();
			$this->title_tracks = $this->title_tracks();
			$this->size = $this->size();

			return true;

		}

		/** Hardware **/

		private function bluray_json() {

			if(file_exists("/usr/local/bin/bluray_info"))
				$this->binary = "/usr/local/bin/bluray_info";
			if(file_exists("/usr/bin/bluray_info"))
				$this->binary = "/usr/bin/bluray_info";

			$arg_device = escapeshellarg($this->device);
			$cmd = $this->binary." --duplicates --json $arg_device";
			if(!$this->debug)
				$cmd .= " 2> /dev/null";

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
			$this->disc_id = strtolower($this->dvd_info['bluray']['disc id']);
			$this->disc_name = trim($this->dvd_info['bluray']['disc name']);
			$this->provider_data = trim($this->dvd_info['bluray']['provider data']);
			$this->bdinfo_titles = $this->dvd_info['bluray']['bdinfo titles'];
			$this->hdmv_titles = $this->dvd_info['bluray']['hdmv titles'];
			$this->bdj_titles = $this->dvd_info['bluray']['bd-j titles'];

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
		 *
		 * Blu-ray discs title is optional, but will have a UDF volume name. The
		 * volume name will only be accessible if the disc is imported directly, though.
		 */
		private function title() {

			if(!$this->opened)
				return null;

			$udf_title = trim($this->dvd_info['bluray']['udf title']);

			return $udf_title;

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
		private function playlists() {

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
				$this->bd_playlists[] = $arr['playlist'];
			}

			// Completely move from titles to playlists
			unset($this->dvd_info['titles']);

			ksort($this->dvd_info['playlists'], SORT_NATURAL);

			sort($this->bd_playlists);

			return $this->dvd_info['playlists'];

		}

		/**
		 * Get the size of the filesystem on the device
		 */
		public function size() {

			// Running stat on a single image file not supported right now
			$kb_size = 0;

			$dirname = dirname($this->device);
			if($dirname == "/dev") {

				$block_device = basename($this->device, "/dev/");
				$num_sectors = intval(trim(file_get_contents("/sys/block/$block_device/size")));
				$b_size = $num_sectors * 512;
				$kb_size = $b_size / 1024;

			} elseif(is_dir($this->device)) {

				exec("du -s ".escapeshellarg($this->device), $output, $retval);

				if($retval == 0)
					$kb_size = intval(current(preg_split("/\s/", current($output))));

			}

			$mb_size = intval($kb_size / 1024);

			return $mb_size;

		}

		/** DVD Track **/

		public function load_playlist($playlist) {

			$playlist = abs(intval($playlist));

			$this->playlist = $playlist;
			$this->playlist_info = $this->dvd_info['playlists'][$playlist];

			$this->playlist_length = $this->playlist_length();
			$this->playlist_msecs = $this->playlist_msecs();
			$this->playlist_seconds = $this->playlist_seconds();
			$this->playlist_filesize = $this->playlist_filesize();
			$this->playlist_blocks = $this->playlist_blocks();

			$this->title_track_chapters = $this->title_track_chapters();
			$this->title_track_audio_tracks = $this->title_track_audio_tracks();
			$this->title_track_subtitle_tracks = $this->title_track_subtitle_tracks();

			$this->video_codec = $this->video_codec();
			$this->video_stream = $this->video_stream();
			$this->video_resolution = $this->video_resolution();
			$this->video_aspect_ratio = $this->video_aspect_ratio();
			$this->video_fps = $this->video_fps();

			return true;

		}

		private function playlist_length() {
			return $this->playlist_info['length'];
		}

		private function playlist_msecs() {
			return $this->dvd_info_number($this->playlist_info, 'msecs');
		}

		private function playlist_seconds() {

			$msecs = abs(intval($this->playlist_msecs()));
			$seconds = 0;
			// Should be div by 1000, current bug in bluray_info
			if($msecs)
				$seconds = floatval(bcdiv($msecs, 100, 3));

			return $seconds;

		}

		private function playlist_filesize() {
			return $this->dvd_info_number($this->playlist_info, 'filesize');
		}

		private function playlist_blocks() {
			// Temporary fix while 1.13 is still being used to track my BDs
			/*
			return $this->dvd_info_number($this->playlist_info, 'blocks');
			*/
			return $this->playlist_filesize() / 192;
		}

		private function title_track_audio_tracks() {

			if(!array_key_exists('audio', $this->playlist_info))
				return 0;

			return count($this->playlist_info['audio']);

		}

		private function title_track_subtitle_tracks() {

			if(!array_key_exists('subtitles', $this->playlist_info))
				return 0;

			return count($this->playlist_info['subtitles']);

		}

		private function title_track_chapters() {

			if(!array_key_exists('chapters', $this->playlist_info))
				return 0;

			return count($this->playlist_info['chapters']);

		}

		private function video_codec() {
			if(count($this->playlist_info['video']))
				return strval($this->playlist_info['video'][0]['codec']);
			else
				return '';
		}

		private function video_stream() {
			if(count($this->playlist_info['video']))
				return strval($this->playlist_info['video'][0]['stream']);
			else
				return '';
		}

		private function video_resolution() {
			if(count($this->playlist_info['video']))
				return strval($this->playlist_info['video'][0]['format']);
			else
				return '';
		}

		private function video_aspect_ratio() {
			if(count($this->playlist_info['video']))
				return strval($this->playlist_info['video'][0]['aspect ratio']);
			else
				return '';
		}

		private function video_fps() {
			if(count($this->playlist_info['video']))
				return strval($this->playlist_info['video'][0]['framerate']);
			else
				return '';
		}

		/** DVD Audio Track **/

		public function load_audio_track($playlist, $audio_track) {

			$playlist = abs(intval($playlist));
			$audio_track = abs(intval($audio_track));

			$this->load_playlist($playlist);

			$this->audio_track = $audio_track;
			$this->audio_track_info = $this->playlist_info['audio'][$this->audio_track - 1];
			$this->audio_track_active = 1;
			$this->audio_track_lang_code = $this->audio_track_info['language'];
			$this->audio_track_codec = $this->audio_track_info['codec'];
			$this->audio_track_stream_id = $this->audio_track_info['stream'];

			// bluray_info doesn't detect # audio channels above stereo
			if($this->audio_track_info['format'] == 'mono')
				$this->audio_track_channels = 1;
			else if($this->audio_track_info['format'] == 'stereo')
				$this->audio_track_channels = 2;
			else
				$this->audio_track_channels = 0;

			return true;

		}

		/** DVD Subtitle Track **/

		public function load_subtitle_track($playlist, $subtitle_track) {

			$playlist = abs(intval($playlist));
			$subtitle_track = abs(intval($subtitle_track));

			$this->load_playlist($playlist);

			$this->subtitle_track = $subtitle_track;
			$this->subtitle_track_info = $this->playlist_info['subtitles'][$this->subtitle_track - 1];
			$this->subtitle_track = $subtitle_track;
			$this->subtitle_track_active = 1;
			$this->subtitle_track_lang_code = $this->subtitle_track_info['language'];
			$this->subtitle_track_stream_id = $this->subtitle_track_info['stream'];

			return true;

		}

		/** DVD Chapter **/

		public function load_chapter($playlist, $chapter) {

			$playlist = abs(intval($playlist));
			$chapter = abs(intval($chapter));

			$playlist_loaded = $this->load_playlist($playlist);

			if(!$playlist_loaded || $chapter === 0 || $chapter > $this->title_track_chapters) {

				return false;
			}

			$this->chapter = $chapter;
			$this->chapter_info = $this->playlist_info['chapters'][$this->chapter - 1];
			$this->chapter_seconds = floatval(bcdiv($this->chapter_info['duration'], 100, 2));
			$this->chapter_filesize = $this->chapter_info['filesize'];
			// Temporarily disabled while 1.13 is still main release and BDs use that index
			// $this->chapter_blocks = $this->chapter_info['blocks'];
			$this->chapter_blocks = $this->chapter_info['filesize'] / 192;

			return true;

		}

		/** Backup using MakeMKV **/
		public function dvdbackup($filename, $logfile = '/dev/null') {

			$bool = false;

			if(!$this->opened)
				return null;

			$logfile = realpath($logfile);

			if($this->debug) {
				echo "* dvd->dvdbackup($filename)\n";
				echo "* Logging to $logfile\n";
			}

			// tobe
			if(realpath($this->device) == '/dev/sr0')
				$arg_input = 0;
			else
				$arg_input = 1;

			$arg_logfile = escapeshellarg($logfile);

			$target_dir = dirname($filename);
			$target_rip = $target_dir."/".basename($filename, '.iso').".R1p";
			$arg_name = basename($target_rip);
			$arg_output = escapeshellarg($arg_name);

			if($this->debug) {
				echo "* input: $arg_input\n";
				echo "* output: $arg_output\n";
				echo "* name: $arg_name\n";
			}

			$cmd = "firejail --net=none makemkvcon --noscan --minlength=0 -r backup --decrypt disc:$arg_input $arg_output 2>&1 | tee $logfile";
			if($this->debug)
				echo "* Executing: $cmd\n";

			$success = true;
			$retval = 0;

			passthru($cmd, $retval);

			if($this->debug)
				echo "* makemkvcon return value: $retval\n";

			if($retval !== 0)
				$success = false;

			$bool = rename($target_dir.'/'.$arg_name, $filename);

			if($bool === false)
				$success = false;

			return $success;

		}

		/** KEYDB.cfg testing **/
		public function test_keydb($filename) {

			if(!file_exists($filename))
				return false;

			$filename = realpath($filename);

			$arg_device = escapeshellarg($this->device);
			$arg_keydb = escapeshellarg($filename);

			$cmd = $this->binary." -m -k $arg_keydb $arg_device 2>&1 | grep -q 'aacs_open() failed'";

			exec($cmd, $output, $retval);


			if($retval == 0)
				return false;
			else
				return true;

		}

	}
