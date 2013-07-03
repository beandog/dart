<?

	class DripDisc {
	
		private $id;
		private $series_id;
		private $uniq_id;
		private $title;
		private $side;
		private $season;
		private $volume;
		private $disc_number;
		private $filename;
		
		function __construct($id = null) {
		
			$this->db = MDB2::singleton();
		
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getUniqID();
				$this->getSide();
				$this->getVolume();
				$this->getSeason();
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

		public function getFilename() {

			$str = $this->getID().".";
			$str .= $this->getTitle();
			$str .= ".iso";
			return $str;

		}

		private function newDisc() {
			
			$sql = "SELECT nextval('discs_id_seq');";
			$id = $this->db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$this->db->autoExecute('discs', $arr_insert, MDB2_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		function setSeriesID($int) {
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'tv_show_id' => $int
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->series_id = $int;
		}
		
		function getSeriesID() {
			if(is_null($this->series_id)) {
				$sql = "SELECT tv_show_id FROM discs WHERE id = ".$this->getID().";";
				$this->series_id = $this->db->getOne($sql);
				if(is_null($this->series_id))
					$this->series_id = "";
			}
			
			return $this->series_id;
		}
		
		public function setUniqID($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
			
			if(!$this->id)
				$this->newDisc();
			
			$arr_update = array(
				'uniq_id' => $str
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->uniq_id = $str;
		}
		
		function getUniqID() {
			if(is_null($this->uniq_id)) {
				$sql = "SELECT uniq_id FROM discs WHERE id = ".$this->getID().";";
				$this->uniq_id = $this->db->getOne($sql);
				if(is_null($this->uniq_id))
					$this->uniq_id = "";
			}
			
			return $this->uniq_id;
			
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newDisc();
			
			$arr_update = array(
				'disc_title' => $str
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				$sql = "SELECT disc_title FROM discs WHERE id = ".$this->getID().";";
				$this->title = $this->db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function setDiscNumber($int) {
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'disc' => $int
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->disc_number = $int;
		}
		
		function getDiscNumber() {
			if(is_null($this->disc_number)) {
				$sql = "SELECT disc FROM discs WHERE id = ".$this->getID().";";
				$this->disc_number = $this->db->getOne($sql);
				if(is_null($this->disc_number))
					$this->disc_number = "";
			}
			
			return $this->disc_number;
		}
		
		function setVolume($int) {
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'volume' => $int
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->volume = $int;
		}
		
		function getVolume() {
			if(!isset($this->volume)) {
				$sql = "SELECT volume FROM discs WHERE id = ".$this->getID().";";
				$this->volume = $this->db->getOne($sql);
			}
			
			return $this->volume;
		}
		
		/** Default season **/
		function setSeason($int) {
		
			$int = abs(intval($int));
			
			if(!$int)
				$int = null;
			
			$arr_update = array(
				'season' => $int
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->season = $int;
		
		}
		
		function getSize() {
			$sql = "SELECT size FROM discs WHERE id = ".$this->getID().";";
			$var = $this->db->getOne($sql);
			
			return $var;
		}
		
		function setSize($int) {
		
			if(!is_numeric($int))
				$int = null;
			
			$arr_update = array(
				'size' => $int
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
		}
		
		function getSeason() {
			if(!isset($this->season)) {
				$sql = "SELECT season FROM discs WHERE id = ".$this->getID().";";
				$this->season = $this->db->getOne($sql);
			}
			
			return $this->season;
		}
		
		function setSide($char) {
		
			$char = strtoupper($char);
			
			if(!($char == "A" || $char == "B"))
				$char = "";
			
			$arr_update = array(
				'side' => $char
			);
			
			$this->db->autoExecute('discs', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->side = $char;
			
		}
		
		function getSide() {
			if(!isset($this->side)) {
				$sql = "SELECT side FROM discs WHERE id = ".$this->getID().";";
				$this->side = $this->db->getOne($sql);
			}
			
			return $this->side;
		}
		
		function getTrackIDs() {
		
			$sql = "SELECT t.id FROM tracks t INNER JOIN discs d ON t.disc = d.id WHERE d.id = ".$this->getID()." ORDER BY t.track;";
			$arr = $this->db->getCol($sql);
			
			return $arr;
		
		}
		
		function getSeasons() {
		
			$sql = "SELECT DISTINCT e.season FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE d.id = ".$this->getID().";";
			$arr = $this->db->getCol($sql);
			
			return $arr;
		}
		
	}
?>
