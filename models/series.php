<?php

	class Series_Model extends DBTable {

		function __construct($id = null) {

			$table = 'series';

			$this->id = parent::__construct($table, $id);

		}

		function get_collection_title() {

			$sql = "SELECT c.title FROM collections c INNER JOIN series s ON s.collection_id = c.id WHERE s.id = {$this->id};";

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

			$sql = "SELECT vcodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

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

			$var = $this->get_one($sql);

			return $var;

		}

		function get_denoise() {

			$sql = "SELECT denoise FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_sharpen() {

			$sql = "SELECT sharpen FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_sharpen_tune() {

			$sql = "SELECT sharpen_tune FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_fps() {

			$sql = "SELECT fps FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_x264_tune() {

		$sql = "SELECT presets.x264_tune FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";
			$var = $this->get_one($sql);

			return $var;

		}

		function get_preset_fps() {

			$sql = "SELECT presets.fps FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_library_name() {

			$sql = "SELECT l.name FROM series s INNER JOIN libraries l ON s.library_id = l.id WHERE s.id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

	}

?>
