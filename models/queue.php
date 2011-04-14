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
		
		public function get_episodes($hostname) {
		
			$sql = "SELECT episode_id FROM ".$this->table." WHERE hostname = ".$this->db->quote($hostname).";";
			
 			$arr = $this->db->getCol($sql);
			
 			return $arr;
		
		}
		
	}
?>
