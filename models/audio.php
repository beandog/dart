<?

	class Audio_Model extends DBTable {

		function __construct($id = null) {

			$table = "audio";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and index.
		 *
		 * @params int $track_id audio.track_id
		 * @params int $ix audio.ix
		 */
		public function find_audio_id($track_id, $ix) {

			$sql = "SELECT id FROM audio WHERE track_id = ".$this->db->quote($track_id)." AND ix = ".$this->db->quote($ix).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
