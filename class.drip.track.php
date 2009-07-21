<?

	class DripTrack {
	
		private $id;
		private $disc;
		private $track;
		private $length;
		private $bad;
		private $multiple_episodes;
		private $aspect_ratio;
		private $order;
		private $language;
		private $audio_track;
		private $audio_track_id;
		private $audio_track_language;
		private $audio_track_channels;
		private $audio_track_format;
	
		function __construct($id = null) {
			if($id) {
				$this->setID($id);
				$this->getDisc();
				$this->getTrackNumber();
 				$this->getLength();
 				$this->isBadTrack();
 				$this->hasMultipleEpisodes();
 				$this->getAspectRatio();
 				$this->getOrder();

			} else {
				$this->newTrack();
			}
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function newTrack() {
			
			global $db;
			$sql = "SELECT nextval('tracks_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('tracks', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
			
		}
		
		function getID() {
			return $this->id;
		}
		
		function setDisc($id) {
		
			global $db;
		
			$id = abs(intval($id));
			if(!$id)
				return false;
		
			if(!$this->id)
				$this->newTrack();
			
			$arr_update = array(
				'disc' => $id
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->disc = $id;
		
		}
		
		function getDisc() {
		
			global $db;
		
			if($this->disc)
				return $this->disc;
			
			$sql = "SELECT disc FROM tracks WHERE id = ".$this->getID().";";
			$id = $db->getOne($sql);
			
			$this->disc = $id;
			
			return $id;
		
		}
		
		function setTrackNumber($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				return false;
		
			if(!$this->id)
				$this->newTrack();
			
			$arr_update = array(
				'track' => $int
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track = $int;
		
		}
		
		function getTrackNumber() {
		
			global $db;
		
			if($this->track)
				return $this->track;
			
			$sql = "SELECT track FROM tracks WHERE id = ".$this->getID().";";
			$track = $db->getOne($sql);
			
			$this->track = $track;
			
			return $track;
		
		}
		
		function setLength($float) {
		
			global $db;
		
			if(!is_numeric($float))
				return false;
		
			if(!$this->id)
				$this->newTrack();
			
			$arr_update = array(
				'len' => $float
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->length = $float;
		
		}
		
		function getLength() {
		
			global $db;
		
			if($this->length)
				return $this->length;
			
			$sql = "SELECT len FROM tracks WHERE id = ".$this->getID().";";
			$length = $db->getOne($sql);
			
			$this->length = $length;
			
			return $length;
		
		}
		
		function setBadTrack($bool = true) {
		
			global $db;
			
			if(!$this->id)
				$this->newTrack();
			
			if($bool === true) {
				$bad_track = "t";
				$this->bad = true;
			} else {
				$bad_track = "f";
				$this->bad = false;
			}
			
			$arr_update = array(
				'bad_track' => $bad_track
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
		}
		
		function isBadTrack() {
		
			global $db;
			
			if(isset($this->bad))
				return $this->bad;
			
			$sql = "SELECT bad_track FROM tracks WHERE id = ".$this->getID().";";
			$bad_track = $db->getOne($sql);
			
			if($bad_track == "t")
				$this->bad = true;
			else
				$this->bad = false;
			
			return $this->bad;
			
		}
		
		function setMultipleEpisodes($bool = true) {
		
			global $db;
			
			if(!$this->id)
				$this->newTrack();
			
			if($bool === true) {
				$multi = "t";
				$this->multiple_episodes = true;
			} else {
				$multi = "f";
				$this->multiple_episodes = false;
			}
			
			$arr_update = array(
				'multi' => $multi
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
		}
		
		function hasMultipleEpisodes() {
		
			global $db;
			
			if(isset($this->multiple_episodes))
				return $this->multiple_episodes;
			
			$sql = "SELECT multi FROM tracks WHERE id = ".$this->getID().";";
			$multi = $db->getOne($sql);
			
			if($multi == "t")
				$this->multiple_episodes = true;
			else
				$this->multiple_episodes = false;
			
			return $this->multiple_episodes;
			
		}
		
		function setAspectRatio($str) {
		
			global $db;
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!($str == '4/3' || $str == '16/9'))
				return false;
			
			if(!$this->id)
				$this->newTrack();
			
			$arr_update = array(
				'aspect' => $str
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->aspect_ratio = $str;
		
		}
		
		public function getAspectRatio() {
			
			global $db;
			
			if($this->aspect_ratio)
				return $this->aspect_ratio;
			
			$sql = "SELECT aspect FROM tracks WHERE id = ".$this->getID().";";
			$this->aspect_ratio = $db->getOne($sql);
			
			return $this->aspect_ratio;
			
		}
		
		function setOrder($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
		
			if(!$this->id)
				$this->newTrack();
			
			$arr_update = array(
				'order' => $int
			);
			
			$db->autoExecute('tracks', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->order = $int;
		
		}
		
		function getOrder() {
		
			global $db;
		
			if($this->order)
				return $this->order;
			
			$sql = "SELECT track_order FROM tracks WHERE id = ".$this->getID().";";
			$this->order = $db->getOne($sql);
			
			return $this->order;
		
		}
	
	}
?>