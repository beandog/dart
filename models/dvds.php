<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = "dvds";

			$this->id = parent::__construct($table, $id);

		}


		// A note about metadata specifications: they should be used as
		// a reference to *what method that data was imported* into the database,
		// and not as whether the metadata is up to date or not (despite the name).
		// The reason for this is that metadata can be marked as "missing" if minor
		// changes are made to the schema, but they would not qualify as out of
		// reference if the same source for providing the data remained the same.
		// So, a FIXME is probably in order and metadata_spec should be changed or
		// perhaps include something like data_source, as well.
		public function max_metadata_spec($disc_type = 'dvd') {

			if($disc_type == 'dvd')
				$sql = "SELECT MAX(version) FROM specs WHERE metadata = 'database';";
			elseif($disc_type == 'bluray')
				$sql = "SELECT MAX(version) FROM specs WHERE metadata = 'bluray';";
			else
				return 0;

			$max_metadata_spec = intval($this->db->getOne($sql));

			return $max_metadata_spec;

		}

		// Check a DVD to see if it has scanned data
		public function dvd_scanned_tracks() {

			$dvd_id = abs(intval($this->id));

			$sql = "SELECT COUNT(1) FROM dvds d JOIN tracks t ON d.id = t.dvd_id LEFT OUTER JOIN track_scans ts ON ts.track_id = t.id WHERE d.id = $dvd_id AND ts.id IS NULL;";
			$count = $this->get_one($sql);

			if($count)
				return false;
			else
				return true;

		}

		// Check a DVD record to see if it is missing
		// metadata somewhere.
		public function dvd_missing_metadata($disc_type = 'dvd') {

			$dvd_id = abs(intval($this->id));

			// Check for empty filesize
			$sql = "SELECT filesize FROM dvds WHERE id = $dvd_id;";
			$filesize = abs(intval($this->db->getOne($sql)));
			if(!$filesize)
				return true;

			if($disc_type == 'dvd') {

				$max_metadata_spec_id = $this->max_metadata_spec();

				$sql = "SELECT COUNT(1) FROM dvds d WHERE id = $dvd_id AND bluray = 0 AND metadata_spec < $max_metadata_spec_id;";
				$count = abs(intval($this->db->getOne($sql)));

				if($count)
					return true;

				// Check if the DVD doesn't have the side set
				$sql = "SELECT COUNT(1) FROM dvds WHERE id = $dvd_id AND bluray = 0 AND side IS NULL;";
				$count = abs(intval($this->db->getOne($sql)));

				if($count)
					return true;

			}

			if($disc_type == 'bluray') {

				// Check for old metadata spec
				$max_metadata_spec_id = $this->max_metadata_spec('bluray');
				$sql = "SELECT COUNT(1) FROM dvds d WHERE id = $dvd_id AND bluray = 1 AND metadata_spec < $max_metadata_spec_id;";
				$count = abs(intval($this->db->getOne($sql)));
				if($count)
					return true;

				// Check for missing title
				$sql = "SELECT COUNT(1) FROM dvds WHERE id = $dvd_id AND TRIM(title) = '';";
				$count = abs(intval($this->db->getOne($sql)));
				if($count)
					return true;

				$sql = "SELECT disc_id FROM blurays WHERE dvd_id = $dvd_id;";
				$var = $this->db->getOne($sql);
				if(!$var)
					return true;

				$sql = "SELECT disc_title FROM blurays WHERE dvd_id = $dvd_id;";
				$var = $this->db->getOne($sql);
				if(is_null($var))
					return true;

			}

			return false;

		}

		// Check for missing data on a Blu-ray found on the disc
		public function missing_bluray_metadata() {

			$dvd_id = abs(intval($this->id));

			$sql = "SELECT * FROM blurays WHERE dvd_id = $dvd_id";
			$row = $this->db->getRow($sql);

			if($row['disc_title'] == null)
				return true;

			return false;

		}

		// Check if any of the tracks on the DVD are missing metadata, regardless
		// of spec.
		public function dvd_tracks_missing_metadata($disc_type = 'dvd') {

			$dvd_id = abs(intval($this->id));

			// Verify tracks are existent
			$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id;";
			$count = intval($this->db->getOne($sql));
			if(!$count)
				return true;

			if($disc_type == 'dvd') {

				// Check if any of the tracks are missing an active flag
				$sql = "SELECT COUNT(1) FROM tracks t JOIN dvds d ON d.id = t.dvd_id JOIN audio a ON a.track_id = t.id JOIN subp s ON s.track_id = t.id WHERE d.id = $dvd_id AND (s.active IS NULL OR a.active IS NULL OR closed_captioning IS NULL);";
				$count = $this->get_one($sql);

				if($count)
					return true;

				// Check if an audio track hasn't been set
				$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id AND audio_ix IS NULL;";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if any subtitle tracks have not been tracked as active or not
				$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND active IS NULL;";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if closed captioning is not flagged
				$sql = "SELECT COUNT(1) FROM tracks WHERE dvd_id = $dvd_id AND closed_captioning IS NULL;";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				if($this->dvd_tracks_missing_cells())
					return true;

				// Check if filesize is not set for tracks
				$sql = "SELECT COUNT(1) FROM tracks t JOIN chapters c ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND (t.filesize IS NULL OR c.filesize IS NULL);";

				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

			}

			if($disc_type == 'bluray') {

				// Check if there are zero audio tracks
				$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id;";
				$count = intval($this->db->getOne($sql));
				if(!$count)
					return true;

				// Check if any audio tracks are missing language
				$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id AND langcode = '';";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if any audio tracks are missing stream id
				$sql = "SELECT COUNT(1) FROM audio a JOIN tracks t ON a.track_id = t.id WHERE t.dvd_id = $dvd_id AND streamid = '';";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if there are zero subtitles
				$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id;";
				$count = intval($this->db->getOne($sql));
				if(!$count)
					return true;

				// Check if any subtitles are missing language
				$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND langcode = '';";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if any subtitles are missing stream id
				$sql = "SELECT COUNT(1) FROM subp s JOIN tracks t ON s.track_id = t.id WHERE t.dvd_id = $dvd_id AND streamid = '';";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if there are zero chapters
				$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id;";
				$count = intval($this->db->getOne($sql));
				if(!$count)
					return true;

				// Check if chapters are missing filesize
				$sql = "SELECT COUNT(1) FROM chapters c JOIN tracks t ON c.track_id = t.id WHERE t.dvd_id = $dvd_id AND c.filesize IS NULL;";
				$count = intval($this->db->getOne($sql));
				if($count)
					return true;

				// Check if the filesize for tracks is using old format
				$sql = "SELECT MAX(filesize) FROM tracks WHERE dvd_id = $dvd_id;";
				$filesize = $this->db->getOne($sql);
				if(ceil($filesize / 1048576) == 1)
					return true;

				return false;

			}

			return false;

		}

		public function dvd_tracks_missing_cells() {

			$dvd_id = abs(intval($this->id));

			// Check if VTS or TTN is not imported
			$sql = "SELECT COUNT(1) from tracks WHERE dvd_id = $dvd_id AND (vts IS NULL OR ttn IS NULL);";
			$count = intval($this->db->getOne($sql));
			if($count)
				return true;

			// Check if cells have been imported
			$sql = "SELECT COUNT(1) FROM cells c JOIN tracks t ON c.track_id = t.id JOIN dvds d ON t.dvd_id = d.id WHERE d.id = $dvd_id;";
			$count = intval($this->db->getOne($sql));
			if(!$count)
				return true;

			return false;

		}

		public function get_episodes() {

			$sql = "SELECT e.id FROM dart_series_episodes e INNER JOIN tracks t ON e.track_id = t.id INNER JOIN dvds d ON t.dvd_id = d.id WHERE d.id = ".$this->db->quote($this->id)." ORDER BY e.season, e.episode_number, e.ix;";

			$arr = $this->db->getCol($sql);

			return $arr;

		}

		public function get_tracks() {

			$sql = "SELECT id FROM tracks t WHERE dvd_id = ".$this->id." ORDER BY ix;";
			$arr = $this->db->getCol($sql);

			return $arr;

		}

		public function get_series_id() {

			$sql = "SELECT series.id FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function get_series_title() {

			$sql = "SELECT series.title FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function get_collection_id() {

			$sql = "SELECT series.collection_id FROM dvds JOIN series_dvds ON dvds.id = series_dvds.dvd_id JOIN series ON series.id = series_dvds.series_id WHERE dvds.id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			return $var;
		}

		public function has_max_tracks() {

			$sql = "SELECT MAX(ix) FROM tracks WHERE dvd_id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			if($var == 99)
				return true;
			else
				return false;

		}

		public function has_bugs() {

			$sql = "SELECT LENGTH(TRIM(bugs)) FROM dvds WHERE id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			if($var)
				return true;
			else
				return false;

		}

		public function get_bugs() {

			$sql = "SELECT bugs FROM dvds WHERE id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function find_dvdread_id($dvdread_id) {

			$dvdread_id = trim($dvdread_id);

			$sql = "SELECT id FROM dvds WHERE dvdread_id = ".$this->db->quote($dvdread_id).";";
			$var = $this->db->getOne($sql);

			if($var)
				$var = intval($var);
			else
				$var = null;

			return $var;

		}

		function load_dvdread_id($dvdread_id) {

			if(!$dvdread_id)
				return false;

			$sql = "SELECT id FROM dvds WHERE dvdread_id = ".$this->db->quote($dvdread_id).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

	}
?>
