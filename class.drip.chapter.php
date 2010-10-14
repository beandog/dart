<?

	class DripChapter {
	
		private $id;
		private $track_id;
		private $number;
		private $length;
		
		function __construct($id = null) {
		
			$this->db = MDB2::singleton();
		
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTrackID();
				$this->getNumber();
				$this->getLength();
			} else {
				$this->newChapter();
			}
				
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function getID() {
			return $this->id;
		}
		
		function newChapter() {
			
			$sql = "SELECT nextval('chapters_id_seq');";
			$id = $this->db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$this->db->autoExecute('chapters', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		function setTrackID($id) {
		
			$id = abs(intval($id));
			if(!$id)
				return false;
		
			if(!$this->id)
				$this->newChapter();
			
			$arr_update = array(
				'track' => $id
			);
			
			$this->db->autoExecute('chapters', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $id;
		}
		
		function getTrackID() {
			return $this->track_id;
		}
		
		function setNumber($int) {
		
			$int = abs(intval($int));
			if(!$int)
				return false;
		
			if(!$this->id)
				$this->newChapter();
			
			$arr_update = array(
				'number' => $int
			);
			
			$this->db->autoExecute('chapters', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->number = $int;
		}
		
		function setLength($float) {
		
			if(!is_numeric($float))
				return false;
		
			if(!$this->id)
				$this->newChapter();
			
			$arr_update = array(
				'length' => $float
			);
			
			$this->db->autoExecute('chapters', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->length = $length;
			
		}
		
		function getLength() {
			return $this->length;
		}
	}
?>