<?php

	require_once 'pdo.config.php';

	class DBTable {

		protected $id;
		protected $table;

		public function __construct($table, $id = null) {

			$table = trim(strval($table));

			if($table === '') {
				trigger_error("Table name is empty", E_USER_ERROR);
				exit(1);
			}

			$this->table = $table;

			return $this->id = $id;

		}

		public function __get($column) {

			global $pg;

			$sql = "SELECT $column FROM {$this->table} WHERE id = {$this->id} LIMIT 1;";

			return $this->get_one($sql);

		}

		public function __set($key, $value) {

			global $pg;

			$key = strval($key);

			$value = $pg->quote($value);

			$sql = "UPDATE {$this->table} SET $key = $value WHERE id = {$this->id};";

			$retval = $pg->exec($sql);

			if($retval === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			return true;

		}

		public function __call($str, $args) {

			$arr = explode('_', $str);
			$function_call = current($arr);
			array_shift($arr);
			$function_value = implode('_', $arr);

			// Check to see if they are setting a column
			if($function_call === 'set' && strlen($function_value) && count($args)) {
				$value = current($args);
				$this->__set($function_value, $value);
			}

			// Otherwise check if they are fetching a column
			elseif($function_call === 'get' && strlen($function_value)) {
				return $this->__get($function_value);
			}

			else
				return null;

		}

		public function __toString() {

			return strval($this->id);

		}

		public function quote($str) {

			global $pg;

			return $pg->quote($str);

		}

		public function create_new() {

			global $pg;

			$sql = "INSERT INTO {$this->table} DEFAULT VALUES RETURNING id;";

			$pdo = $pg->query($sql);

			$var = $pdo->fetchColumn();

			if($var === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			$this->id = $var;

			return $var;

		}

		public function delete() {

			global $pg;

			$sql = "DELETE FROM {$this->table} WHERE id = {$this->id};";

			$retval = $pg->exec($sql);

			if($retval === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			return true;

		}

		public function delete_from_table_where($table, $column, $var) {

			global $pg;

			$table = trim(strval($table));
			$column = trim(strval($column));
			$var = $pg->quote($var);

			if($table === '' || $column === '') {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			$sql = "DELETE FROM $table WHERE $column = $var;";
			var_dump($sql);

			$retval = $pg->exec($sql);

			if($retval === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			return true;

		}

		public function load($id) {

			$this->id = $id;

		}

		public function get_one($sql) {

			global $pg;

			$pdo = $pg->query($sql);

			$var = $pdo->fetchColumn();

			if($var === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			return $var;

		}

		public function get_col($sql) {

			global $pg;

			$pdo = $pg->query($sql);

			$arr = $pdo->fetchAll(PDO::FETCH_COLUMN, 0);

			if($arr === false) {
				trigger_error("Query failed: $sql", E_USER_ERROR);
				exit(1);
			}

			return $arr;

		}

		public function get_all($sql) {

			global $pg;

			$pdo = $pg->query($sql, PDO::FETCH_ASSOC);

			$arr = $pdo->fetchAll();

			if($arr === false) {
				trigger_error("Query failed:\n\t$sql\n", E_USER_ERROR);
				exit(1);
			}

			return $arr;

		}

		public function get_row($sql) {

			global $pg;

			$pdo = $pg->query($sql, PDO::FETCH_ASSOC);

			$arr = $pdo->fetch();

			if($arr === false) {
				trigger_error("Query failed:\n\t$sql\n", E_USER_ERROR);
				exit(1);
			}

			return $arr;

		}

	}

?>
