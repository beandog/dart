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
			$var = $this->get_one($sql);

			if(!$var)
				return false;

			$this->id = intval($var);

			return $this->id;

		}

		function load_dvdread_id($str) {

			if(!$str)
				return false;

			$sql = "SELECT b.id FROM blurays b JOIN dvds d ON d.id = b.dvd_id WHERE d.dvdread_id = '".pg_escape_string($str)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_disc_title($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE disc_title = '".pg_escape_string($str)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_disc_id($str) {

			if(!$str)
				return false;

			$str = strtolower($str);

			$sql = "SELECT id FROM blurays WHERE disc_id = '".pg_escape_string($str)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_volname($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE volname = '".pg_escape_string($str)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_legacy_md5($str) {

			if(!$str)
				return false;

			$sql = "SELECT id FROM blurays WHERE legacy_md5 = '".pg_escape_string($str)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		// Holder function from DVDs model
		function has_max_tracks() {

			return false;

		}

	}
?>
