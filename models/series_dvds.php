<?

	require_once 'mdb2/charlie.dvds.php';

	class Series_Dvds_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "series_dvds";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
// 		public function get_series_id() {
// 		
// 			$sql = "SELECT s.id INNER JOIN series_dvds sd ON sd.series_id = s.id INNER JOIN dvds d ON d.id = sd.dvd_id INNER JOIN tracks t ON t.dvd_id = d.id INNER JOIN episodes e ON e.track_id = t.id LIMIT 1;";
// 			
// 			$var = $this->db->getOne($sql);
// 			
// 			return $var;
// 		
// 		}
		
// 		public function get_episodes() {
// 		
// 			$sql = "SELECT e.id FROM episodes e INNER JOIN tracks t ON e.track_id = t.id INNER JOIN dvds d ON t.dvd_id = d.id WHERE d.id = ".$this->db->quote($this->id).";";
// 			
// 			$arr = $this->db->getCol($sql);
// 			
// 			return $arr;
// 		
// 		}
// 		
// 		public function get_tracks() {
// 		
// 			$sql = "SELECT id FROM tracks t WHERE dvd_id = ".$this->id." ORDER BY ix;";
// 			$arr = $this->db->getCol($sql);
// 			
// 			return $arr;
// 			
// 		}
		
	}
?>
