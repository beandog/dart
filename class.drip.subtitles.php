<?

	class DripSubtitles {
	
		private $id;
		private $track_id;
		private $index;
		private $language;
		private $langcode;
		private $format;
		private $stream_id;
	
		function __construct($id = null) {
		
			$this->db = MDB2::singleton();
		
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
		
		public function __toString() {
			return $this->id;
		}
	
		private function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		private function newRecord() {
			
			$sql = "SELECT nextval('subtitles_id_seq');";
			$id = $this->db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$this->db->autoExecute('subtitles', $arr_insert, MDB2_AUTOQUERY_INSERT);
			
			$this->setId($id);
			
		}
		
		public function getID() {
			return $this->id;
		}
		
		public function setTrackID($id) {
		
			$id = abs(intval($id));
			if(!$id)
				return false;
		
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'track' => $id
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $id;
		
		}
		
		public function getTrackID() {
		
			if($this->track_id)
				return $this->track_id;
			
			$sql = "SELECT track FROM subtitles WHERE id = ".$this->getID().";";
			$id = $this->db->getOne($sql);
			
			$this->track_id = $id;
			
			return $id;
		
		}
		
		public function setIndex($int) {
		
			$int = abs(intval($int));
			if(!$int)
				return false;
		
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'ix' => $int
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->index = $int;
		
		}
		
		public function getIndex() {
		
			if($this->index)
				return $this->index;
			
			$sql = "SELECT ix FROM subtitles WHERE id = ".$this->getID().";";
			$int = $this->db->getOne($sql);
			
			$this->index = $int;
			
			return $int;
		
		}
		
		/** Language **/
		public function setLanguage($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'language' => $str
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->language = $str;
		
		}
		
		public function getLanguage() {
			
			if(is_null($this->language)) {
				$sql = "SELECT language FROM subtitles WHERE id = ".$this->getID().";";
				$this->language = $this->db->getOne($sql);
			}
			
			return $this->language;
			
		}
		
		/** Langcode **/
		public function setLangcode($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'langcode' => $str
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->langcode = $str;
		
		}
		
		public function getLangcode() {
			
			if(is_null($this->language)) {
				$sql = "SELECT langcode FROM subtitles WHERE id = ".$this->getID().";";
				$this->langcode = $this->db->getOne($sql);
			}
			
			return $this->langcode;
			
		}
		
		/** Stream ID **/
		public function setStreamID($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'stream_id' => $str
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->stream_id = $str;
		
		}
		
		public function getStreamID() {
		
			if(is_null($this->stream_id)) {
				$sql = "SELECT stream_id FROM subtitles WHERE id = ".$this->getID().";";
				$this->stream_id = $this->db->getOne($sql);
			}
			
			return $this->stream_id;
			
		}
		
		/** Format */
		public function setFormat($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newRecord();
			
			$arr_update = array(
				'format' => $str
			);
			
			$this->db->autoExecute('subtitles', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->format = $str;
		
		}
		
		public function getFormat() {
			
			if(is_null($this->format)) {
				$sql = "SELECT format FROM subtitles WHERE id = ".$this->getID().";";
				$this->format = $this->db->getOne($sql);
			}
			
			return $this->format;
			
		}
		
	}
?>