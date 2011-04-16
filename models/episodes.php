<?

	require_once 'mdb2/charlie.dvds.php';

	class Episodes_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "episodes";
			
			$this->id = parent::__construct($table, $id);
			
		}
		
		public function get_series_id() {
		
			$sql = "SELECT s.id FROM series s INNER JOIN series_dvds sd ON sd.series_id = s.id INNER JOIN dvds d ON d.id = sd.dvd_id INNER JOIN tracks t ON t.dvd_id = d.id INNER JOIN episodes e ON e.track_id = t.id WHERE e.id = ".$this->db->quote($this->id)." LIMIT 1;";
			
			$var = $this->db->getOne($sql);
			
			return $var;
		
		}
		
		public function get_season() {
		
			$sql = "SELECT season FROM view_episodes WHERE episode_id = ".$this->db->quote($this->id)." LIMIT 1;";
			
			return $this->db->getOne($sql);
		
		}
		
		public function get_display_name() {
		
			$sql = "SELECT series_title, episode_title, episode_part FROM view_episodes WHERE episode_id = ".$this->db->quote($this->id).";";
			
			$arr = $this->db->getRow($sql);
			
			if(empty($arr[2]))
				array_pop($arr);
			
			$str = implode(": ", $arr);
			
			return $str;
		
		}
		
		
		public function get_number() {
		
			// Find the # of episodes on PREVIOUS DISCS for this season and/or volume
// 			$sql = "SELECT COUNT(1) ".
// 				"FROM view_episodes e1 ".
// 				"INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id ".
// 				"AND e1.series_dvds_volume = e2.series_dvds_volume ".
// 				"AND e1.dvd_id != e2.dvd_id ".
// 				"AND (e1.season = e2.season OR (e1.series_dvds_volume <= e2.series_dvds_volume AND e1.season IS NULL AND e2.season IS NULL) OR (e1.series_dvds_volume <= e2.series_dvds_volume AND e1.season = e2.season) ) ".
// 				"WHERE e2.episode_id = ".$this->db->quote($this->id).
// 				" AND ( e1.season <= e2.season  OR e1.season IS NULL AND e2.season IS NULL ) ".
// 				" AND ((e1.series_dvds_ix < e2.series_dvds_ix ) OR (e1.series_dvds_ix = e2.series_dvds_ix AND e1.series_dvds_side < e2.series_dvds_side));";
// 			
// 			$count1 = $this->db->getOne($sql);

			/**
			 * I'm taking a different approach here.  Instead of trying to write one
			 * massive query that checks all conditions, this one looks at three
			 * separate conditions to get # episodes on PREVIOUS DISCS.  Then it
			 * just adds them up and gets the count from the unique set of values.
			 *
			 * This is simpler because the queries can have overlapping results.
			 *
			 */
			
			// Same season, same volume, other discs
			$sql = "SELECT DISTINCT e1.episode_id 
				FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id 
				AND e1.dvd_id != e2.dvd_id 
				AND (
					( e1.season = e2.season OR ( e1.season IS NULL AND e2.season IS NULL ) ) 
					AND e1.series_dvds_ix < e2.series_dvds_ix 
					AND ( 
						e1.series_dvds_volume = e2.series_dvds_volume OR ( e1.series_dvds_volume IS NULL AND e2.series_dvds_volume IS NULL )
					)
				)
				WHERE e2.episode_id = ".$this->db->quote($this->id)."
				ORDER BY e1.episode_id;";
				
			$episodes = $this->db->getCol($sql);
			
			// Earlier seasons
			$sql = "SELECT DISTINCT e1.episode_id 
				FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id 
				AND e1.dvd_id != e2.dvd_id 
				AND e1.season < e2.season 
				WHERE e2.episode_id = ".$this->db->quote($this->id)."
				ORDER BY e1.episode_id;";
			
			$episodes = array_merge($episodes, $this->db->getCol($sql));
			
			// Earlier volumes
			$sql = "SELECT DISTINCT e1.episode_id 
				FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id 
				AND e1.dvd_id != e2.dvd_id 
				AND e1.series_dvds_volume < e2.series_dvds_volume 
				WHERE e2.episode_id = ".$this->db->quote($this->id)."
				ORDER BY e1.episode_id;";
			
			$episodes = array_merge($episodes, $this->db->getCol($sql));
			
			$count1 = count(array_unique($episodes));
			
//  			echo "# episodes on PREVIOUS DISCS: $count1\n";
			
//   			echo "$sql\n";
	
			// Find the # of episodes ON THE CURRENT DISC before this episode
			// TESTING Added a check for complete-series DVDs, where the query
			// looks at the volume as well, not just the season.
			$sql = "SELECT COUNT(1) FROM view_episodes e1 ".
				"INNER JOIN view_episodes e2 ON e1.dvd_id = e2.dvd_id ".
				"AND e1.series_id = e2.series_id ".
				"AND ((e1.episode_ix < e2.episode_ix) OR (e1.episode_ix = e2.episode_ix AND e1.episode_ix < e2.episode_ix)) ".
				"AND e1.series_dvds_side = e2.series_dvds_side ".
 				"AND (e1.season = e2.season OR (e1.series_dvds_volume = e2.series_dvds_volume AND e1.season IS NULL AND e2.season IS NULL) OR (e1.series_dvds_volume = e2.series_dvds_volume AND e1.season = e2.season) ) ".
				"AND e1.episode_id != e2.episode_id ".
				"WHERE e2.episode_id = ".$this->db->quote($this->id).
				";";
 			$count2 = $this->db->getOne($sql);
 			
//  			echo "# PREVIOUS EPISODES on SAME DISC: $count2\n";
 			
 			// Add one because we start counting at 1, not 0
 			$count = $count1 + $count2 + 1;
			
			return $count;
		
		}
		
	}
?>
