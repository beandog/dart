<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Subp_Model extends DBTable {

		function __construct($id = null) {

			$table = "subp";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and ix.
		 *
		 * Note that normally it seems like looking up on
		 * streamid would make more sense, but the original
		 * activerecord code would do a lookup on 'ix' instead,
		 * so I'm keeping it the same.
		 *
		 * @params int $track_id subp.track_id
		 * @params int $ix subp.ix
		 */
		public function find_subp_id($track_id, $ix) {

			$sql = "SELECT id FROM subp WHERE track_id = ".$this->db->quote($track_id)." AND ix = ".$this->db->quote($ix).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
