<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = "dvds";

			$this->id = parent::__construct($table, $id);

		}

		public function max_metadata_spec() {

			$sql = "SELECT MAX(id) FROM specs WHERE metadata = 'database';";
			$max_metadata_spec_id = intval($this->db->getOne($sql));

			return $max_metadata_spec_id;

		}

		// Check a DVD record to see if it is missing
		// metadata somewhere.
		public function dvd_missing_metadata() {

			$dvd_id = abs(intval($this->id));

			$max_metadata_spec_id = $this->max_metadata_spec();

			$sql = "SELECT COUNT(1) FROM dvds d WHERE id = $dvd_id AND metadata_spec < $max_metadata_spec_id;";
			$count = abs(intval($this->db->getOne($sql)));

			if($count)
				return true;

			// Check if the DVD doesn't have the side set
			$sql = "SELECT COUNT(1) FROM dvds WHERE id = $dvd_id AND side IS NULL;";
			$count = abs(intval($this->db->getOne($sql)));

			if($count)
				return true;

			return false;

		}

		// Check if any of the tracks on the DVD are missing metadata, regardless
		// of spec.
		public function dvd_tracks_missing_metadata() {

			$dvd_id = abs(intval($this->id));

			// Check if any of the tracks are missing an active flag
			$sql = "SELECT COUNT(1) FROM tracks t JOIN dvds d ON d.id = t.dvd_id JOIN audio a ON a.track_id = t.id JOIN subp s ON s.track_id = t.id WHERE d.id = $dvd_id AND (s.active IS NULL OR a.active IS NULL OR closed_captioning IS NULL);";
			$count = $this->get_one($sql);

			if($count)
				return true;

			return false;

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

		public function tag_dvd($tag_name) {

			$dvd_id = intval($this->id);

			if(!$dvd_id)
				return false;

			$sql = "SELECT id FROM tags WHERE name = ".$this->db->quote($tag_name).";";
			$tag_id = intval($this->db->getOne($sql));

			if(!$tag_id)
				return false;

			$sql = "DELETE FROM tags_dvds WHERE tag_id = $tag_id AND dvd_id = $dvd_id;";
			$this->db->query($sql);

			$sql = "INSERT INTO tags_dvds (tag_id, dvd_id) VALUES ($tag_id, $dvd_id);";
			$this->db->query($sql);

			return true;

		}

	}
?>
