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

		function get_video_encoder() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT vcodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_audio_encoder() {

			$sql = "SELECT presets.acodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";

			$var = $this->get_one($sql);

			return $var;

		}

		function get_crf() {

			$sql = "SELECT COALESCE(s.crf, p.crf) AS preset_crf FROM presets p INNER JOIN series_presets sp ON sp.preset_id = p.id JOIN series s ON s.id = sp.series_id AND sp.series_id = ".$this->id.";";
			$str = $this->get_one($sql);

			return $str;

		}

		function get_x264opts() {

			$arr = array();

			$sql = "SELECT presets.x264opts FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->id.";";
			$str = $this->get_one($sql);

			if(strlen($str))
				$arr[] = $str;

			if(count($arr) > 1)
				$var = implode(":", $arr);
			elseif(count($arr))
				$var = current($arr);
			else
				$var = '';

			return $var;

		}

		function get_x264_preset() {

			$arr = array();

			$sql = "SELECT COALESCE(s.x264_preset, p.x264_preset) FROM presets p JOIN series_presets sp ON p.id = sp.preset_id JOIN series s ON sp.series_id = s.id AND sp.series_id = ".$this->id.";";
			$str = $this->get_one($sql);

			return $str;

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
