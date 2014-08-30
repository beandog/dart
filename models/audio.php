<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Audio_Model extends DBTable {

		function __construct($id = null) {

			$table = "audio";

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
		 * @param int $track_id audio.track_id
		 * @param int $ix audio.ix
		 */
		public function find_audio_id($track_id, $ix) {

			$track_id = abs(intval($track_id));
			$ix = abs(intval($ix));

			$sql = "SELECT id FROM audio WHERE track_id = $track_id AND ix = $ix;";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
