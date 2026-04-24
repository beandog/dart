<?php

	class Series_Dvds_Model extends DBTable {

		function __construct($id = null) {

			$table = 'series_dvds';

			$this->id = parent::__construct($table, $id);

		}

		function load_dvdread_id($dvdread_id) {

			$dvdread_id = trim(strval($dvdread_id));
			if($dvdread_id === '') {
				trigger_error("dvdread id is empty", E_USER_ERROR);
				exit(1);
			}

			$dvdread_id = $this->quote($dvdread_id);

			$sql = "SELECT id FROM view_series_dvds WHERE dvdread_id = $dvdread_id;";

			$this->id = $this->get_one($sql);

			return $this->id;

		}

		function get_dvdread_id_iso_filename($dvdread_id) {

			$this->load_dvdread_id($dvdread_id);

			$sql = "SELECT iso_filename FROM view_series_dvds WHERE id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

	}

?>
