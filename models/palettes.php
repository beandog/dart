<?

	class Palettes_Model extends DBTable {

		function __construct($id = null) {

			$table = "palettes";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * track_id and color.
		 *
		 * @params int $track_id palettes.track_id
		 * @params int $color palettes.color
		 */
		public function find_palettes_id($track_id, $ix) {

			$sql = "SELECT id FROM palettes WHERE track_id = ".$this->db->quote($track_id)." AND color = ".$this->db->quote($color).";";
			$var = $this->db->getOne($sql);

			return $var;
		}

	}
?>
