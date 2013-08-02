<?

	class Audio_Model extends DBTable {

		function __construct($id = null) {

			$table = "audio";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and streamid.
		 *
		 * @params int $track_id audio.track_id
		 * @params int $streamid audio.streamid
		 */
		public function find_audio_id($track_id, $streamid) {

			$sql = "SELECT id FROM audio WHERE track_id = ".$this->db->quote($track_id)." AND streamid = ".$this->db->quote($streamid).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
