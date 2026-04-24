<?php

	class Cells_Model extends DBTable {

		function __construct($id = null) {

			$table = 'cells';

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

			$track_id = abs(intval($track_id));
			if(!$track_id) {
				trigger_error("Track number is zero", E_USER_ERROR);
				exit(1);
			}

			$sql = "SELECT id FROM cells WHERE track_id = $track_id AND ix = $ix;";
			$var = $this->get_one($sql);

			return $var;
		}

	}

?>
