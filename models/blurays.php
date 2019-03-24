<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Blurays_Model extends DBTable {

		function __construct($id = null) {

			$table = "blurays";

			$this->id = parent::__construct($table, $id);

		}

		function load_dvd_id($int) {

			$int = abs(intval($int));

			if(!$int)
				return false;

			$sql = "SELECT id FROM blurays WHERE dvd_id = $int;";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		function load_titles($str, $bdinfo_titles, $bdj_titles, $hdmv_titles) {

			$sql = "SELECT id FROM blurays WHERE;";

		}

		function load_dvdread_id($str) {

			if(!$str)
				return false;

			$sql = "SELECT b.id FROM blurays b JOIN dvds d ON d.id = b.dvd_id WHERE d.dvdread_id = ".$this->db->quote($str).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		function load_disc_title($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE disc_title = ".$this->db->quote($str).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		function load_disc_id($str) {

			if(!$str)
				return false;

			$str = strtolower($str);

			$sql = "SELECT id FROM blurays WHERE disc_id = ".$this->db->quote($str).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		function load_volname($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE volname = ".$this->db->quote($str).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		function load_legacy_md5($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE legacy_md5 = ".$this->db->quote($str).";";
			$this->id = intval($this->db->getOne($sql));

			return $this->id;

		}

		// Holder function from DVDs model
		function has_max_tracks() {

			return false;

		}

	}
?>
