<?php
	class DBTable {

		protected $id;
		protected $db;
		protected $table;
		protected $pg;

		public function __construct($table, $id = null) {

			$this->db = MDB2::singleton();

			$this->table = $table;

			// FIXME needs a constructor
			global $pdo_dsn;
			$this->new_pdo($pdo_dsn);

			return $this->id = $id;

		}

		public function __get($var) {

			$sql = "SELECT ".$this->db->escape($var)." FROM ".$this->table." WHERE id = ".$this->db->quote($this->id).";";

			return $this->db->getOne($sql);

		}

		public function __set($key, $value) {

			$arr_update = array(
				$key => $value
			);

			return $this->db->autoExecute($this->table, $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->db->quote($this->id));

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

		public function new_pdo($pdo_dsn) {

			// *Starting* to migrate from PEAR DBA to native PHP PDO.
			try {
				$this->pg = new PDO($pdo_dsn);
				$this->pg->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				return true;
			} catch (PDOException $e) {
				echo "* PHP PDO connection FAILED: ".$e->getMessage()."\n";
				return false;
			}

		}

		public function create_new() {

			$this->db->query("INSERT INTO ".$this->table." DEFAULT VALUES;");

			return $this->id = $this->db->lastInsertID();

		}

		public function delete() {

			$sql = "DELETE FROM ".$this->table." WHERE id = ".$this->db->quote($this->id).";";

			return $this->db->query($sql);

		}

		public function load($id) {

			$this->id = $id;

		}

		// Replace DBA with native PHP PDO functions that return the correct
		// data types from PostgreSQL instead of casting them all to strings :D

		public function get_one($sql) {

			$rs = $this->pg->pg_query($sql);
			$var = $rs->fetchColumn();

			return $var;

		}

		public function get_col($sql) {

			$rs = $pg->query($sql);
			$arr = $rs->fetchAll(PDO::FETCH_COLUMN);

			return $arr;

		}

		public function get_all($sql) {

			$rs = $pg->query($sql);
			$arr = $rs->fetchAll();

			return $arr;

		}

		public function getRow($sql) {

			$arr = current($this->get_all($sql));

			return $arr;

		}

	}
?>
