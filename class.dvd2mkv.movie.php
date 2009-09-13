<?

	class DVDMovie {
	
		private $id;
		private $title;
		private $sorting_title;
		private $blu_ray;
		private $year_release;
		private $production_studio;
		private $rating;
		
		function __construct($id = null) {
			if(!is_null($id)) {
				$this->setID($id);
				$this->getTitle();
				$this->getSortingTitle();
				$this->isBluRay();
				$this->getYearRelease();
				$this->getProductionStudio();
				$this->getRating();
			} else {
				$this->newMovie();
			}
				
			$this->arr_ratings = array('NR', 'G', 'PG', 'PG-13');
			
			$this->arr_production_studios = array(
				'Warner Bros.',
				'Walt Disney Pictures',
				'Walt Disney Productions',
				'Walt Disney',
				'Universal Pictures',
				'Paramount Pictures',
				'MGM',
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
		
		private function newMovie() {
			global $db;
			
			$sql = "SELECT nextval('movies_id_seq');";
			$id = $db->getOne($sql);
			
			$arr_insert = array(
				'id' => $id
			);
			
			$db->autoExecute('movies', $arr_insert);
			
			$this->setId($id);
		}
		
		public function setTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newMovie();
			
			$arr_update = array(
				'title_long' => $str
			);
			
			$db->autoExecute('movies', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->title = $str;
		}
		
		public function setSortingTitle($str) {
		
			$str = trim($str);
			if(empty($str) || !is_string($str))
				return false;
				
			global $db;
		
			if(!$this->id)
				$this->newMovie();
			
			$arr_update = array(
				'title' => $str
			);
			
			$db->autoExecute('movies', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->sorting_title = $str;
		}
		
		function getTitle() {
			if(is_null($this->title)) {
				global $db;
				$sql = "SELECT title_long FROM movies WHERE id = ".$this->getID().";";
				$this->title = $db->getOne($sql);
				if(is_null($this->title))
					$this->title = "";
			}
			
			return $this->title;
			
		}
		
		function getSortingTitle() {
			if(is_null($this->sorting_title)) {
				global $db;
				$sql = "SELECT title FROM movies WHERE id = ".$this->getID().";";
				$str = $db->getOne($sql);
				$this->sorting_title = $db->getOne($sql);
				if(is_null($this->sorting_title))
					$this->sorting_title = "";
			}
			
			return $this->sorting_title;
		}
		
		function setYearRelease($int) {
		
			global $db;
		
			if(is_null($int))
				$int = null;
			else
				$int = abs(intval($int));
			
			$arr_update = array(
				'year_release' => $int
			);
			
			$db->autoExecute('movies', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->year_release = $int;
		}
		
		function getYearRelease() {
			if(is_null($this->year_release)) {
				global $db;
				$sql = "SELECT year_release FROM movies WHERE id = ".$this->getID().";";
				$this->year_release = $db->getOne($sql);
				if(is_null($this->year_release))
					$this->year_release = "";
			}
			
			return $this->year_release;
		}
		
		function setBluRay($bool) {
		
			global $db;
		
			if($bool)
				$value = true;
			else
				$value = false;
			
			$arr_update = array(
				'blu_ray' => $value
			);
			
			$db->autoExecute('movies', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->blu_ray = $int;
			
		}
		
		function isBluRay() {
		
			if(is_null($this->blu_ray)) {
				global $db;
				$sql = "SELECT blu_ray FROM movies WHERE id = ".$this->getID().";";
				$value = $db->getOne($sql);
				if($value === 't')
					$this->blu_ray = true;
				else
					$this->blu_ray = false;
			}
			
			return $this->blu_ray;
		
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
		
		public function setRating($str) {
		
			$str = trim($str);
			if(!is_string($str))
				return false;
			
			global $db;
		
			if(!$this->id)
				$this->newMovie();
			
			$arr_update = array(
				'rating' => $str
			);
			
			$db->autoExecute('movies', $arr_update, DB_AUTOQUERY_UPDATE, "id = ".$this->getID());
			
			$this->rating = $str;
		}
		
		function getRating() {
			if(is_null($this->rating)) {
				global $db;
				$sql = "SELECT rating FROM movies WHERE id = ".$this->getID().";";
				$this->rating = $db->getOne($sql);
				if(is_null($this->rating))
					$this->rating = "";
			}
			
			return $this->rating;
			
		}
		
		function getRatings() {
			return $this->arr_ratings;
		}
		
		function getNumDiscs() {
			global $db;
			
			$sql = "SELECT COUNT(1) FROM discs WHERE movie = ".$this->getID().";";
			return $db->getOne($sql);
		}
		
	}
?>