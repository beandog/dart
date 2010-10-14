<?

	class DripAudio {
	
		private $id;
		private $track_id;
		private $index;
		private $language;
		private $num_channels;
		private $format;
	
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
				$this->newAudio();
			}
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function newAudio() {
			
			$sql = "SELECT nextval('audio_tracks_id_seq');";
			$id = $this->db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$this->db->autoExecute('audio_tracks', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
			
		}
		
		function getID() {
			return $this->id;
		}
		
		function setTrackID($id) {
		
			$id = abs(intval($id));
			if(!$id)
				return false;
		
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'track' => $id
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $id;
		
		}
		
		function getTrackID() {
		
			if($this->track_id)
				return $this->track_id;
			
			$sql = "SELECT track FROM audio_tracks WHERE id = ".$this->getID().";";
			$id = $this->db->getOne($sql);
			
			$this->track_id = $id;
			
			return $id;
		
		}
		
		function setIndex($int) {
		
			$int = abs(intval($int));
			if(!$int)
				return false;
		
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'ix' => $int
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->index = $int;
		
		}
		
		function getIndex() {
		
			if($this->index)
				return $this->index;
			
			$sql = "SELECT ix FROM audio_tracks WHERE id = ".$this->getID().";";
			$track = $this->db->getOne($sql);
			
			$this->index = $track;
			
			return $track;
		
		}
		
		/** Language **/
		function setLanguage($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'lang' => $str
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->language = $str;
		
		}
		
		public function getLanguage() {
			
			if(is_null($this->language)) {
				$sql = "SELECT lang FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->language = $this->db->getOne($sql);
			}
			
			return $this->language;
			
		}
		
		/** Language **/
		function setStreamID($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'stream_id' => $str
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->stream_id = $str;
		
		}
		
		public function getStreamID() {
			
			if(is_null($this->stream_id)) {
				$sql = "SELECT stream_id FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->stream_id = $this->db->getOne($sql);
			}
			
			return $this->stream_id;
			
		}
		
		function setNumChannels($int) {
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
		
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'channels' => $int
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_channels = $int;
		
		}
		
		function getNumChannels() {
		
			if(is_null($this->num_channels)) {
				$sql = "SELECT channels FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->num_channels = $this->db->getOne($sql);
			}
			
			return $this->num_channels;
		
		}
		
		function setFormat($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newAudio();
			
			$arr_update = array(
				'format' => $str
			);
			
			$this->db->autoExecute('audio_tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->format = $str;
		
		}
		
		public function getFormat() {
			
			if(is_null($this->format)) {
				$sql = "SELECT format FROM audio_tracks WHERE id = ".$this->getID().";";
				$this->format = $this->db->getOne($sql);
			}
			
			return $this->format;
			
		}
		
	}
?>