<?php

	/** CD **/

	class CD {

		public $device = '/dev/cdrom';
		public $cd_info;
		public $debug = false;

		// CD
		public $cddb_id;
		public $tracks;
		public $length;
		public $sectors;

		function __construct($device = '/dev/cdrom', $debug = false) {

			$this->device = realpath($device);
			$this->debug = boolval($debug);

			if(!file_exists($this->device)) {
				$this->opened = false;
				return null;
			}

			// Run cd_info first and return if it passes or not
			$arr = $this->cd_info();

			if($arr === false)
				return false;

			$this->cddb_id = $arr['cd']['freedb'];
			$this->tracks = $arr['cd']['tracks'];
			$this->sectors = $arr['cd']['sectors'];

			return true;

		}

		private function cd_info() {

			$arg_device = escapeshellarg($this->device);
			$cmd = "cd_info $arg_device";

			if($this->debug)
				echo "* Executing: $cmd\n";

			$str_json = shell_exec($cmd);

			if(!json_validate($str_json)) {
				echo "* Invalid JSON: $str_json\n";
				return array();
			}

			$arr_json = json_decode($str_json, true);

			return $arr_json;

		}

	}

	/** CDDB **/

	class CDDB {

		public $id;

		public $cddb_info;
		public $filename;

		public $artist;
		public $album;
		public $title;
		public $genre_id;
		public $genre_title;
		public $year;

		public $titles = array();

		function __construct($id) {

			$this->id = strval($id);

		}

		function cddb_info($filename) {

			if(!file_exists($filename))
				return array();

			$arr_genres = array('Blues', 'Classic Rock', 'Country', 'Dance', 'Disco', 'Funk', 'Grunge', 'Hip-Hop', 'Jazz', 'Metal', 'New Age', 'Oldies', 'Other', 'Pop', 'R&B', 'Rap', 'Reggae', 'Rock', 'Techno', 'Industrial', 'Alternative', 'Ska', 'Death Metal', 'Pranks', 'Soundtrack', 'Euro-Techno', 'Ambient', 'Trip-Hop', 'Vocal', 'Jazz+Funk', 'Fusion', 'Trance', 'Classical', 'Instrumental', 'Acid', 'House', 'Game', 'Sound Clip', 'Gospel', 'Noise', 'AlternRock', 'Bass', 'Soul', 'Punk', 'Space', 'Meditative', 'Instrumental Pop', 'Instrumental Rock', 'Ethnic', 'Gothic', 'Darkwave', 'Techno-Industrial', 'Electronic', 'Pop-Folk', 'Eurodance', 'Dream', 'Southern Rock', 'Comedy', 'Cult', 'Gangsta', 'Top 40', 'Christian Rap', 'Pop/Funk', 'Jungle', 'Native American', 'Cabaret', 'New Wave', 'Psychadelic', 'Rave', 'Showtunes', 'Trailer', 'Lo-Fi', 'Tribal', 'Acid Punk', 'Acid Jazz', 'Polka', 'Retro', 'Musical', 'Rock & Roll', 'Hard Rock', 'Folk', 'Folk-Rock', 'National Folk', 'Swing', 'Fast Fusion', 'Bebob', 'Latin', 'Revival', 'Celtic', 'Bluegrass', 'Avantgarde', 'Gothic Rock', 'Progressive Rock', 'Psychedelic Rock', 'Symphonic Rock', 'Slow Rock', 'Big Band', 'Chorus', 'Easy Listening', 'Acoustic', 'Humour', 'Speech', 'Chanson', 'Opera', 'Chamber Music', 'Sonata', 'Symphony', 'Booty Bass', 'Primus', '', 'Satire', 'Slow Jam', 'Club', 'Tango', 'Samba', 'Folklore', 'Ballad', 'Power Ballad', 'Rhythmic Soul', 'Freestyle', 'Duet', 'Punk Rock', 'Drum Solo', 'A capella', 'Euro-House', 'Dance Hall', 'Goa', 'Drum & Bass', 'Club-House', 'Hardcore', 'Terror', 'Indie', 'Britpop', 'Negerpunk', 'Polsk Punk', 'Beat', 'Christian Gangsta Rap', 'Heavy Metal', 'Black Metal', 'Crossover', 'Contemporary Christian', 'Christian Rock', 'Merengue', 'Salsa', 'Trash Metal', 'Anime', 'JPop', 'Synthpop');

			$arr_contents = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

			$arr_contents = preg_grep('/^(D|T)/', $arr_contents);

			$arr_cddb = array();

			foreach($arr_contents as $value) {
				$arr = explode('=', $value, 2);
				$arr_cddb[$arr[0]] = $arr[1];
			}

			$arr = explode('/', $arr_cddb['DTITLE'], 2);

			$arr_metadata = array();
			$arr_metadata['id'] = $arr_cddb['DISCID'];
			if($arr[1])
				$arr_metadata['album'] = trim($arr[1]);
			else
				$arr_metadata['album'] = 'Unknown';
			if($arr[0])
				$arr_metadata['artist'] = trim($arr[0]);
			else
				$arr_metadata['artist'] = 'Unknown';
			$arr_metadata['year'] = $arr_cddb['DYEAR'];

			if(array_key_exists('DID3', $arr_cddb)) {
				$arr_metadata['genre'] = $arr_genres[$arr_cddb['DID3']];
			} elseif(array_key_exists('DGENRE', $arr_cddb)) {
				$arr_metadata['genre'] = $arr_cddb['DGENRE'];
			} else {
				$arr_metadata['genre'] = '';
			}

			$arr_metadata['tracks'] = array();
			foreach(array_keys($arr_cddb) as $key) {

				if(str_contains($key, 'TTITLE')) {

					$track_number = str_replace('TTITLE', '', $key);
					$track_title = $arr_cddb[$key];

					$arr_metadata['tracks'][$track_number + 1] = $track_title;

				}

			}

			return $arr_metadata;

		}

	}

	/** CD Track **/

	class CD_Track {

		private $data = array();

		public $track;
		public $device;
		public $filename;
		public $title;
		public $artist;
		public $album;
		public $year;
		public $genre;
		public $cover_art;

		function __construct($track = 1, $device = '/dev/cdrom') {

			$track = intval($track);

			if(!$track) {
				echo "# Track number is empty\n";
				return false;
			}

			$this->track = intval($track);

			$this->device = realpath($device);

			return true;

		}

		public function __set($key, $value) {
			$this->$key = $value;
		}

		function set_filename($filename) {

			$filename = trim(strval($filename));

			if(!strlen($filename)) {
				echo "# Invalid filename\n";
				return false;
			}

			$this->filename = $filename;

			return true;

		}

		function backup() {

			if(!$this->filename)
				$this->filename = $this->track.".wav";

			$arg_device = escapeshellarg($this->device);

			$arg_filename = escapeshellarg($this->filename);

			$track = $this->track;

			$cmd = "libcdio-paranoia -d $arg_device $track $arg_filename";

			passthru($cmd, $retval);

		}

		function rip($mp3) {

			$wav_filename = $this->filename;
			$arg_wav_filename = escapeshellarg($wav_filename);

			$arg_mp3_filename = escapeshellarg($mp3);

			$track = $this->track;

			$cmd = "lame -V 0 --id3v2-only --tn $track ";

			if($this->title) {
				$arg_title = escapeshellarg($this->title);
				$cmd .= "--tt $arg_title ";
			}
			if($this->artist) {
				$arg_artist = escapeshellarg($this->artist);
				$cmd .= "--ta $arg_artist ";
			}
			if($this->album) {
				$arg_album = escapeshellarg($this->album);
				$cmd .= "--tl $arg_album ";
			}
			if($this->year) {
				$arg_year = escapeshellarg($this->year);
				$cmd .= "--ty $arg_year ";
			}
			if($this->genre) {
				$arg_genre = escapeshellarg($this->genre);
				$cmd .= "--tg $arg_genre ";
			}
			if($this->cover_art && file_exists($this->cover_art)) {
				$arg_cover_art = escapeshellarg($this->cover_art);
				$cmd .= "--ti $arg_cover_art ";
			}

			$cmd .= "$arg_wav_filename $arg_mp3_filename";

			echo "$cmd\n";

			$retval = 1;
			passthru($cmd, $retval);

			return $retval;

		}

	}


