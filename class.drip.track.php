<?

	class DripTrack {
	
		private $id;
		private $disc;
		private $track;
		private $length;
		private $bad;
		private $aspect_ratio;
		private $order;
		private $num_chapters;
		private $num_episodes;
		private $disc_id;
	
		function __construct($id = null) {
			if($id) {
				$this->setID($id);
				$this->getDiscID();
				$this->getTrackNumber();
 				$this->getLength();
 				$this->isBadTrack();
 				$this->getAspectRatio();
 				$this->getOrder();
				$this->num_episodes = $this->getNumEpisodes();
				$this->valid_length = $this->validLength();
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
		
		function setDiscID($id) {
		
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
		
		function getDiscID() {
		
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
		
		function getNumChapters() {
		
			if(is_null($this->num_chapters)) {
				global $db;
				$sql = "SELECT COUNT(1) FROM chapters WHERE track = ".$this->getID().";";
				$num_chapters = $db->getOne($sql);
				
				$this->num_chapters = $num_chapters;
			}
			return $this->num_chapters;
		}
		
		function getEpisodeIDs() {
		
			global $db;
			
			$sql = "SELECT id FROM episodes WHERE track = ".$this->getID()." ORDER BY season, episode_order, starting_chapter, id;";
			$arr = $db->getCol($sql);
			
			return $arr;
		}
		
		function getNumEpisodes() {
		
			if(is_null($this->num_episodes)) {
				global $db;
				$sql = "SELECT COUNT(1) FROM episodes WHERE track = ".$this->getID().";";
				$int = $db->getOne($sql);
				
				return $int;
			} else {
				return $this->num_episodes;
			}
		}
		
		/**
		 * See if a track meets the valid length for an episode
		 */
		function validLength() {
		
			if(is_null($this->valid_length)) {
		
				global $db;
				$sql = "SELECT min_len, max_len FROM tv_shows tv INNER JOIN discs d ON d.tv_show_id = tv.id INNER JOIN tracks t ON t.disc = d.id WHERE t.id = ".$this->getID().";";
				
				$row = $db->getRow($sql);
				
				if(($this->getLength() >= $row['min_len']) && ($this->getLength() <= $row['max_len']))
					return true;
				else
					return false;
			} else {
				return $this->valid_length;
			}
			
		}
		
		public function getDefaultStreamID() {
		
			global $db;
		
// 			$sql = "SELECT stream_id from audio_tracks WHERE track = ".$this->getID()." ORDER BY lang = 'en' DESC, channels DESC, stream_id LIMIT 1;";
// 			$var = $db->getOne($sql);
			
			$sql = "SELECT id, ix, lang, stream_id FROM audio_tracks WHERE track = ".$this->getID()." ORDER BY lang = 'en' DESC, channels DESC, format = 'dts' DESC, stream_id;";
			$arr = $db->getAssoc($sql);
			
			if(count($arr)) {
				foreach($arr as $row) {
					if($row['lang'] == 'en') {
						$audio_track = $row['stream_id'];
						break;
					}
					
					if(!$audio_track)
						$audio_track = "0x80";
					
				}
			} else {
				$audio_track = "0x80";
			}
			
			return $audio_track;
				
		}
		
		/**
		 * Unlike audio streams, you are not guaranteed a subtitle stream in English.
		 * 
		 */
		public function getDefaultSubtitleIndex() {
		
			global $db;
		
			$sql = "SELECT id, ix, langcode FROM subtitles WHERE track = ".$this->getID()." AND format = 'VobSub' ORDER BY langcode = 'en' DESC, ix;";
			$arr = $db->getAssoc($sql);
			
			if(count($arr)) {
				foreach($arr as $row) {
					if($row['langcode'] == 'en') {
						$track = $row['ix'];
						break;
					}
				}
			}
			
			return $track;
				
		}
		
	
	}
?>