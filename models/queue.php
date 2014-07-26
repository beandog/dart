<?php

	class Queue_Model extends DBTable {

		function __construct($id = null) {

			$this->table = "queue";

			$this->id = parent::__construct($this->table, $id);

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

		public function get_episodes($hostname, $skip = 0, $max = 0) {

			$sql = '';

			if($skip > 0)
				$sql = " OFFSET $skip";

			if($max > 0)
				$sql .= " LIMIT $max";

			// FIXME This order comes out kind of random.  I realize
			// its simply ordering by insert date, generally, but still .. it's odd.
			$sql = "SELECT episode_id FROM ".$this->table." WHERE hostname = ".$this->db->quote($hostname)." AND status = 0 ORDER BY priority, insert_date $sql;";

 			$arr = $this->db->getCol($sql);

 			return $arr;

		}

		public function get_episode_status($episode_id) {

			$sql = "SELECT status FROM queue WHERE episode_id = ".$this->db->quote($episode_id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

		public function set_episode_status($episode_id, $status) {

			$episode_id = abs(intval($episode_id));
			$status = abs(intval($status));

			$sql = "UPDATE queue SET status = $status WHERE episode_id = $episode_id;";

			$this->db->query($sql);

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

			$sql = "DELETE FROM queue WHERE status = 0 AND hostname = ".$this->db->quote($hostname).";";

			$this->db->query($sql);

		}

	}
?>
