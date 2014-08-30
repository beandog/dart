<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Queue_Model extends DBTable {

		private $skip;
		private $max;
		private $random;
		private $episode_id;
		private $hostname;

		function __construct($id = null) {

			$this->table = "queue";

			$this->id = parent::__construct($this->table, $id);

			$this->skip = 0;
			$this->max = 0;
			$this->random = false;
			$this->episode_id = 0;
			$this->hostname = '';

		}

		public function add_episode($id, $hostname) {

			$this->remove_episode($id);

			$this->create_new();
			$this->episode_id = $id;
			$this->hostname = $hostname;

		}

		public function remove_episode($id) {

			$sql = "DELETE FROM ".$this->table." WHERE episode_id = ".$this->db->quote($id).";";

			return $this->db->query($sql);

		}

		public function skip_episodes($num_episodes) {

			$this->skip = abs(intval($num_episodes));

		}

		public function set_max_episodes($num_episodes) {

			$this->max = abs(intval($num_episodes));

		}

		public function set_episode_id($episode_id) {

			$this->episode_id = abs(intval($episode_id));
			$this->skip = 0;
			$this->max = 1;

		}

		public function set_random($random) {

			$this->random = (bool)$random;

		}

		public function set_hostname($hostname) {

			$this->hostname = trim($hostname);

		}

		public function get_episodes() {

			$sql = '';
			$where = array();
			$order_by = '';

			if($this->skip)
				$sql = " OFFSET $skip";

			if($this->max > 0)
				$sql .= " LIMIT $max";

			if($this->random)
				$order_by = "RANDOM(), ";

			if($this->hostname)
				$where[] = "hostname = ".$this->db->quote($hostname);

			if($this->episode_id)
				$where[] = "episode_id = ".abs(intval($this->episode_id));

			if(count($where))
				$str_where = "WHERE".implode(" AND ", $where);

			$sql = "SELECT episode_id FROM ".$this->table." $str_where ORDER BY priority, $order_by id $sql;";

 			$arr = $this->db->getCol($sql);

 			return $arr;

		}

		public function get_episode_status($episode_id) {

			$sql = "SELECT x264, xml, mkv FROM queue WHERE episode_id = ".$this->db->quote($episode_id).";";

			$arr = $this->db->getRow($sql);

			return $arr;

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

		public function get_dvds($hostname) {

			$sql = "SELECT DISTINCT dvd_id FROM view_episodes e JOIN queue q ON q.episode_id = e.episode_id WHERE q.hostname = ".$this->db->quote($hostname).";";

			$arr = $this->db->getCol($sql);

 			return $arr;

		}

		public function prioritize() {

			$arr_update = array(
				'priority' => 0
			);

			$this->db->autoExecute('queue', $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->id);

		}

		public function reset($hostname) {

			$sql = "DELETE FROM queue WHERE hostname = ".$this->db->quote($hostname)." AND x264 = 0 AND xml = 0 AND mkv = 0;";

			$this->db->query($sql);

		}

	}
?>
