<?

	class DripSeries {
	
		private $id;
		private $title;
		private $sorting_title;
		private $min_length;
		private $max_length;
		private $cartoon;
		private $unordered;
		private $cc;
		private $volumes;
		private $broadcast_year;
		private $production_studio;
		private $arr_production_studios;
		
		
		function __construct($id = null) {
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getSortingTitle();
				$this->getMinLength();
				$this->getMaxLength();
				$this->isCartoon();
				$this->isUnordered();
				$this->hasCC();
				$this->hasVolumes();
				$this->getBroadcastYear();
				$this->getProductionStudio();
			} else {
				$this->newSeries();
			}
				
			$this->arr_boxset = array(
				'Seasons',
				'Volumes',
				'Complete Series'
			);	
			
			$this->arr_production_studios = array(
				'DiC',
				'Filmation',
				'Hanna-Barbera',
				'Warner Bros.',
				'Walt Disney',
			);
			
		}
	
		function setID($id) {
			$id = abs(intval($id));
			if($id)
				$this->id = $id;
		}
		
		function getID() {
			return $this->id;
		}
		
		private function newSeries() {
			global $db;
			
			$sql = "SELECT nextval('tv_shows_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('tv_shows', $arr_insert, DB_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'title_long' => $str
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		public function setSortingTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			global $db;
		
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'title' => $str
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->sorting_title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				global $db;
				$sql = "SELECT title_long FROM tv_shows WHERE id = ".$this->getID().";";
				$this->title = $db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function getSortingTitle() {
			if(is_null($this->sorting_title)) {
				global $db;
				$sql = "SELECT title FROM tv_shows WHERE id = ".$this->getID().";";
				$str = $db->getOne($sql);
				$this->sorting_title = $db->getOne($sql);
				if(is_null($this->sorting_title))
					$this->sorting_title = "";
			}
			
			return $this->sorting_title;
		}
		
		function setMinLength($int) {
		
			global $db;
		
			if(is_null($int))
				$length = null;
			else
				$length = abs(intval($int));
			
			$arr_update = array(
				'min_len' => $length
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->min_length = $int;
		}
		
		function setMaxLength($int) {
		
			global $db;
		
			if(is_null($int))
				$length = null;
			else
				$length = abs(intval($int));
			
			$arr_update = array(
				'max_len' => $length
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->max_length = $int;
		}
		
		function getMinLength() {
			if(is_null($this->min_length)) {
				global $db;
				$sql = "SELECT min_len FROM tv_shows WHERE id = ".$this->getID().";";
				$this->min_length = $db->getOne($sql);
				if(is_null($this->min_length))
					$this->min_length = "";
			}
			
			return $this->min_length;
		}
		
		function getMaxLength() {
			if(is_null($this->max_length)) {
				global $db;
				$sql = "SELECT max_len FROM tv_shows WHERE id = ".$this->getID().";";
				$this->max_length = $db->getOne($sql);
				if(is_null($this->max_length))
					$this->max_length = "";
			}
			
			return $this->max_length;
		}
		
		function setCartoon($bool) {
		
			global $db;
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'cartoon' => $value
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->cartoon = $value;
			
		}
		
		function isCartoon() {
		
			if(is_null($this->cartoon)) {
				global $db;
				$sql = "SELECT cartoon FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $db->getOne($sql);
				if($value === 't')
					$this->cartoon = true;
				else
					$this->cartoon = false;
			}
			
			return $this->cartoon;
		
		}
		
		function setVolumes($bool) {
		
			global $db;
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'volumes' => $value
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->volumes = $value;
			
		}
		
		function hasVolumes() {
		
			if(is_null($this->volumes)) {
				global $db;
				$sql = "SELECT volumes FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $db->getOne($sql);
				if($value === 't')
					$this->volumes = true;
				else
					$this->volumes = false;
			}
			
			return $this->volumes;
		
		}
		
		function setUnordered($bool) {
		
			global $db;
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'unordered' => $value
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->unordered = $int;
			
		}
		
		function isUnordered() {
		
			if(is_null($this->unordered)) {
				global $db;
				$sql = "SELECT unordered FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $db->getOne($sql);
				if($value === 't')
					$this->unordered = true;
				else
					$this->unordered = false;
			}
			
			return $this->unordered;
		
		}
		
		function setCC($cc) {
		
			global $db;
		
			if(is_null($cc) || $cc == -1)
				$value = null;
			elseif($cc)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'cc' => $value
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->cc = $value;
			
		}
		
		function hasCC() {
		
			if(is_null($this->cc)) {
				global $db;
				$sql = "SELECT cc FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $db->getOne($sql);
				if($value === 't')
					$this->cc = true;
				elseif(is_null($value))
					$this->cc = null;
				else
					$this->cc = false;
			}
			
			return $this->cc;
		
		}
		
		function setNumSeasons($int) {
		
			global $db;
		
			if(!is_null($int))
				$int = abs(intval($int));
			
			$arr_update = array(
				'num_seasons' => $int
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_seasons = $int;
		}
		
		function setNumEpisodes($int) {
		
			global $db;
		
			if(!is_null($int))
				$int = abs(intval($int));
			
			$arr_update = array(
				'num_episodes' => $int
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_episodes = $int;
		}
		
		function getNumSeasons() {
			if(is_null($this->num_seasons)) {
				global $db;
				$sql = "SELECT num_seasons FROM tv_shows WHERE id = ".$this->getID().";";
				$this->num_seasons = $db->getOne($sql);
			}
			
			return $this->num_seasons;
		}
		
		function getNumEpisodes() {
			if(is_null($this->num_episodes)) {
				global $db;
				$sql = "SELECT num_episodes FROM tv_shows WHERE id = ".$this->getID().";";
				$this->num_episodes = $db->getOne($sql);
			}
			
			return $this->num_episodes;
		}
		
		function setBroadcastYear($year) {
		
			global $db;
			
			$year = abs(intval($year));
			
			if(!($year && strlen($year) == 4))
				$year = "";
		
			$arr_update = array(
				'start_year' => $year
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->broadcast_year = $year;
		}
		
		function getBroadcastYear() {
			if(is_null($this->broadcast_year)) {
				global $db;
				$sql = "SELECT start_year FROM tv_shows WHERE id = ".$this->getID().";";
				$this->broadcast_year = $db->getOne($sql);
			}
			
			return $this->broadcast_year;
		}
		
		function getProductionStudioArray() {
			return $this->arr_production_studios;
		}
		
		public function setProductionStudio($str) {
		
			$str = trim($str);
			if(!is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'production_studio' => $str
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->production_studio = $str;
		}
		
		function getProductionStudio() {
			if(is_null($this->production_studio)) {
				global $db;
				$sql = "SELECT production_studio FROM tv_shows WHERE id = ".$this->getID().";";
				$this->production_studio = $db->getOne($sql);
				if(is_null($this->production_studio))
					$this->production_studio = "";
			}
			
			return $this->production_studio;
			
		}
		
		public function setEpisodeList($str) {
		
			$str = trim($str);
			if(!is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'episode_list' => $str
			);
			
			$db->autoExecute('tv_shows', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->episode_list = $str;
		}
		
		function getEpisodeList() {
			if(is_null($this->episode_list)) {
				global $db;
				$sql = "SELECT episode_list FROM tv_shows WHERE id = ".$this->getID().";";
				$this->episode_list = $db->getOne($sql);
				if(is_null($this->episode_list))
					$this->episode_list = "";
			}
			
			return $this->episode_list;
			
		}
		
		function getNumDiscs() {
			global $db;
			
			$sql = "SELECT COUNT(1) FROM view_discs WHERE tv_show = ".$this->getID().";";
			return $db->getOne($sql);
		}
		
		function getNumAltTitles() {
		
			global $db;
			
			$sql = "SELECT COUNT(1) FROM alt_titles WHERE tv_show = ".$this->getID().";";
			return $db->getOne($sql);
		}
		
		
		function getAltTitles() {
			global $db;
			
			$sql = "SELECT id, title FROM alt_titles WHERE tv_show = ".$this->getID().";";
			return $db->getAssoc($sql);
		}
		
		function getLastSeasonNumber() {
			global $db;
			
			$sql = "SELECT MAX(season) FROM view_episodes WHERE tv_show_id = ".$this->getID().";";
			return $db->getOne($sql);
		}
		
		function getLastDiscNumber() {
			global $db;
			
			$sql = "SELECT MAX(disc_number) FROM view_episodes WHERE tv_show_id = ".$this->getID()." AND season = ".$this->getLastSeasonNumber().";";
			return $db->getOne($sql);
		}
		
		function getLastSide() {
			global $db;
			
			$sql = "SELECT MAX(side) FROM view_episodes WHERE tv_show_id = ".$this->getID()." AND season = ".$this->getLastSeasonNumber()." AND disc_number = ".$this->getLastDiscNumber().";";
			return $db->getOne($sql);
		}
		
		function getLastVolumeNumber() {
			global $db;
			
			$sql = "SELECT MAX(volume) FROM view_episodes WHERE tv_show_id = ".$this->getID().";";
			return $db->getOne($sql);
		}
	}
?>