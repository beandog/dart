<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Series_Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = "series_dvds";

			$this->id = parent::__construct($table, $id);

		}

	}
?>
