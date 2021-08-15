<?php
	class DBTable {

		protected $id;
		protected $db;
		protected $table;
		protected $pg;

		public function __construct($table, $id = null) {

			$this->table = $table;

			global $db4;
			$this->db = pg_connect($db4);

			if($this->db === false) {
				trigger_error("Cannot connect to DB using credentials: $db4", E_USER_ERROR);
				return null;
			}

			return $this->id = $id;

		}

		public function __get($var) {

			$sql = "SELECT $var FROM ".$this->table." WHERE id = ".$this->id.";";

			return $this->get_one($sql);

		}

		public function __set($key, $value) {

			$arr_update = array(
				$key => $value
			);

			$sql = "UPDATE ".$this->table." SET $key = '".pg_escape_string($value)."';";

			return $this->query($sql);

		}

		public function __call($str, $args) {

			$arr = explode("_", $str);
			$function_call = current($arr);
			array_shift($arr);
			$function_value = implode("_", $arr);

			// Check to see if they are setting a column
			if($function_call === "set" && strlen($function_value) && count($args)) {
				$value = current($args);
				$this->__set($function_value, $value);
			}

			// Otherwise check if they are fetching a column
			elseif($function_call === "get" && strlen($function_value)) {

				return $this->__get($function_value);

			}

			else
				return null;

		}

		public function __toString() {

			return (string)$this->id;

		}

		public function create_new() {

			$sql = "INSERT INTO ".$this->table." DEFAULT VALUES RETURNING id;";

			return $this->get_one($sql);

		}

		public function delete() {

			$sql = "DELETE FROM ".$this->table." WHERE id = ".$this->id.";";

			$rs = pg_query($sql);

			if($rs === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				return false;
			}

			return true;

		}

		public function load($id) {

			$this->id = $id;

		}

		public function get_one($sql) {

			$rs = pg_query($sql);
			if($rs === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				return array();
			}

			$arr = pg_fetch_array($rs, 0, PGSQL_ASSOC);
			$var = current($arr);

			return $var;

		}

		public function get_col($sql) {

			$rs = pg_query($sql);
			if($rs === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				return array();
			}

			$arr = array();

			while($row = pg_fetch_array($rs, 0, PGSQL_NUM))
				$arr[] = current($row);

			return $arr;

		}

		public function get_all($sql) {

			$rs = pg_query($sql);
			if($rs === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				return array();
			}

			$arr = array();

			while($row = pg_fetch_array($rs, 0, PGSQL_ASSOC))
				$arr[] = $row;

			return $arr;

		}

		public function get_row($sql) {

			$rs = pg_query($sql);
			if($rs === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				return array();
			}

			$arr = pg_fetch_array($rs, 0, PGSQL_ASSOC);

			return $arr;

		}

	}
?>
