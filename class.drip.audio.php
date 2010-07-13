<?

	class DripAudio {
	
		private $id;
		private $track_id;
		private $index;
		private $language;
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
				$this->newAudio();
			}
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function newAudio() {
			
			global $db;
			$sql = "SELECT nextval('audio_tracks_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('audio_tracks', $arr_insert, DB_AUTOQUERY_INSERT);
			
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
				$this->newAudio();
			
			$arr_update = array(
				'track' => $id
			);
			
			$db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $id;
		
		}
		
		function getTrackID() {
		
			global $db;
		
			if($this->track_id)
				return $this->track_id;
			
			$sql = "SELECT track FROM audio_tracks WHERE id = ".$this->getID().";";
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
				$this->newAudio();
			
			$arr_update = array(
				'ix' => $int
			);
			
			$db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->index = $int;
		
		}
		
		function getIndex() {
		
			global $db;
		
			if($this->index)
				return $this->index;
			
			$sql = "SELECT ix FROM audio_tracks WHERE id = ".$this->getID().";";
			$track = $db->getOne($sql);
			
			$this->index = $track;
			
			return $track;
		
		}
		
		/** Language **/
		function setLanguage($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'lang' => $str
			);
			
			$db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->language = $str;
		
		}
		
		public function getLanguage() {
			
			global $db;
			
			if(is_null($this->language)) {
				$sql = "SELECT lang FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->language = $db->getOne($sql);
			}
			
			return $this->language;
			
		}
		
		function setNumChannels($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
		
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'channels' => $int
			);
			
			$db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_channels = $int;
		
		}
		
		function getNumChannels() {
		
			global $db;
		
			if(is_null($this->num_channels)) {
				$sql = "SELECT channels FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->num_channels = $db->getOne($sql);
			}
			
			return $this->num_channels;
		
		}
		
		function setFormat($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'format' => $str
			);
			
			$db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->format = $str;
		
		}
		
		public function getFormat() {
			
			global $db;
			
			if(is_null($this->format)) {
				$sql = "SELECT format FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->format = $db->getOne($sql);
			}
			
			return $this->format;
			
		}
		
	}
?>