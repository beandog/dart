<?

	require_once 'mdb2/charlie.dvds.php';

	class Tracks_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "tracks";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
		public function get_audio_streams() {
		
			$sql = "SELECT * FROM audio WHERE track_id = ".$this->db->quote($this->id)." ORDER BY ix;";
			
			$arr = $this->db->getAll($sql);
			
			return $arr;
		
		}
		
	}
?>
