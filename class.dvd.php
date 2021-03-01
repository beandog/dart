<?php

	class DVD {

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

			$this->dvdread_id = $this->dvdread_id();
			$this->title = $this->title();
			$this->title_tracks = $this->title_tracks();
			$this->title_track_index = $this->title_track_index();
			$this->longest_track = $this->longest_track();
			$this->provider_id = $this->provider_id();
			$this->size = $this->size();
			$this->side = $this->side();

			return true;

		}

		/** Hardware **/

		private function dvd_info() {

			if(file_exists("/usr/local/bin/dvd_info"))
				$this->binary = "/usr/local/bin/dvd_info";

			$arg_device = escapeshellarg($this->device);
			$cmd = $this->binary." --json $arg_device 2> /dev/null";

			if($this->debug)
				echo "* Executing: $cmd\n";

			exec($cmd, $output, $retval);

			if($retval !== 0 || !count($output)) {
				echo "* dvd_info(): FAILED\n";
				return false;
			}

			$str = implode('', $output);

			// Create an assoc. array
			$json = json_decode($str, true);

			if(is_null($json)) {
				echo "* dvd_info(): json_decode() FAILED\n";
				return false;
			}

			$this->dvd_info = $json;

			return true;

		}

		protected function dvd_info_string($arr, $key) {


			if(!array_key_exists($key, $arr))
				return "";
			else
				return $arr[$key];

		}

		protected function dvd_info_number($arr, $key) {

			if(!array_key_exists($key, $arr))
				return 0;
			else
				return $arr[$key];

		}

		/** Metadata **/

		// Use dvd_info to get dvdread id
		public function dvdread_id() {

			if(!$this->opened)
				return null;

			$dvdread_id = $this->dvd_info['dvd']['dvdread id'];

			if(strlen($dvdread_id) != 32)
				return false;

			return $dvdread_id;

		}

		public function dvdbackup($filename, $logfile = '/dev/null') {

			$bool = false;

			if(!$this->opened)
				return null;

			$logfile = realpath($logfile);

			if($this->debug) {
				echo "* dvd->dvdbackup($filename)\n";
				echo "* Logging to $logfile\n";
			}

			$arg_input = escapeshellarg($this->device);
			$arg_logfile = escapeshellarg($logfile);

			$target_dir = dirname($filename);
			$target_rip = $target_dir."/".basename($filename, '.iso').".R1p";
			$arg_name = basename($target_rip);
			$arg_output = escapeshellarg(dirname($filename));

			if($this->debug) {
				echo "* input: $arg_input\n";
				echo "* output: $arg_output\n";
				echo "* name: $arg_name\n";
			}

			$cmd = "dvdbackup -M -p -i $arg_input -o $arg_output -n $arg_name 2>&1 | tee $logfile";
			if($this->debug)
				echo "* Executing: $cmd\n";

			$success = true;
			$retval = 0;

			if(!$this->dry_run)
				passthru($cmd, $retval);

			if($this->debug)
				echo "* dvdbackup return value: $retval\n";

			if($retval !== 0)
				$success = false;

			if(!$this->dry_run)
				$bool = rename($target_dir.'/'.$arg_name, $filename);

			if($bool === false)
				$success = false;

			if($this->dry_run)
				return false;

			return $success;

		}

		/**
		 * Get the DVD title
		 */
		private function title() {

			if(!$this->opened)
				return null;

			$title = $this->dvd_info['dvd']['title'];

			// Standardize UDF volume names for Blu-rays to match DVDs
			$title = trim($title);
			$title = strtoupper($title);
			$title = str_replace(' ', '_', $title);

			return $title;
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

		public function provider_id() {

			if(!$this->opened)
				return null;

			$dvd =& $this->dvd_info;

			if(array_key_exists('provider id', $dvd['dvd'])) {
				$provider_id = $dvd['dvd']['provider id'];
				$provider_id = trim($provider_id);
			} else
				$provider_id = '';

			return $provider_id;

		}

		/**
		 * Get the size of the filesystem on the device
		 */
		public function size() {

			if($this->is_iso) {
				$stat = stat($this->device);
				$b_size = $stat['size'];
			} else {

				$block_device = basename($this->device, "/dev/");
				$num_sectors = file_get_contents("/sys/block/$block_device/size");
				$b_size = $num_sectors * 512;

			}

			if(!$b_size)
				return 0;

			$kb_size = $b_size / 1024;
			$mb_size = intval($kb_size / 1024);

			return $mb_size;

		}

		/**
		 * Get the side of the DVD
		 *
		 * Ideally, this should be set correctly on the DVD.
		 */
		public function side() {

			$side = $this->dvd_info['dvd']['side'];

			if(intval($side) === 2)
				return 2;
			else
				return 1;

		}

		/** DVD Track **/

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

		private function title_track_vts() {
			return $this->dvd_info_number($this->title_track_info, 'vts');
		}

		private function title_track_ttn() {
			return $this->dvd_info_number($this->title_track_info, 'ttn');
		}

		private function title_track_audio_tracks() {

			if(!array_key_exists('audio', $this->title_track_info))
				return 0;

			return count($this->title_track_info['audio']);

		}

		private function title_track_subtitle_tracks() {

			if(!array_key_exists('subtitles', $this->title_track_info))
				return 0;

			return count($this->title_track_info['subtitles']);

		}

		private function title_track_chapters() {

			if(!array_key_exists('chapters', $this->title_track_info))
				return 0;

			return count($this->title_track_info['chapters']);

		}

		private function title_track_cells() {

			if(!array_key_exists('cells', $this->title_track_info))
				return 0;

			return count($this->title_track_info['cells']);

		}

		private function title_track_filesize() {

			if(!array_key_exists('filesize', $this->title_track_info))
				return 0;

			return $this->title_track_info['filesize'];

		}

		private function video_codec() {
			return $this->dvd_info_string($this->title_track_info['video'], 'codec');
		}

		private function video_format() {
			return $this->dvd_info_string($this->title_track_info['video'], 'format');
		}

		private function video_aspect_ratio() {
			return $this->dvd_info_string($this->title_track_info['video'], 'aspect ratio');
		}

		private function video_width() {
			return $this->dvd_info_number($this->title_track_info['video'], 'width');
		}

		private function video_height() {
			return $this->dvd_info_number($this->title_track_info['video'], 'height');
		}

		private function video_angles() {
			return $this->dvd_info_number($this->title_track_info['video'], 'angles');
		}

		private function video_fps() {
			return $this->dvd_info_number($this->title_track_info['video'], 'fps');
		}

		/** DVD Audio Track **/

		public function load_audio_track($title_track, $audio_track) {

			$title_track = abs(intval($title_track));
			$audio_track = abs(intval($audio_track));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $audio_track === 0 || $audio_track > $this->title_track_audio_tracks) {

				return false;
			}

			$title_track_index = $this->title_track_index[$title_track];

			$this->audio_track = $audio_track;
			$this->audio_track_info = $this->dvd_info['tracks'][$title_track_index]['audio'][$this->audio_track - 1];

			$this->audio_track_active = $this->audio_track_active();
			$this->audio_track_lang_code = $this->audio_track_lang_code();
			$this->audio_track_codec = $this->audio_track_codec();
			$this->audio_track_channels = $this->audio_track_channels();
			$this->audio_track_stream_id = $this->audio_track_stream_id();

			return true;

		}

		private function audio_track_active() {
			return $this->dvd_info_string($this->audio_track_info, 'active');
		}

		private function audio_track_lang_code() {
			return $this->dvd_info_string($this->audio_track_info, 'lang code');
		}

		private function audio_track_codec() {
			return $this->dvd_info_string($this->audio_track_info, 'codec');
		}

		private function audio_track_channels() {
			return $this->dvd_info_number($this->audio_track_info, 'channels');
		}

		private function audio_track_stream_id() {
			return $this->dvd_info_string($this->audio_track_info, 'stream id');
		}

		/** DVD Subtitle Track **/

		public function load_subtitle_track($title_track, $subtitle_track) {

			$title_track = abs(intval($title_track));
			$subtitle_track = abs(intval($subtitle_track));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $subtitle_track === 0 || $subtitle_track > $this->title_track_subtitle_tracks) {

				return false;
			}

			$title_track_index = $this->title_track_index[$title_track];

			$this->subtitle_track = $subtitle_track;
			$this->subtitle_track_info = $this->dvd_info['tracks'][$title_track_index]['subtitles'][$this->subtitle_track - 1];

			$this->subtitle_track_active = $this->subtitle_track_active();
			$this->subtitle_track_lang_code = $this->subtitle_track_lang_code();
			$this->subtitle_track_stream_id = $this->subtitle_track_stream_id();

			return true;

		}

		private function subtitle_track_active() {
			return $this->dvd_info_string($this->subtitle_track_info, 'active');
		}

		private function subtitle_track_lang_code() {
			return $this->dvd_info_string($this->subtitle_track_info, 'lang code');
		}

		private function subtitle_track_stream_id() {
			return $this->dvd_info_string($this->subtitle_track_info, 'stream id');
		}

		/** DVD Chapter **/

		public function load_chapter($title_track, $chapter) {

			$title_track = abs(intval($title_track));
			$chapter = abs(intval($chapter));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $chapter === 0 || $chapter > $this->title_track_chapters) {

				return false;
			}

			$title_track_index = $this->title_track_index[$title_track];

			$this->chapter = $chapter;
			$this->chapter_info = $this->dvd_info['tracks'][$title_track_index]['chapters'][$this->chapter - 1];

			$this->chapter_length = $this->chapter_length();
			$this->chapter_msecs = $this->chapter_msecs();
			$this->chapter_seconds = $this->chapter_seconds();
			$this->chapter_filesize = $this->chapter_filesize();

			return true;

		}

		private function chapter_length() {
			return $this->dvd_info_string($this->chapter_info, 'length');
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

		private function chapter_filesize() {
			return $this->dvd_info_number($this->chapter_info, 'filesize');
		}

		/** DVD Cell **/

		public function load_cell($title_track, $cell) {

			$title_track = abs(intval($title_track));
			$cell = abs(intval($cell));

			$title_track_loaded = $this->load_title_track($title_track);

			if(!$title_track_loaded || $cell === 0 || $cell > $this->title_track_cells) {

				return false;
			}

			$title_track_index = $this->title_track_index[$title_track];

			$this->cell = $cell;
			$this->cell_info = $this->dvd_info['tracks'][$title_track_index]['cells'][$this->cell - 1];

			$this->cell_length = $this->cell_length();
			$this->cell_msecs = $this->cell_msecs();
			$this->cell_seconds = $this->cell_seconds();
			$this->cell_first_sector = $this->cell_first_sector();
			$this->cell_last_sector = $this->cell_last_sector();

			return true;

		}

		private function cell_length() {
			return $this->dvd_info_string($this->cell_info, 'length');
		}

		private function cell_msecs() {
			return $this->dvd_info_number($this->cell_info, 'msecs');
		}

		private function cell_seconds() {

			$msecs = abs(intval($this->cell_msecs()));
			$seconds = 0;
			if($msecs)
				$seconds = floatval(bcdiv($msecs, 1000, 3));

			return $seconds;

		}

		private function cell_first_sector() {
			return $this->dvd_info_string($this->cell_info, 'first sector');
		}

		private function cell_last_sector() {
			return $this->dvd_info_string($this->cell_info, 'last sector');
		}

	}

