<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Queue_Model extends DBTable {

		private $random;
		private $episode_id;
		private $track_id;
		private $dvd_id;
		private $series_id;
		private $hostname;

		function __construct($id = null) {

			$this->table = "queue";

			$this->id = parent::__construct($this->table, $id);

			$this->random = false;
			$this->episode_id = 0;
			$this->hostname = '';

		}

		public function add_episode($id) {

			$this->remove_episode($id);

			$this->create_new();
			$this->__set('episode_id', $id);
			if($this->hostname)
				$this->__set('hostname', $this->hostname);

		}

		public function remove_episode($id) {

			$sql = "DELETE FROM ".$this->table." WHERE episode_id = ".$this->db->quote($id).";";

			return $this->db->query($sql);

		}

		public function set_episode_id($episode_id) {

			$this->episode_id = abs(intval($episode_id));

		}

		public function set_track_id($track_id) {

			$this->track_id = abs(intval($track_id));

		}

		public function set_dvd_id($dvd_id) {

			$this->dvd_id = abs(intval($dvd_id));

		}

		public function set_series_id($series_id) {

			$this->series_id = abs(intval($series_id));

		}

		public function set_random($random = true) {

			$this->random = boolval($random);

		}

		public function set_hostname($hostname) {

			$this->hostname = trim($hostname);

		}

		public function get_episodes($skip = 0, $max = 0, $in_progress = true) {

			$skip = abs(intval($skip));
			$max = abs(intval($max));

			$sql = '';
			$where = array();
			$order_by = '';

			if($skip)
				$sql = " OFFSET $skip";

			if($max > 0)
				$sql .= " LIMIT $max";

			if($this->random)
				$order_by = "RANDOM(), ";

			if($this->hostname)
				$where[] = "hostname = ".$this->db->quote($this->hostname);

			if($this->episode_id)
				$where[] = "episode_id = ".abs(intval($this->episode_id));

			if($in_progress == false)
				$where[] = "x264 = 0 AND xml = 0 AND mkv = 0";

			if(count($where))
				$str_where = "WHERE ".implode(" AND ", $where);

			if($this->track_id) {
				$track_id = intval($this->track_id);
				$str_where .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE track_id = $track_id) ";
			}

			if($this->dvd_id) {
				$dvd_id = intval($this->dvd_id);
				$str_where .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE dvd_id  = $dvd_id) ";
			}

			if($this->series_id) {
				$series_id = intval($this->series_id);
				$str_where .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE series_id  = $series_id) ";
			}

			$sql = "SELECT episode_id FROM ".$this->table." $str_where ORDER BY $order_by id $sql;";

 			$arr = $this->db->getCol($sql);

 			return $arr;

		}

		public function episode_in_queue($episode_id) {

			$episode_id = abs(intval($episode_id));

			if(!$episode_id)
				return false;

			$sql = "SELECT 1 FROM queue WHERE episode_id = $episode_id;";

			$var = $this->db->getOne($sql);

			if(is_null($var))
				return false;
			else
				return true;

		}

		/**
		 * Get the queue status for an episode at a specific stage.
		 */
		public function get_episode_status($episode_id, $stage) {

			if(!in_array($stage, array('x264', 'xml', 'mkv')))
				return null;

			$episode_id = abs(intval($episode_id));

			$sql = "SELECT $stage FROM queue WHERE episode_id = $episode_id;";

			$var = $this->db->getOne($sql);

			if(is_null($var))
				return null;
			else
				return intval($var);

		}

		public function set_episode_status($episode_id, $stage, $status) {

			$episode_id = abs(intval($episode_id));
			$stage = trim($stage);
			$status = abs(intval($status));

			$arr_stages = array('x264', 'xml', 'mkv');

			if(!in_array($stage, $arr_stages))
				return false;

			$sql = "UPDATE queue SET $stage = $status WHERE episode_id = $episode_id;";

			$this->db->query($sql);

			return true;

		}

		public function get_dvds() {

			$where = '';
			if($this->hostname)
				$where = "WHERE q.hostname = ".$this->db->quote($hostname);

			$sql = "SELECT DISTINCT dvd_id FROM view_episodes e JOIN queue q ON q.episode_id = e.episode_id $where;";

			$arr = $this->db->getCol($sql);

 			return $arr;

		}

		public function remove() {

			$sql = '';

			if($this->hostname)
				$sql .= " AND hostname = ".$this->db->quote($this->hostname);
			if($this->episode_id)
				$sql .= " AND episode_id = ".$this->db->quote($this->episode_id);
			if($this->track_id) {
				$track_id = intval($this->track_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE track_id = $track_id) ";
			}

			if($this->dvd_id) {
				$dvd_id = intval($this->dvd_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE dvd_id  = $dvd_id) ";
			}

			if($this->series_id) {
				$series_id = intval($this->series_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE series_id  = $series_id) ";
			}

			$sql = "DELETE FROM queue WHERE x264 = 0 AND xml = 0 AND mkv = 0 $sql;";

			$this->db->query($sql);

		}

		public function reset() {

			$sql = '';

			if($this->hostname)
				$sql .= " AND hostname = ".$this->db->quote($this->hostname);
			if($this->episode_id)
				$sql .= " AND episode_id = ".$this->db->quote($this->episode_id);
			if($this->track_id) {
				$track_id = intval($this->track_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE track_id = $track_id) ";
			}

			if($this->dvd_id) {
				$dvd_id = intval($this->dvd_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE dvd_id  = $dvd_id) ";
			}

			if($this->series_id) {
				$series_id = intval($this->series_id);
				$sql .= " AND episode_id IN (SELECT episode_id FROM view_episodes WHERE series_id  = $series_id) ";
			}

			$sql = "UPDATE queue SET x264 = 0, xml = 0, mkv = 0 WHERE (x264 > 0 OR xml > 0 OR mkv > 0) $sql;";

			$this->db->query($sql);

		}

	}
?>
