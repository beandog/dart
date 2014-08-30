<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Tracks_Model extends DBTable {

		function __construct($id = null) {

			$table = "tracks";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * dvd_id and ix.
		 *
		 * @params int $dvd_id tracks.dvd_id
		 * @params int $ix tracks.ix
		 */
		public function find_track_id($dvd_id, $ix) {

			$sql = "SELECT id FROM tracks WHERE dvd_id = ".$this->db->quote($dvd_id)." AND ix = ".$this->db->quote($ix).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

		public function get_audio_streams() {

			$sql = "SELECT * FROM audio WHERE track_id = ".$this->db->quote($this->id)." ORDER BY langcode = 'en' DESC, channels DESC, format = 'dts' DESC, streamid;";

			$arr = $this->db->getAll($sql);

			return $arr;

		}

		public function get_best_quality_audio_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id).") ORDER BY CASE WHEN format = 'dts' THEN 1 ELSE 0 END, streamid LIMIT 1;";

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id).") ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_first_english_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' ORDER BY streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_first_english_subp() {

			$sql = "SELECT ix FROM subp WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' ORDER BY ix LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_num_chapters() {

			$sql = "SELECT COUNT(1) FROM chapters WHERE track_id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		// Check a track record to see if it is missing
		// metadata somewhere.
		public function missing_metadata() {

			$track_id = abs(intval($this->id));

			$sql = "SELECT 1 FROM tracks WHERE (angles IS NULL OR cc IS NULL) AND id = $track_id;";
			$var = $this->db->getOne($sql);
			$bool = (bool)$var;

			if($bool)
				return true;

			$sql = "SELECT COUNT(1) FROM audio WHERE track_id = $track_id AND active IS NULL;";
			$var = $this->db->getOne($sql);
			if($var)
				return true;

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = $track_id AND active IS NULL;";
			$var = $this->db->getOne($sql);
			if($var)
				return true;

			return false;

		}

		public function get_tags() {

			$sql = "SELECT tg.name FROM tracks t INNER JOIN tags_tracks tt ON t.id = tt.track_id INNER JOIN tags tg ON tt.tag_id = tg.id WHERE t.id = ".$this->db->quote($this->id).";";
			$arr = $this->db->getCol($sql);

			return $arr;

		}

		public function tag_track($tag_name) {

			$track_id = intval($this->id);

			if(!$track_id)
				return false;

			$sql = "SELECT id FROM tags WHERE name = ".$this->db->quote($tag_name).";";
			$tag_id = intval($this->db->getOne($sql));

			if(!$tag_id)
				return false;

			$sql = "DELETE FROM tags_tracks WHERE tag_id = $tag_id AND track_id = $track_id;";
			$this->db->query($sql);

			$sql = "INSERT INTO tags_tracks (tag_id, track_id) VALUES ($tag_id, $track_id);";
			$this->db->query($sql);

			return true;

		}

	}
?>
