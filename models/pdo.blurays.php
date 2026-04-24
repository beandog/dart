<?php

	class Blurays_Model extends DBTable {

		function __construct($id = null) {

			$table = 'blurays';

			$this->id = parent::__construct($table, $id);

		}

		function load_dvd_id($id) {

			$id = abs(intval($id));

			if(!$id)
				return false;

			$sql = "SELECT id FROM blurays WHERE dvd_id = $id;";
			$var = $this->get_one($sql);

			if(!$var)
				return false;

			$this->id = intval($var);

			return $this->id;

		}

		function load_dvdread_id($dvdread_id) {

			$dvdread_id = trim(strval($dvdread_id));
			if(!$dvdread_id)
				return false;

			$dvdread_id = $this->quote($dvread_id);

			$sql = "SELECT b.id FROM blurays b JOIN dvds d ON d.id = b.dvd_id WHERE d.dvdread_id = $dvdread_id;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_disc_title($disc_title) {

			$disc_title = trim(strval($disc_title));
			if(!$disc_title)
				return false;

			$sql = "SELECT id FROM blurays WHERE disc_title = $disc_title;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_volname($volname) {

			$volname = trim(strval($volname));

			if(!$volname)
				return false;

			$sql = "SELECT id FROM blurays WHERE volname = $volname;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		function load_legacy_md5($legacy_md5) {

			$legacy_md5 = trim(strval($legacy_md5));
			if(!$legacy_md5)
				return false;

			$sql = "SELECT id FROM blurays WHERE legacy_md5 = $legacy_md5;";
			$this->id = intval($this->get_one($sql));

			return $this->id;

		}

		public function get_episodes($include_skipped = true) {

			if($include_skipped)
				$str_skip = "0, 1";
			else
				$str_skip = "0";

			$sql = "SELECT episode_id FROM view_episodes WHERE dvd_id = {$this->dvd_id} AND episode_skip IN ($str_skip) ORDER BY episode_season, episode_number, episode_id;";

			$arr = $this->get_col($sql);

			return $arr;

		}

		// Holder function from DVDs model
		function has_max_tracks() {

			return false;

		}

	}

?>
