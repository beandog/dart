<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Encodes_Model extends DBTable {

		function __construct($id = null) {

			$table = "encodes";

			$this->id = parent::__construct($table, $id);

		}

		function load_filename($filename) {

			if(!$filename)
				return false;

			$sql = "SELECT id FROM encodes WHERE filename = '".pg_escape_string($filename)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_uuid($uuid) {

			if(!$uuid)
				return false;

			$sql = "SELECT id FROM encodes WHERE uuid = '".pg_escape_string($uuid)."';";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

	}
?>
