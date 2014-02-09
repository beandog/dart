<?php

	class Cells_Model extends DBTable {

		function __construct($id = null) {

			$table = "cells";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and ix.
		 *
		 * @params int $track_id cells.track_id
		 * @params int $ix cells.ix
		 */
		public function find_cells_id($track_id, $ix) {

			$sql = "SELECT id FROM cells WHERE track_id = ".$this->db->quote($track_id)." AND ix = ".$this->db->quote($ix).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
