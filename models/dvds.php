<?

	require_once 'mdb2/charlie.dvds.php';

	class Dvds_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "dvds";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
		public function get_episodes() {
		
			$sql = "SELECT e.id FROM episodes e INNER JOIN tracks t ON e.track_id = t.id INNER JOIN dvds d ON t.dvd_id = d.id WHERE d.id = ".$this->db->quote($this->id).";";
			
			$arr = $this->db->getCol($sql);
			
			return $arr;
		
		}
		
		public function get_tracks() {
		
			$sql = "SELECT id FROM tracks t WHERE dvd_id = ".$this->id." ORDER BY ix;";
			$arr = $this->db->getCol($sql);
			
			return $arr;
			
		}
		
		public function get_audio_preference() {
		
			$sql = "SELECT audio_preference FROM series_dvds WHERE dvd_id = ".$this->db->quote($this->id).";";
			$audio_preference = $this->db->getOne($sql);
			
			if($audio_preference === "0") {
			
				$sql = "SELECT c.default_audio_preference FROM collections c INNER JOIN series s ON s.collection_id = c.id INNER JOIN series_dvds sd ON sd.series_id = s.id AND sd.dvd_id = ".$this->db->quote($this->id).";";
				
				$audio_preference = $this->db->getOne($sql);
				
			}
			
			return $audio_preference;
		
		}
		
	}
?>