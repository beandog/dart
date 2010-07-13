<?

	class DripAudio {
	
		private $id;
		private $track_id;
		private $index;
		private $language;
		private $langcode;
		private $num_channels;
		private $format;
	
		function __construct($id = null) {
			if($id) {
				$this->setID($id);
				$this->getTrackID();
				$this->getIndex();
				$this->getLanguage();
 				$this->getNumChannels();
 				$this->getFormat();
			} else {
				$this->newRecord();
			}
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function newRecord() {
			
			global $db;
			$sql = "SELECT nextval('subtitles_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('subtitles', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
			
		}
		
		function getID() {
			return $this->id;
		}
		
		function setTrackID($id) {
		
			global $db;
		
			$id = abs(intval($id));
			if(!$id)
				return false;
		
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'track' => $id
			);
			
			$db->autoExecute('subtitles', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $id;
		
		}
		
		function getTrackID() {
		
			global $db;
		
			if($this->track_id)
				return $this->track_id;
			
			$sql = "SELECT track FROM subtitles WHERE id = ".$this->getID().";";
			$id = $db->getOne($sql);
			
			$this->track_id = $id;
			
			return $id;
		
		}
		
		function setIndex($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				return false;
		
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'ix' => $int
			);
			
			$db->autoExecute('subtitles', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->index = $int;
		
		}
		
		function getIndex() {
		
			global $db;
		
			if($this->index)
				return $this->index;
			
			$sql = "SELECT ix FROM subtitles WHERE id = ".$this->getID().";";
			$int = $db->getOne($sql);
			
			$this->index = $int;
			
			return $int;
		
		}
		
		/** Language **/
		function setLanguage($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'language' => $str
			);
			
			$db->autoExecute('subtitles', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->language = $str;
		
		}
		
		public function getLanguage() {
			
			global $db;
			
			if(is_null($this->language)) {
				$sql = "SELECT language FROM subtitles WHERE id = ".$this->getID().";";
				$this->language = $db->getOne($sql);
			}
			
			return $this->language;
			
		}
		
		/** Langcode **/
		function setLangcode($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'langcode' => $str
			);
			
			$db->autoExecute('subtitles', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->langcode = $str;
		
		}
		
		public function getLangcode() {
			
			global $db;
			
			if(is_null($this->language)) {
				$sql = "SELECT langcode FROM subtitles WHERE id = ".$this->getID().";";
				$this->langcode = $db->getOne($sql);
			}
			
			return $this->langcode;
			
		}
		
		function setFormat($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'format' => $str
			);
			
			$db->autoExecute('subtitles', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->format = $str;
		
		}
		
		public function getFormat() {
			
			global $db;
			
			if(is_null($this->format)) {
				$sql = "SELECT format FROM subtitles WHERE id = ".$this->getID().";";
				$this->format = $db->getOne($sql);
			}
			
			return $this->format;
			
		}
		
	}
?>