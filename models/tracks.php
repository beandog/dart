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

			$sql = "SELECT * FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND active = 1 ORDER BY langcode = 'en' DESC, channels DESC, format = 'dts' DESC, streamid;";

			$arr = $this->db->getAll($sql);

			return $arr;

		}

		public function get_best_quality_audio_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' AND active = 1 AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND active = 1) ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_best_quality_audio_ix() {

			$sql = "SELECT COALESCE(ix, 1) FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' AND active = 1 AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND active = 1) ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_first_english_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' AND active = 1 ORDER BY streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_num_active_audio_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND active = 1";
			if(strlen($lang) == 2)
				$sql .= " AND langcode = ".$this->db->quote($lang);

			$sql .= ";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function has_closed_captioning() {

			$sql = "SELECT closed_captioning FROM tracks WHERE id = ".$this->db->quote($this->id).";";
			$var = $this->db->getOne($sql);
			if($var)
				return true;
			else
				return false;

		}

		public function get_num_subp_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = ".$this->db->quote($this->id);
			if(strlen($lang) == 2)
				$sql .= " AND langcode = ".$this->db->quote($lang);

			$sql .= ";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function get_num_active_subp_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = ".$this->db->quote($this->id)." AND active = 1";
			if(strlen($lang) == 2)
				$sql .= " AND langcode = ".$this->db->quote($lang);

			$sql .= ";";
			$var = $this->db->getOne($sql);
			return $var;

		}

		public function get_first_english_subp() {

			$sql = "SELECT ix FROM subp WHERE track_id = ".$this->db->quote($this->id)." AND (langcode = 'en' OR langcode = 'eng') AND active = 1 ORDER BY ix LIMIT 1;";

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
		// Unused -- dvd_tracks_missing_metadata() is used instead.
		public function missing_metadata() {

			$track_id = abs(intval($this->id));

			$sql = "SELECT COUNT(1) FROM tracks WHERE closed_captioning AND id = $track_id;";
			$count = $this->get_one($sql);
			if($count)
				return true;

			$sql = "SELECT COUNT(1) FROM audio WHERE track_id = $track_id AND active IS NULL;";
			$count = $this->get_one($sql);
			if($count)
				return true;

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = $track_id AND active IS NULL;";
			$count = $this->get_one($sql);
			if($count)
				return true;

			return false;

		}

		function get_audio_details($audio_stream_id) {

			$track_id = intval($this->id);
			$q_audio_stream_id = $this->db->quote($audio_stream_id);

			$sql = "SELECT format, channels FROM audio WHERE track_id = $track_id AND streamid = $q_audio_stream_id;";
			$arr = $this->db->getRow($sql);

			return $arr;

		}

		function has_handbrake_scan() {

			$track_id = abs(intval($this->id));

			$sql = "SELECT COUNT(1) FROM track_scans WHERE track_id = $track_id;";
			$count = $this->get_one($sql);
			if($count)
				return true;
			else
				return false;
		}

		function set_handbrake_scan($hb_version, $hb_output) {

			$track_id = intval($this->id);
			$sql = "SELECT id FROM track_scans WHERE track_id = $track_id;";
			$track_scan_id = $this->get_one($sql);
			$hb_version = $this->db->quote($hb_version);
			$hb_output = $this->db->quote($hb_output);

			if($track_scan_id) {
				$sql = "UPDATE track_scans SET hb_version = $hb_version, scan_output = $hb_output WHERE id = $track_scan_id;";
				$this->db->query($sql);
			} else {
				$sql = "INSERT INTO track_scans (track_id, hb_version, scan_output) VALUES ($track_id, $hb_version, $hb_output);";
				$this->db->query($sql);
			}

			return true;

		}

	}
?>
