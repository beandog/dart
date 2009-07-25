<?

	class DripDisc {
	
		private $id;
		private $series_id;
		private $disc_id;
		private $title;
		private $side;
		private $volume;
		private $disc_number;
		
		function __construct($id = null) {
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getDiscID();
				$this->getSide();
				$this->getVolume();
			} else {
				$this->newDisc();
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
		
		private function newDisc() {
			global $db;
			
			$sql = "SELECT nextval('discs_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('discs', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		function setSeriesID($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'tv_show_id' => $int
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->series_id = $int;
		}
		
		function getSeriesID() {
			if(is_null($this->series_id)) {
				global $db;
				$sql = "SELECT tv_show_id FROM discs WHERE id = ".$this->getID().";";
				$this->series_id = $db->getOne($sql);
				if(is_null($this->series_id))
					$this->series_id = "";
			}
			
			return $this->series_id;
		}
		
		public function setDiscID($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newDisc();
			
			$arr_update = array(
				'disc_id' => $str
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->disc_id = $str;
		}
		
		function getDiscID() {
			if(is_null($this->disc_id)) {
				global $db;
				$sql = "SELECT disc_id FROM discs WHERE id = ".$this->getID().";";
				$this->disc_id = $db->getOne($sql);
				if(is_null($this->disc_id))
					$this->disc_id = "";
			}
			
			return $this->disc_id;
			
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			global $db;
		
			if(!$this->id)
				$this->newDisc();
			
			$arr_update = array(
				'disc_title' => $str
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				global $db;
				$sql = "SELECT disc_title FROM discs WHERE id = ".$this->getID().";";
				$this->title = $db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function setDiscNumber($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'disc' => $int
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->disc_number = $int;
		}
		
		function getDiscNumber() {
			if(is_null($this->disc_number)) {
				global $db;
				$sql = "SELECT disc FROM discs WHERE id = ".$this->getID().";";
				$this->disc_number = $db->getOne($sql);
				if(is_null($this->disc_number))
					$this->disc_number = "";
			}
			
			return $this->disc_number;
		}
		
		function setVolume($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'volume' => $int
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->volume = $int;
		}
		
		function getVolume() {
			if(!isset($this->volume)) {
				global $db;
				$sql = "SELECT volume FROM discs WHERE id = ".$this->getID().";";
				$this->volume = $db->getOne($sql);
			}
			
			return $this->volume;
		}
		
		
		function setSide($char) {
		
			global $db;
			
			$char = strtoupper($char);
			
			if(!($char == "A" || $char == "B"))
				$char = "";
			
			$arr_update = array(
				'side' => $char
			);
			
			$db->autoExecute('discs', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->side = $char;
			
		}
		
		function getSide() {
			if(!isset($this->side)) {
				global $db;
				$sql = "SELECT side FROM discs WHERE id = ".$this->getID().";";
				$this->side = $db->getOne($sql);
			}
			
			return $this->side;
		}
		
		function getTrackIDs() {
		
			global $db;
			$sql = "SELECT t.id FROM tracks t INNER JOIN discs d ON t.disc = d.id WHERE d.id = ".$this->getID()." ORDER BY t.track;";
			$arr = $db->getCol($sql);
			
			return $arr;
		
		}
		
		function getSeasons() {
		
			global $db;
			
			$sql = "SELECT DISTINCT season FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE d.id = ".$this->getID().";";
			$arr = $db->getCol($sql);
			
			return $arr;
		}
		
	}
?>