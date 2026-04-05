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

			$arr = $this->get_row($sql);

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

			$sql = "SELECT series_id FROM view_episodes WHERE episode_id = $episode_id LIMIT 1;";

			$var = $this->get_one($sql);
			$var = intval($var);

			return $var;

		}

		public function get_metadata() {

			$episode_id = intval($this->id);

			$sql = "SELECT * FROM view_episodes WHERE episode_id = $episode_id;";
			$arr = $this->get_row($sql);

			return $arr;

		}

		public function get_episode_titles() {

			$arr = $this->get_metadata();

			$series_title = $arr['series_title'];
			$episode_title = $arr['episode_title'];
			$episode_part = $arr['episode_part'];
			$provider_id = $arr['provider_id'];

			$arr_episode_titles = array(
				'series_title' => $series_title,
				'episode_title' => $episode_title,
				'episode_part' => $episode_part,
				'provider_id' => $provider_id,
				'display_title' => '',
			);

			if(str_contains($episode_title, '(') && str_contains($episode_title, ')')) {

				$arr = explode(')', $episode_title);
				$str = current($arr);
				$episode_title = end($arr);
				$episode_title = trim($episode_title);

				$str = substr($str, 1);

				$series_title = $str;

				if(str_contains($series_title, '|')) {

					$arr = explode('|', $series_title);

					$series_title = current($arr);
					$provider_id = end($arr);

				}

				$arr_episode_titles['series_title'] = $series_title;
				$arr_episode_titles['episode_title'] = $episode_title;

			}

			$display_title = "$series_title: ";

			if($episode_title)
				$display_title .= "$episode_title";

			if($episode_part)
				$display_title .= ", Part $episode_part";

			$arr_episode_titles['display_title'] = $display_title;

			return $arr_episode_titles;

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

		public function missing_episode_metadata() {

			return false;

			/*
			$episode_id = intval($this->id);

			// Check for missing crop detection on episodes
			$sql = "SELECT crop FROM episodes WHERE id = $episode_id;";

			$crop = $this->get_one($sql);

			if(!strlen($crop))
				return true;

			return false;
			*/

		}

		public function get_filename($container = 'mkv') {

			$episode_id = intval($this->id);

			$sql = "SELECT s.collection_id, s.nsix, sd.series_id, t.dvd_id FROM episodes e JOIN tracks t ON e.track_id = t.id JOIN dvds d ON t.dvd_id = d.id JOIN series_dvds sd ON d.id = sd.dvd_id JOIN series s ON sd.series_id = s.id WHERE e.id = $episode_id LIMIT 1;";

			$arr = $this->get_row($sql);

			if(!$arr)
				return "";

			extract($arr);

			// Get the target filename
			$str = str_pad($collection_id, 1, '0');
			$str .= ".".str_pad($series_id, 3, '0', STR_PAD_LEFT);
			$str .= ".".str_pad($dvd_id, 4, '0', STR_PAD_LEFT);
			$str .= ".".str_pad($episode_id, 5, 0, STR_PAD_LEFT);
			$str .= ".$nsix";
			$str .= ".$container";

			return $str;

		}

		function get_episode_filenames($collection_id = null, $series_id = null, $dvd_id = null, $episode_id = null) {

			$sql = "SELECT episode_filename FROM view_episodes WHERE series_active = 1 AND episode_skip = 0";
			if($collection_id)
				$sql .= " AND collection_id = $collection_id";
			if($series_id)
				$sql .= " AND series_id = $series_id";
			if($dvd_id)
				$sql .= " AND dvd_id = $dvd_id";
			if($episode_id)
				$sql .= " AND episode_id = $episode_id";
			$sql .= " ORDER BY episode_filename;";

			$arr = $this->get_col($sql);

			return $arr;

		}

	}
?>
