<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Encodes_Model extends DBTable {

		function __construct($id = null) {

			$table = "encodes";

			$this->id = parent::__construct($table, $id);

		}

		function set_encode_finish($seconds) {

			$id = intval($this->id);
			$seconds = intval($seconds);

			if(!$seconds)
				$seconds = time();

			$sql = "UPDATE encodes SET encode_finish = (encode_begin + interval '$seconds seconds') WHERE id = $id;";
			$this->query($sql);

		}

		function find_episode_id($episode_id) {

			$episode_id = intval($episode_id);

			$sql = "SELECT id FROM encodes WHERE episode_id = $episode_id AND encode_finish IS NULL ORDER BY ID DESC LIMIT 1;";
			$encode_id = $this->getOne($sql);

			return $encode_id;

		}

	}
?>
