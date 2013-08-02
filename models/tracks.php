<?

	class Tracks_Model extends DBTable {

		function __construct($id = null) {

			$table = "tracks";

			$this->id = parent::__construct($table, $id);

		}

		public function get_audio_streams() {

			$sql = "SELECT * FROM audio WHERE track_id = ".$this->db->quote($this->id)." ORDER BY langcode = 'en' DESC, channels DESC, format = 'dts' DESC, streamid;";

			$arr = $this->db->getAll($sql);

			return $arr;

		}

		public function get_best_quality_audio_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id).") ORDER BY CASE WHEN format = 'dts' THEN 1 ELSE 0 END, streamid LIMIT 1;";

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->db->quote($this->id).") ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_first_english_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' ORDER BY streamid LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function get_first_english_subp() {

			$sql = "SELECT ix FROM subp WHERE track_id = ".$this->db->quote($this->id)." AND langcode = 'en' ORDER BY ix LIMIT 1;";

			$var = $this->db->getOne($sql);

			return $var;

		}

	}
?>
