<?php

	class Series_Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = 'series_dvds';

			$this->id = parent::__construct($table, $id);

		}

		function get_dvdread_id_iso_filename($dvdread_id) {

			$dvdread_id = trim(strval($dvdread_id));
			if($dvdread_id === '') {
				trigger_error("dvdread id is empty", E_USER_ERROR);
				exit(1);
			}

			$dvdread_id = $this->quote($dvdread_id);

			$sql = "SELECT iso_filename FROM view_series_dvds WHERE dvdread_id = $dvdread_id;";

			$var = $this->get_one($sql);

			return $var;

		}

	}

?>
