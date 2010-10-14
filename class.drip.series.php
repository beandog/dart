<?

	class DripSeries {
	
		private $id;
		private $title;
		private $sorting_title;
		private $min_length;
		private $max_length;
		private $cartoon;
		private $grayscale;
		private $movie;
		private $unordered;
		private $cc;
		private $volumes;
		private $broadcast_year;
		private $production_studio;
		private $arr_production_studios;
		private $handbrake;
		private $handbrake_preset;
		
		function __construct($id = null) {
		
			$this->db = MDB2::singleton();
			
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getSortingTitle();
				$this->getMinLength();
				$this->getMaxLength();
				$this->isCartoon();
				$this->isUnordered();
				$this->isMovie();
				$this->isGrayscale();
				$this->hasCC();
				$this->hasSDH();
				$this->hasVolumes();
				$this->getBroadcastYear();
				$this->getProductionStudio();
				$this->useHandbrake();
				$this->getHandbrakePreset();
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
		
		function __toString() {
			return (string)$this->id;
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
			
			$sql = "SELECT nextval('tv_shows_id_seq');";
			$id = $this->db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$this->db->autoExecute('tv_shows', $arr_insert, MDB2_AUTOQUERY_INSERT);
			
			$this->setId($id);
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
			
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'title_long' => $str
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		public function setSortingTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'title' => $str
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->sorting_title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				$sql = "SELECT title_long FROM tv_shows WHERE id = ".$this->getID().";";
				$this->title = $this->db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function getSortingTitle() {
			if(is_null($this->sorting_title)) {
				$sql = "SELECT title FROM tv_shows WHERE id = ".$this->getID().";";
				$str = $this->db->getOne($sql);
				$this->sorting_title = $this->db->getOne($sql);
				if(is_null($this->sorting_title))
					$this->sorting_title = "";
			}
			
			return $this->sorting_title;
		}
		
		function setMinLength($int) {
		
			if(is_null($int))
				$length = null;
			else
				$length = abs(intval($int));
			
			$arr_update = array(
				'min_len' => $length
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->min_length = $int;
		}
		
		function setMaxLength($int) {
		
			if(is_null($int))
				$length = null;
			else
				$length = abs(intval($int));
			
			$arr_update = array(
				'max_len' => $length
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->max_length = $int;
		}
		
		function getMinLength() {
			if(is_null($this->min_length)) {
				$sql = "SELECT min_len FROM tv_shows WHERE id = ".$this->getID().";";
				$this->min_length = $this->db->getOne($sql);
				if(is_null($this->min_length))
					$this->min_length = "";
			}
			
			return $this->min_length;
		}
		
		function getMaxLength() {
			if(is_null($this->max_length)) {
				$sql = "SELECT max_len FROM tv_shows WHERE id = ".$this->getID().";";
				$this->max_length = $this->db->getOne($sql);
				if(is_null($this->max_length))
					$this->max_length = "";
			}
			
			return $this->max_length;
		}
		
		function setCartoon($bool = true) {
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'cartoon' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->cartoon = $value;
			
		}
		
		function isCartoon() {
		
			if(is_null($this->cartoon)) {
				$sql = "SELECT cartoon FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->cartoon = true;
				else
					$this->cartoon = false;
			}
			
			return $this->cartoon;
		
		}
		
		function setMovie($bool = true) {
		
			if($bool)
				$value = 4;
			else
				$value = 1;
			
			$arr_update = array(
				'collection' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->movie = $value;
			
		}
		
		function isMovie() {
		
			if(is_null($this->movie)) {
				$sql = "SELECT collection FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 4)
					$this->movie = true;
				else
					$this->movie = false;
			}
			
			return $this->movie;
		
		}
		
		function setGrayscale($bool = true) {
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'grayscale' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->grayscale = $value;
			
		}
		
		function isGrayscale() {
		
			if(is_null($this->grayscale)) {
				$sql = "SELECT grayscale FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->grayscale = true;
				else
					$this->grayscale = false;
			}
			
			return $this->grayscale;
		
		}
		
		function setHandbrake($bool = true) {
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'handbrake' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->handbrake = $value;
			
		}
		
		function useHandbrake() {
		
			if(is_null($this->handbrake)) {
				$sql = "SELECT handbrake FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->handbrake = true;
				else
					$this->handbrake = false;
			}
			
			return $this->handbrake;
		
		}
		
		function setVolumes($bool = true) {
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'volumes' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->volumes = $value;
			
		}
		
		function hasVolumes() {
		
			if(is_null($this->volumes)) {
				$sql = "SELECT volumes FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->volumes = true;
				else
					$this->volumes = false;
			}
			
			return $this->volumes;
		
		}
		
		function setUnordered($bool = true) {
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'unordered' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->unordered = $int;
			
		}
		
		function isUnordered() {
		
			if(is_null($this->unordered)) {
				$sql = "SELECT unordered FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->unordered = true;
				else
					$this->unordered = false;
			}
			
			return $this->unordered;
		
		}
		
		function setCC($cc = true) {
		
			if(is_null($cc) || $cc == -1)
				$value = null;
			elseif($cc)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'cc' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->cc = $value;
			
		}
		
		function hasCC() {
		
			if(is_null($this->cc)) {
				$sql = "SELECT cc FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->cc = true;
				elseif(is_null($value))
					$this->cc = null;
				else
					$this->cc = false;
			}
			
			return $this->cc;
		
		}
		
		function setSDH($sdh = true) {
		
			if(is_null($sdh) || $sdh == -1)
				$value = null;
			elseif($sdh)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'sdh' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->sdh = $value;
			
		}
		
		function hasSDH() {
		
			if(is_null($this->sdh)) {
				$sql = "SELECT sdh FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->sdh = true;
				elseif(is_null($value))
					$this->sdh = null;
				else
					$this->sdh = false;
			}
			
			return $this->sdh;
		
		}
		
		function setVobSub($bool = true) {
		
			if(is_null($bool) || $bool == -1)
				$value = null;
			elseif($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'vobsub' => $value
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->vobsub = $value;
			
		}
		
		function hasVobSub() {
		
			if(is_null($this->vobsub)) {
				$sql = "SELECT vobsub FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				if($value === 't')
					$this->vobsub = true;
				elseif(is_null($value))
					$this->vobsub = null;
				else
					$this->vobsub = false;
			}
			
			return $this->vobsub;
		
		}
		
		function setHandbrakePreset($int) {
		
			if(!is_null($int))
				$int = abs(intval($int));
			
			$arr_update = array(
				'handbrake_preset' => $int
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->handbrake_preset = $int;
		}
		
		function getHandbrakePreset() {
		
			if(is_null($this->handbrake_preset)) {
				$sql = "SELECT handbrake_preset FROM tv_shows WHERE id = ".$this->getID().";";
				$value = $this->db->getOne($sql);
				$this->handbrake_preset = $value;
			}
			
			return $this->handbrake_preset;
		
		}
		
		function setNumSeasons($int) {
		
			if(!is_null($int))
				$int = abs(intval($int));
			
			$arr_update = array(
				'num_seasons' => $int
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_seasons = $int;
		}
		
		function setNumEpisodes($int) {
		
			if(!is_null($int))
				$int = abs(intval($int));
			
			$arr_update = array(
				'num_episodes' => $int
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->num_episodes = $int;
		}
		
		function getNumSeasons() {
			if(is_null($this->num_seasons)) {
				$sql = "SELECT num_seasons FROM tv_shows WHERE id = ".$this->getID().";";
				$this->num_seasons = $this->db->getOne($sql);
			}
			
			return $this->num_seasons;
		}
		
		function getNumEpisodes() {
			if(is_null($this->num_episodes)) {
				$sql = "SELECT num_episodes FROM tv_shows WHERE id = ".$this->getID().";";
				$this->num_episodes = $this->db->getOne($sql);
			}
			
			return $this->num_episodes;
		}
		
		function setBroadcastYear($year) {
		
			$year = abs(intval($year));
			
			if(!($year && strlen($year) == 4))
				$year = "";
		
			$arr_update = array(
				'start_year' => $year
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->broadcast_year = $year;
		}
		
		function getBroadcastYear() {
			if(is_null($this->broadcast_year)) {
				$sql = "SELECT start_year FROM tv_shows WHERE id = ".$this->getID().";";
				$this->broadcast_year = $this->db->getOne($sql);
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
			
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'production_studio' => $str
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->production_studio = $str;
		}
		
		function getProductionStudio() {
			if(is_null($this->production_studio)) {
				$sql = "SELECT production_studio FROM tv_shows WHERE id = ".$this->getID().";";
				$this->production_studio = $this->db->getOne($sql);
				if(is_null($this->production_studio))
					$this->production_studio = "";
			}
			
			return $this->production_studio;
			
		}
		
		public function setEpisodeList($str) {
		
			$str = trim($str);
			if(!is_string($str))
				return false;
			
			if(!$this->id)
				$this->newSeries();
			
			$arr_update = array(
				'episode_list' => $str
			);
			
			$this->db->autoExecute('tv_shows', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->episode_list = $str;
		}
		
		function getEpisodeList() {
			if(is_null($this->episode_list)) {
				$sql = "SELECT episode_list FROM tv_shows WHERE id = ".$this->getID().";";
				$this->episode_list = $this->db->getOne($sql);
				if(is_null($this->episode_list))
					$this->episode_list = "";
			}
			
			return $this->episode_list;
			
		}
		
		function getNumDiscs() {
			$sql = "SELECT COUNT(1) FROM view_discs WHERE tv_show = ".$this->getID().";";
			return $this->db->getOne($sql);
		}
		
		function getNumAltTitles() {
		
			$sql = "SELECT COUNT(1) FROM alt_titles WHERE tv_show = ".$this->getID().";";
			return $this->db->getOne($sql);
		}
		
		
		function getAltTitles() {
			$sql = "SELECT id, title FROM alt_titles WHERE tv_show = ".$this->getID().";";
			return $this->db->getAssoc($sql);
		}
		
		function getLastSeasonNumber() {
			
			$sql = "SELECT MAX(season) FROM view_episodes WHERE tv_show_id = ".$this->getID().";";
			return $this->db->getOne($sql);
		}
		
		function getLastDiscNumber() {
			
			$sql = "SELECT MAX(disc_number) FROM view_episodes WHERE tv_show_id = ".$this->getID()." AND season = ".$this->getLastSeasonNumber().";";
			return $this->db->getOne($sql);
		}
		
		function getLastSide() {
			
			$sql = "SELECT MAX(side) FROM view_episodes WHERE tv_show_id = ".$this->getID()." AND season = ".$this->getLastSeasonNumber()." AND disc_number = ".$this->getLastDiscNumber().";";
			return $this->db->getOne($sql);
		}
		
		function getLastVolumeNumber() {
			
			$sql = "SELECT MAX(volume) FROM view_episodes WHERE tv_show_id = ".$this->getID().";";
			return $this->db->getOne($sql);
		}
	}
?>