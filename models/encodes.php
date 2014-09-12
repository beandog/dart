<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Encodes_Model extends DBTable {

		function __construct($id = null) {

			$table = "encodes";

			$this->id = parent::__construct($table, $id);

		}

		function set_encode_finish($seconds) {

			$id = intval($this->id);

			if(!$seconds)
				$seconds = time();

			$sql = "UPDATE episodes SET encode_finish = (encode_begin + interval '$seconds seconds') WHERE id = $id;";
			$this->db->query($sql);

		}

	}
?>
