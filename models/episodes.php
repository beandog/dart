<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Episodes_Model extends DBTable {

		function __construct($id = null) {

			$table = "episodes";

			$this->id = parent::__construct($table, $id);

		}

		public function get_iso() {

			$episode_id = intval($this->id);

			$sql = "SELECT s.collection_id, sd.series_id, t.dvd_id, e.track_id, s.title FROM episodes e JOIN tracks t ON e.track_id = t.id JOIN dvds d ON t.dvd_id = d.id JOIN series_dvds sd ON d.id = sd.dvd_id JOIN series s ON sd.series_id = s.id WHERE e.id = $episode_id LIMIT 1;";

			$arr = $this->db->getRow($sql);

			if(!$arr)
				return "";

			extract($arr);

			// Get the series title
			$title = strtoupper($title);
			$title = preg_replace("/[^0-9A-Z \-_.]/", '', $title);
			$title = str_replace(' ', '_', $title);
			$title = substr($title, 0, 28);

			// Get the target filename
			$str = str_pad($collection_id, 1, '0');
			$str .= ".".str_pad($series_id, 3, '0', STR_PAD_LEFT);
			$str .= ".".str_pad($dvd_id, 4, '0', STR_PAD_LEFT);
			$str .= ".$title.iso";

			return $str;

		}

		public function get_series_id() {

			$episode_id = intval($this->id);

			$sql = "SELECT series_id FROM dart_series_episodes WHERE id = $episode_id LIMIT 1;";

			$var = $this->db->getOne($sql);
			$var = intval($var);

			return $var;

		}

		public function get_season() {

			$episode_id = intval($this->id);

			$sql = "SELECT series_dvds_season FROM dart_series_episodes WHERE id = $episode_id LIMIT 1;";

			$var = $this->db->getOne($sql);

			if($var === 0)
				$var = null;
			else
				$var = intval($var);

			return $var;

		}

		public function get_volume() {

			$episode_id = intval($this->id);

			$sql = "SELECT volume FROM dart_series_episodes WHERE id = $episode_id LIMIT 1;";

			$var = $this->db->getOne($sql);

			if($var === 0)
				$var = null;
			else
				$var = intval($var);

			return $var;

		}

		public function get_display_name() {

			$episode_id = intval($this->id);

			$sql = "SELECT series_title, title, part FROM dart_series_episodes WHERE id = $episode_id;";

			$arr = $this->db->getRow($sql);

			if(empty($arr[2]))
				array_pop($arr);

			$arr = array_unique($arr);

			$str = implode(": ", $arr);

			return $str;

		}

		public function get_long_title() {

			$episode_id = intval($this->id);

			$sql = "SELECT * FROM dart_series_episodes WHERE id = $episode_id;";
			$arr = $this->db->getRow($sql);

			if(!$arr)
				return "";

			extract($arr);

			$episode_year = $production_year;
			if($season)
				$episode_year += $season;

			// Episode part
			if($part)
				$title .= ", Part $part";

			// Add season, episode number to indexed series
			if($indexed == 't') {
				$episode_number = $this->get_number();
				$episode_number_ix = str_pad($episode_number, 2, 0, STR_PAD_LEFT);
				$title = "$season.$episode_number_ix $title";
			}

			return $title;

		}

		public function get_number() {

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
			$sql = "SELECT DISTINCT e1.episode_id ".
				"FROM view_episodes e1 INNER JOIN view_episodes e2 ON ".
				// Same series
				"e1.series_id = e2.series_id ".
				// Same season
				"AND e1.season = e2.season ".
				// Same volume
				"AND e1.series_dvds_volume = e2.series_dvds_volume ".
				// Not the same DVD
// 				"AND e1.dvd_id != e2.dvd_id ".
				// Previous DVD index or same index and other side
				"AND ( (e1.series_dvds_ix < e2.series_dvds_ix ) OR ((e1.series_dvds_ix = e2.series_dvds_ix) AND (e1.series_dvds_side < e2.series_dvds_side))) ".
				"WHERE e2.episode_id = ".$this->db->quote($this->id).
				" ORDER BY e1.episode_id;";

			$episodes = $this->db->getCol($sql);

			$i = count($episodes);

			// Earlier seasons
			/** For the life of me, on a re-edit, where I fixed things so
			that the volume and season are always the same (set to zero, to avoid
			the extra conditional checks for null values), I cannot now remember
			why in the world this query is in here.
			*/
// 			$sql = "SELECT DISTINCT e1.episode_id
// 				FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id
// 				AND e1.dvd_id != e2.dvd_id
// 				AND e1.season < e2.season
// 				WHERE e2.episode_id = ".$this->db->quote($this->id)."
// 				ORDER BY e1.episode_id;";
//
// 			$episodes = array_unique(array_merge($episodes, $this->db->getCol($sql)));
//
// 			$i = count($episodes);
//
// 			echo ("count: $i\n");

			// Earlier volumes, same SEASON
			// Fetching this because a SEASON can go across many VOLUMEs
			$sql = "SELECT DISTINCT e1.episode_id
				FROM view_episodes e1 INNER JOIN view_episodes e2 ON e1.series_id = e2.series_id
				AND e1.season = e2.season
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
				// Same series
				"AND e1.series_id = e2.series_id ".
				// Same season
				"AND e1.season = e2.season ".
				// Same volume
				"AND e2.series_dvds_volume = e2.series_dvds_volume ".
				// Same DVD
				"AND e2.dvd_id = e2.dvd_id ".
				// Same side
				"AND e1.series_dvds_side = e2.series_dvds_side ".
				// Lesser episode IF they are not the same (zero)
 				"AND ((e1.episode_ix != e2.episode_ix AND e1.episode_ix < e2.episode_ix)".
 					"OR (e1.episode_ix = e2.episode_ix AND e1.episode_id < e2.episode_id)) ".
 				// Not the same episode
				"AND e1.episode_id != e2.episode_id ".
				"WHERE e2.episode_id = ".$this->db->quote($this->id).
				";";
 			$count2 = $this->db->getOne($sql);

//  			echo "$count2\n";

//  			echo "# PREVIOUS EPISODES on SAME DISC: $count2\n";

 			// Add one because we start counting at 1, not 0
 			$count = $count1 + $count2 + 1;

			return $count;

		}

		public function get_metadata() {

			$episode_id = intval($this->id);

			$sql = "SELECT * FROM series_dvds_episodes WHERE id = $episode_id;";
			$arr = $this->db->getRow($sql);

			return $arr;

		}

		public function get_chapter_lengths($minimum_length = 0) {

			$episode_id = intval($this->id);
			$minimum_length = abs(floatval($minimum_length));

			// Get the track id
			$sql = "SELECT track_id FROM episodes WHERE id = $episode_id;";
			$track_id = $this->get_one($sql);

			if(is_null($track_id))
				return array();

			// Get starting and ending chapter
			$sql = "SELECT starting_chapter, ending_chapter FROM episodes WHERE id = $episode_id;";
			$arr = $this->get_row($sql);
			extract($arr);

			$sql = "SELECT length FROM chapters WHERE track_id = $track_id";
			if(!is_null($starting_chapter))
				$sql .= " AND ix >= $starting_chapter";
			if(!is_null($ending_chapter))
				$sql .= " AND ix <= $ending_chapter";
			if($minimum_length)
				$sql .= " AND length >= $minimum_length";
			$sql .= " ORDER BY ix;";

			$arr = $this->get_col($sql);

			return $arr;

		}

	}
?>
