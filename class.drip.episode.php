<?

	class DripEpisode {
	
		private $id;
		private $track_id;
		private $order;
		private $title;
		private $season;
		private $part;
		private $starting_chapter;
		private $ending_chapter;
		private $alt_title_id;
		private $episode_number;
		
		function __construct($id = null) {
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getTrackID();
				$this->getSeason();
				$this->getPart();
				$this->getOrder();
				$this->getStartingChapter();
				$this->getEndingChapter();
				$this->alt_title_id = $this->getAltTitleID();
			} else {
				$this->newEpisode();
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
		
		private function newEpisode() {
			global $db;
			
			$sql = "SELECT nextval('episodes_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('episodes', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		
		function setTrackID($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'track' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->track_id = $int;
		}
		
		function getTrackID() {
			if(is_null($this->track_id)) {
				global $db;
				$sql = "SELECT track FROM episodes WHERE id = ".$this->getID().";";
				$this->track_id = $db->getOne($sql);
				if(is_null($this->track_id))
					$this->track_id = "";
			}
			
			return $this->track_id;
		}
		
		function setSeason($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'season' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->season = $int;
		}
		
		function getSeason() {
			if(is_null($this->season)) {
				global $db;
				$sql = "SELECT season FROM episodes WHERE id = ".$this->getID().";";
				$this->season = $db->getOne($sql);
				if(is_null($this->season))
					$this->season = "";
			}
			
			return $this->season;
		}
		
		function setPart($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
			
			$arr_update = array(
				'part' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->part = $int;
		}
		
		function getPart() {
			if(is_null($this->part)) {
				global $db;
				$sql = "SELECT part FROM episodes WHERE id = ".$this->getID().";";
				$this->part = $db->getOne($sql);
				if(is_null($this->part))
					$this->part = "";
			}
			
			return $this->part;
		}
		
		function setStartingChapter($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'starting_chapter' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->starting_chapter = $int;
		}
		
		function getStartingChapter() {
			if(is_null($this->starting_chapter)) {
				global $db;
				$sql = "SELECT starting_chapter FROM episodes WHERE id = ".$this->getID().";";
				$this->starting_chapter = $db->getOne($sql);
				if(is_null($this->starting_chapter))
					$this->starting_chapter = "";
			}
			
			return $this->starting_chapter;
		}
		
		function setEndingChapter($int) {
		
			global $db;
		
			$int = abs(intval($int));
			
			$arr_update = array(
				'ending_chapter' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->ending_chapter = $int;
		}
		
		function getEndingChapter() {
			if(is_null($this->ending_chapter)) {
				global $db;
				$sql = "SELECT ending_chapter FROM episodes WHERE id = ".$this->getID().";";
				$this->ending_chapter = $db->getOne($sql);
				if(is_null($this->ending_chapter))
					$this->ending_chapter = "";
			}
			
			return $this->ending_chapter;
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			global $db;
		
			if(!$this->id)
				$this->newEpisode();
			
			$arr_update = array(
				'title' => $str
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				global $db;
				$sql = "SELECT title FROM episodes WHERE id = ".$this->getID().";";
				$this->title = $db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function setOrder($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
			
			$arr_update = array(
				'episode_order' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->order = $int;
		}
		
		function getOrder() {
			if(is_null($this->order)) {
				global $db;
				$sql = "SELECT episode_order FROM episodes WHERE id = ".$this->getID().";";
				$this->order = $db->getOne($sql);
				if(is_null($this->order))
					$this->order = "";
			}
			
			return $this->order;
		}
		
		function getLength() {}
		
		function getAudioID($language = "en") {
			global $db;
			
			if(is_null($this->audio_id)) {
				$sql = "SELECT id, ix, language FROM audio_tracks WHERE track = ".$this->getTrackID()." ORDER BY ix;";
				$arr = $db->getAssoc($sql);
				
				if(count($arr) == 1) {
					$this->audio_id = key($arr);
					$this->audio_index = $arr[key($arr)]['ix'];
				} else {
					foreach($arr as $key => $row) {
						if($row['language'] == $language) {
							$this->audio_id = $key;
							$this->audio_index = $row['ix'];
							return $this->audio_id;
						}
					}
					
					// If it didn't find a matching one, use the first one
					reset($arr);
					$this->audio_id = key($arr);
					$this->audio_index = $arr[key($arr)]['ix'];
				}
			}
				
			return $this->audio_id;
			
		}
		
		// Return the first audio track with the default language
		// Seems to work for the most part.
		function getAudioIndex($language = "en") {
// 			global $db;
			
// 			$sql = "SELECT ix FROM audio_tracks WHERE track = ".$this->getTrackID()." AND lang = ".$db->quote($language)." ORDER BY ix;";
// 			$arr = $db->getCol($sql);
// 			
// 			if(is_null($arr))
// 				return 1;
			
// 			return current($arr);

			if(is_null($this->audio_index))
				$this->getAudioID($language);
			
			return $this->audio_index;
			
		}
		
		function getAudioAID($language = "en") {
		
			if(is_null($this->audio_index))
				$this->getAudioID($language);
			
			return $this->audio_index + 127;
		
		}
		
		function getEpisodeNumber() {
		
			global $db;
			
			// Find the # of episodes on previous discs for this season
			$sql = "SELECT COUNT(1) FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.tv_show_id = e2.tv_show_id AND e1.season = e2.season AND e1.volume = e2.volume AND e1.disc_id != e2.disc_id WHERE e2.episode_id = ".$this->getID()." AND e1.season <= e2.season AND ((e1.disc_number < e2.disc_number) OR (e1.disc_number = e2.disc_number AND e1.side < e2.side)) AND ((e1.alt_title_id IS NULL AND e2.alt_title_id IS NULL) OR (e1.alt_title_id = e2.alt_title_id));";
			$count = $db->getOne($sql);
		
			// Find the # of episodes before the one on the current disc
			$sql = "SELECT COUNT(1) FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.disc_id = e2.disc_id AND ((e1.episode_order < e2.episode_order) OR (e1.episode_order = e2.episode_order AND e1.episode_id < e2.episode_id))AND e1.side = e2.side AND e1.season = e2.season AND e1.episode_id != e2.episode_id WHERE e2.episode_id = ".$this->getID()." AND ((e1.alt_title_id IS NULL AND e2.alt_title_id IS NULL) OR (e1.alt_title_id = e2.alt_title_id));";
			$count += $db->getOne($sql);
			
			// Add one because we start counting at 1, not 0
			$count++;
			
			return $this->episode_number = $count;
		
		}
		
		function getEpisodeIndex() {
			
			$season = $this->getSeason();
			
			if(!$season)
				$season = 1;
			
			$str = $this->getEpisodeNumber();
			
			$str = str_pad($str, 2, 0, STR_PAD_LEFT);
			$str = $season.$str;
			
			return $str;
			
		}
		
		function setAltTitleID($int) {
		
			global $db;
		
			$int = abs(intval($int));
			if(!$int)
				$int = null;
			
			$arr_update = array(
				'alt_title_id' => $int
			);
			
			$db->autoExecute('episodes', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->alt_title_id = $int;
		
		}
		
		function getAltTitleID() {
		
 			if($this->alt_title_id)
 				return $this->alt_title_id;
			
			global $db;
			$sql = "SELECT alt_title_id FROM episodes WHERE id = ".$this->getID().";";
			
			$id = $db->getOne($sql);
			
			$this->alt_title_id = $id;
			
			return $id;
			
		}
		
		function getExportTitle() {
		
			if($this->export_title)
				return $this->export_title;
				
			global $db;
		
			$alt_title_id = $this->getAltTitleID();
		
			if($alt_title_id) {
				$sql = "SELECT title FROM alt_titles WHERE id = $alt_title_id;";
			} else {
				$sql = "SELECT tv_show_title FROM view_episodes WHERE episode_id = ".$this->getID().";";
			}
			
			$title = $db->getOne($sql);
			
			$this->export_title = $title;
			
			return $title;
			
		}
		
		function getNumChapters() {
			
			$int = $this->getEndingChapter - $this->getStartingChapter();
			
			if($int < 0)
				return 0;
			else
				return $int;
			
		}
		
	}
?>