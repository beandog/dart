<?php

	class Chapters_Model extends DBTable {

		function __construct($id = null) {

			$table = "chapters";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and ix.
		 *
		 * @params int $track_id chapters.track_id
		 * @params int $ix chapters.ix
		 */
		public function find_chapters_id($track_id, $ix) {

			$sql = "SELECT id FROM chapters WHERE track_id = ".$this->db->quote($track_id)." AND ix = ".$this->db->quote($ix).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
