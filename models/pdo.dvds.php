<?php

	class Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = 'dvds';

			$this->id = parent::__construct($table, $id);

		}

		// Check a DVD to see if it has scanned data
		public function dvd_scanned_tracks() {

			$dvd_id = $this->id;

			$sql = "SELECT COUNT(1) FROM dvds d JOIN tracks t ON d.id = t.dvd_id LEFT OUTER JOIN track_scans ts ON ts.track_id = t.id WHERE d.id = $dvd_id AND ts.id IS NULL;";
			$count = $this->get_one($sql);

			if($count)
				return false;
			else
				return true;

		}

		// Check a DVD record to see if it is missing
		// metadata somewhere.
		public function dvd_missing_metadata($debug = false) {

			$dvd_id = $this->id;

			// Check for missing disc title
			$sql = "SELECT title FROM dvds WHERE id = $dvd_id;";
			$title = $this->get_one($sql);

			if($debug && !$title)
				echo "* DVD $dvd_id disc title IS NULL\n";

			if(!$title)
				return true;

			// Check for DVD filesize
			$sql = "SELECT filesize FROM dvds WHERE id = $dvd_id;";
			if(!$this->get_one($sql))
				return true;

			// Check if the DVD doesn't have the side set
			$sql = "SELECT COUNT(1) FROM dvds WHERE id = $dvd_id AND bluray = 0 AND side IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id side IS NULL\n";

			if($count)
				return true;

			$sql = "SELECT COUNT(1) FROM dvds WHERE id = $dvd_id AND region IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD region is unset\n";

			if($count)
				return true;

			return false;

		}

		// Check for missing data on a Blu-ray found on the disc
		public function missing_bluray_metadata($debug = false) {

			$debug = false;

			$dvd_id = $this->id;

			// Check for empty filesize
			$sql = "SELECT filesize FROM dvds WHERE id = $dvd_id;";
			$filesize = abs(intval($this->get_one($sql)));
			if(!$filesize)
				return true;

			$sql = "SELECT * FROM blurays WHERE dvd_id = $dvd_id";
			$row = $this->get_row($sql);

			if(count($row) && $row['disc_title'] === null) {
				if($debug)
					echo "* Missing 'disc_title'\n";
				return true;
			}

			$sql = "SELECT title FROM dvds WHERE id = $dvd_id;";
			$title = $this->get_one($sql);
			if(!$title)
				return true;

			$sql = "SELECT bdinfo_titles FROM blurays WHERE dvd_id = $dvd_id;";
			$var = $this->get_one($sql);
			if(is_null($var))
				return true;

			$sql = "SELECT hdmv_titles FROM blurays WHERE dvd_id = $dvd_id;";
			$var = $this->get_one($sql);
			if(is_null($var))
				return true;

			$sql = "SELECT bdj_titles FROM blurays WHERE dvd_id = $dvd_id;";
			$var = $this->get_one($sql);
			if(is_null($var))
				return true;

			$sql = "SELECT blocks FROM tracks WHERE dvd_id = $dvd_id;";
			$var = $this->get_one($sql);
			if(is_null($var))
				return true;

			/*
			$sql = "SELECT udf_uuid FROM blurays WHERE dvd_id = $dvd_id;";
			$var = $this->get_one($sql);
			if(!strlen($var))
				return true;
			*/

			return false;

		}

		// Check if any of the tracks on the DVD are missing metadata, regardless
		// of spec.
		public function dvd_tracks_missing_metadata($debug = false) {

			$dvd_id = $this->id;

			// Verify tracks are existent
			$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id;";
			$count = $this->get_one($sql);

			if($debug && !$count)
				echo "* DVD $dvd_id has no tracks in database\n";

			if(!$count)
				return true;

			// Check if any of the tracks are missing an active flag
			$sql = "SELECT COUNT(1) FROM tracks t JOIN dvds d ON d.id = t.dvd_id JOIN audio a ON a.track_id = t.id JOIN subp s ON s.track_id = t.id WHERE d.id = $dvd_id AND (s.active IS NULL OR a.active IS NULL OR closed_captioning IS NULL);";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count tracks missing an active flag\n";

			if($count)
				return true;

			// Check if an audio track hasn't been set
			$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id AND audio_ix IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count audio tracks that haven't been set\n";

			if($count)
				return true;

			// Check if any subtitle tracks have not been tracked as active or not
			$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND active IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count subtitle tracks not marked as active or not\n";

			if($count)
				return true;

			// Check if closed captioning is not flagged
			$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id AND closed_captioning IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count tracks where closed captioning is not flagged\n";

			if($count)
				return true;

			if($this->dvd_tracks_missing_cells()) {
				if($debug)
					echo "DVD $dvd_id tracks are missing cells\n";
				return true;
			}

			// Check if filesize is not set for tracks
			$sql = "SELECT COUNT(1) FROM tracks t JOIN chapters c ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND (t.filesize IS NULL OR c.filesize IS NULL);";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count tracks or chapters with unset filesize\n";

			if($count)
				return true;

			// Check if tracks are missing blocks
			$sql = "SELECT COUNT(1) FROM tracks t WHERE dvd_id = $dvd_id AND blocks IS NULL AND vts IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count tracks missing blocks\n";

			if($count)
				return true;

			// Check if chapters are missing blocks
			$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND c.blocks IS NULL;";
			$count = $this->get_one($sql);

			if($debug && $count)
				echo "* DVD $dvd_id has $count chapters that are missing blocks\n";

			if($count)
				return true;

			if($debug)
				echo "* DVD $dvd_id has no missing tracks metadata :)\n";

			return false;

		}

		// Check if any of the tracks on the BD are missing metadata, regardless
		// of spec.
		public function bluray_playlists_missing_metadata($debug = true) {

			$dvd_id = $this->id;

			// Verify tracks are existent
			$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id;";
			$count = $this->get_one($sql);
			if(!$count) {
				if($debug)
					echo "* Zero playlists\n";
				return true;
			}

			// Check if there are zero audio tracks
			$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id;";
			$count = $this->get_one($sql);
			if(!$count) {
				if($debug)
					echo "* Zero audio tracks\n";
				return true;
			}

			// Check if any audio tracks are missing language
			$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id AND langcode = '';";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Audio tracks missing language\n";
				return true;
			}

			// Check if any audio tracks are missing stream id
			$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id AND streamid = '';";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Audio tracks missing stream id\n";
				return true;
			}

			// Check if any subtitles are missing language
			$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND langcode = '';";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Subtitles missing language\n";
				return true;
			}

			// Check if any subtitles are missing stream id
			$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND streamid = '';";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Subtitles missing stream id\n";
				return true;
			}

			// Check if there are zero chapters
			$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id;";
			$count = $this->get_one($sql);
			if(!$count) {
				if($debug)
					echo "* Zero chapters\n";
				return true;
			}

			// Check if chapters are missing filesize
			$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND c.filesize IS NULL;";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Chapters missing filesize\n";
				return true;
			}

			// Check if playlists are missing blocks
			$sql = "SELECT COUNT(1) FROM tracks t WHERE dvd_id = $dvd_id AND blocks IS NULL;";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Playlists are missing blocks\n";
				return true;
			}

			// Check if chapters are missing blocks
			$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND c.blocks IS NULL;";
			$count = $this->get_one($sql);
			if($count) {
				if($debug)
					echo "* Chapters are missing blocks\n";
				return true;
			}

			// Check if the filesize for tracks is using old format
			$sql = "SELECT MAX(filesize) FROM tracks WHERE dvd_id = $dvd_id;";
			$filesize = $this->get_one($sql);
			if(ceil($filesize / 1048576) == 1) {
				if($debug)
					echo "* Filesize for tracks is using old format\n";
				return true;
			}

			return false;

		}

		public function dvd_tracks_missing_cells() {

			$dvd_id = $this->id;

			// Check if VTS or TTN is not imported
			$sql = "SELECT COUNT(1) from tracks WHERE dvd_id = $dvd_id AND (vts IS NULL OR ttn IS NULL);";
			$count = $this->get_one($sql);
			if($count)
				return true;

			// Check if cells have been imported
			$sql = "SELECT COUNT(1) FROM cells c JOIN tracks t ON c.track_id = t.id JOIN dvds d ON t.dvd_id = d.id WHERE d.id = $dvd_id;";
			$count = $this->get_one($sql);
			if(!$count)
				return true;

			return false;

		}

		public function dvd_missing_episode_metadata() {

			return false;

			/*
			$dvd_id = abs(intval($this->id));

			$sql = "SELECT COUNT(1) FROM view_episodes WHERE crop = '' AND dvd_id = $dvd_id;";

			$count = intval($this->get_one($sql));

			if($count) {
				echo "* $count episodes missing crop values\n";
				return true;
			}

			return false;
			*/

		}

		public function get_episodes($include_skipped = true) {

			if($include_skipped)
				$str_skip = "0, 1";
			else
				$str_skip = "0";

			$sql = "SELECT episode_id FROM view_episodes WHERE dvd_id = {$this->id} AND episode_skip IN ($str_skip) ORDER BY episode_season, episode_number, episode_id;";

			$arr = $this->get_col($sql);

			return $arr;

		}

		public function get_tracks() {

			$sql = "SELECT id FROM tracks t WHERE dvd_id = {$this->id} ORDER BY ix;";
			$arr = $this->get_col($sql);

			return $arr;

		}

		public function get_title_sets() {

			$sql = "SELECT DISTINCT track_vts FROM view_episodes WHERE dvd_id = {$this->id} AND episode_skip = 0 ORDER BY track_vts;";
			$arr = $this->get_col($sql);

			return $arr;

		}

		public function get_series_id() {

			$sql = "SELECT series.id FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = {$this->id};";
			$var = $this->get_one($sql);
			return $var;

		}

		public function get_series_title() {

			$sql = "SELECT series.title FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = {$this->id};";
			$var = $this->get_one($sql);
			return $var;

		}

		public function get_collection_id() {

			$sql = "SELECT series.collection_id FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = {$this->id};";
			$var = $this->get_one($sql);
			return $var;
		}

		public function has_max_tracks() {

			$sql = "SELECT MAX(ix) FROM tracks WHERE dvd_id = {$this->id};";
			$var = $this->get_one($sql);
			if($var == 99)
				return true;
			else
				return false;

		}

		public function has_bugs() {

			$sql = "SELECT COUNT(1) FROM dvd_bugs WHERE dvd_id = {$this->id};";
			$var = $this->get_one($sql);
			if($var)
				return true;
			else
				return false;

		}

		public function get_bugs() {

			$sql = "SELECT b.name FROM dvd_bugs db JOIN bugs b ON db.bug_id = b.id WHERE db.dvd_id = {$this->id} ORDER BY b.name;";
			$arr = $this->get_col($sql);

			return $arr;

		}

		public function get_deint() {

			$sql = "SELECT COUNT(1) FROM dvd_bugs db JOIN bugs b ON db.bug_id = b.id WHERE db.dvd_id = {$this->id} AND b.name = 'deint-all';";
			$var = $this->get_one($sql);

			if($var)
				return 'all';

			return '';

		}

		// Override encoder if there is a bug for the disc or set by the series
		public function get_encoder() {

			// Check for bugs
			$sql = "SELECT b.name FROM dvd_bugs db JOIN bugs b ON db.bug_id = b.id WHERE db.dvd_id = {$this->id};";
			$arr = $this->get_col($sql);

			$var = '';

			if(in_array('ffmpeg', $arr))
				return('ffmpeg');
			elseif(in_array('ffmpeg+pipe', $arr))
				return('ffmpeg+pipe');
			elseif(in_array('handbrake', $arr))
				return('handbrake');

			// Get encoder override from series
			$sql = "SELECT r.name FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id JOIN ripping r ON series.ripping_id = r.id WHERE dvds.id = {$this->id};";
			$var = $this->get_one($sql);

			return $var;

		}

		public function find_dvdread_id($dvdread_id) {

			$dvdread_id = trim(strval($dvdread_id));

			$dvdread_id = $this->quote($dvdread_id);

			$sql = "SELECT id FROM dvds WHERE dvdread_id = $dvdread_id;";
			$var = $this->get_one($sql);

			if($var)
				$var = intval($var);
			else
				$var = null;

			return $var;

		}

		function load_dvdread_id($dvdread_id) {

			$dvdread_id = trim(strval($dvdread_id));

			$dvdread_id = $this->quote($dvdread_id);

			$sql = "SELECT id FROM dvds WHERE dvdread_id = $dvdread_id;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function get_iso_filenames($collection_id = null, $series_id = null, $dvd_id = null, $episode_id = null, $active = true) {

			$active = boolval($active);

			$sql = "SELECT DISTINCT iso_filename FROM view_episodes WHERE";
			if($active)
				$sql .= " series_active = 1";
			else
				$sql .= " series_active != 1";
			if($collection_id)
				$sql .= " AND collection_id = $collection_id";
			if($series_id)
				$sql .= " AND series_id = $series_id";
			if($dvd_id)
				$sql .= " AND dvd_id = $dvd_id";
			if($episode_id)
				$sql .= " AND episode_id = $episode_id";
			$sql .= " AND episode_skip = 0";
			$sql .= " ORDER BY iso_filename;";

			$arr = $this->get_col($sql);

			return $arr;

		}

	}

?>
