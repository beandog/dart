<?php

	class Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = "dvds";

			$this->id = parent::__construct($table, $id);

		}

		// Check a DVD record to see if it is missing
		// metadata somewhere.
		public function missing_metadata() {

			$sql = "SELECT 1 FROM dvds WHERE (longest_track IS NULL OR filesize IS NULL OR serial_id = '') AND id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			$bool = (bool)$var;

			return $bool;

		}

		public function get_episodes() {

			$sql = "SELECT e.id FROM episodes e INNER JOIN tracks t ON e.track_id = t.id INNER JOIN dvds d ON t.dvd_id = d.id WHERE d.id = ".$this->db->quote($this->id).";";

			$arr = $this->db->getCol($sql);

			return $arr;

		}

		public function get_tracks() {

			$sql = "SELECT id FROM tracks t WHERE dvd_id = ".$this->id." ORDER BY ix;";
			$arr = $this->db->getCol($sql);

			return $arr;

		}

		public function get_audio_preference() {

			$sql = "SELECT audio_preference FROM series_dvds WHERE dvd_id = ".$this->db->quote($this->id).";";
			$audio_preference = $this->db->getOne($sql);

			if($audio_preference === "0") {

				$sql = "SELECT c.default_audio_preference FROM collections c INNER JOIN series s ON s.collection_id = c.id INNER JOIN series_dvds sd ON sd.series_id = s.id AND sd.dvd_id = ".$this->db->quote($this->id).";";

				$audio_preference = $this->db->getOne($sql);

			}

			return $audio_preference;

		}

		public function get_no_dvdnav() {

			$sql = "SELECT no_dvdnav FROM series_dvds WHERE dvd_id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);

			return $var;

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

	}
?>
