<?

	require_once 'mdb2/charlie.dvds.php';

	class Queue_Model extends DBTable {
	
		function __construct($id = null) {
		
			$this->table = "queue";
			
			$this->id = parent::__construct($this->table, $id);
				
		}
		
		public function add_episode($id, $hostname) {
		
			$this->remove_episode($id);
		
			$this->create_new();
			$this->episode_id = $id;
			$this->hostname = $hostname;
		
		}
		
		public function remove_episode($id) {
		
			$sql = "DELETE FROM ".$this->table." WHERE episode_id = ".$this->db->quote($id).";";
			
			return $this->db->query($sql);
		
		}
		
		public function get_episodes($hostname, $skip = 0, $max = 0) {
		
			if($skip > 0)
				$sql = " OFFSET $skip";
			
			if($max > 0)
				$sql .= " LIMIT $max";
		
			$sql = "SELECT episode_id FROM ".$this->table." WHERE hostname = ".$this->db->quote($hostname)." ORDER BY id $sql;";
			
 			$arr = $this->db->getCol($sql);
			
 			return $arr;
		
		}
		
		public function get_dvds($hostname) {
		
			$sql = "SELECT DISTINCT dvd_id FROM view_episodes e JOIN queue q ON q.episode_id = e.episode_id WHERE q.hostname = ".$this->db->quote($hostname).";";
			
			$arr = $this->db->getCol($sql);
			
 			return $arr;
 			
		}
		
	}
?>