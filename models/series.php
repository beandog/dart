<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Series_Model extends DBTable {

		function __construct($id = null) {

			$table = "series";

			$this->id = parent::__construct($table, $id);

		}

		function get_collection_title() {

			$sql = "SELECT c.title FROM collections c INNER JOIN series s ON s.collection_id = c.id WHERE s.id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_preset_name() {

			$sql = "SELECT presets.name FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_preset_format() {

			$sql = "SELECT presets.format FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_vcodec() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT vcodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_acodec() {

			$sql = "SELECT presets.acodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_crf() {

			$sql = "SELECT COALESCE(s.crf, p.crf) AS preset_crf FROM presets p INNER JOIN series_presets sp ON sp.preset_id = p.id JOIN series s ON s.id = sp.series_id AND sp.series_id = ".$this->id.";";
			$str = $this->get_one($sql);

			return $str;

		}

		function get_qmin() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT qmin FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_qmax() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT qmax FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_denoise() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT denoise FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_sharpen() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT sharpen FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_sharpen_tune() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT sharpen_tune FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_fps() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT fps FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_x264_tune() {

			$arr = array();

			$sql = "SELECT presets.x264_tune FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";
			$str = $this->get_one($sql);

			return $str;

		}

		function get_preset_fps() {

			$sql = "SELECT presets.fps FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

	}

?>
