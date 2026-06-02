<?php

	class Encodes_Model extends DBTable {

		function __construct($id = null) {

			$table = 'encodes';

			$this->id = parent::__construct($table, $id);

		}

		function delete_encodes($filename) {

			$filename = trim(strval($filename));
			if($filename === '') {
				trigger_error("Filename is empty", E_USER_ERROR);
				exit(1);
			}

			$this->delete_from_table_where('encodes', 'filename', $filename);

			return true;

		}

		function load_filename($filename) {

			$filename = trim(strval($filename));
			if($filename === '') {
				trigger_error("Filename is empty", E_USER_ERROR);
				exit(1);
			}

			$filename = $this->quote($filename);

			$sql = "SELECT id FROM encodes WHERE filename = $filename LIMIT 1;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_uuid($uuid) {

			$uuid = trim(strval($uuid));
			if($uuid === '') {
				trigger_error("UUID is empty", E_USER_ERROR);
				exit(1);
			}

			$uuid = $this->quote($uuid);

			$sql = "SELECT id FROM encodes WHERE uuid = $uuid;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

	}
?>
