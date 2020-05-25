<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Series_Model extends DBTable {

		function __construct($id = null) {

			$table = "series";

			$this->id = parent::__construct($table, $id);

		}

		function get_collection_title() {

			$sql = "SELECT c.title FROM collections c INNER JOIN series s ON s.collection_id = c.id WHERE s.id = ".$this->db->quote($this->id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

		function get_preset_name() {

			$sql = "SELECT presets.name FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

		function get_preset_format() {

			$sql = "SELECT presets.format FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

		function get_video_encoder() {

			$series_id = abs(intval($this->id));

			$sql = "SELECT vcodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = $series_id;";

			$var = $this->db->getOne($sql);

			return $var;

		}

		function get_audio_encoder() {

			$sql = "SELECT presets.acodec FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

		function get_crf() {

			$sql = "SELECT s.crf AS series_crf, p.crf AS preset_crf FROM presets p INNER JOIN series_presets sp ON sp.preset_id = p.id JOIN series s ON s.id = sp.series_id AND sp.series_id = ".$this->db->quote($this->id).";";
			$arr = $this->db->getRow($sql);

			extract($arr);

			// Prefer lossless
			if($preset_crf == 0)
				return 0;

			if($series_crf != NULL)
				return $series_crf;

			return $preset_crf;

		}

		function get_x264opts() {

			$arr = array();

			$sql = "SELECT presets.x264opts FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			$str = $this->db->getOne($sql);

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

			$sql = "SELECT presets.x264_preset FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			$str = $this->db->getOne($sql);

			return $str;

		}

		function get_x264_tune() {

			$arr = array();

			$sql = "SELECT presets.x264_tune FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			$str = $this->db->getOne($sql);

			return $str;

		}

		function get_preset_deinterlace() {

			$sql = "SELECT presets.deinterlace FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$str = $this->db->getOne($sql);

			return $str;

		}

		function get_preset_decomb() {

			$sql = "SELECT presets.decomb FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$str = $this->db->getOne($sql);

			return $str;

		}

		function get_preset_detelecine() {

			$sql = "SELECT presets.detelecine FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$str = $this->db->getOne($sql);

			return $str;

		}

		function get_preset_fps() {

			$sql = "SELECT presets.fps FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";

			$var = $this->db->getOne($sql);

			return $var;

		}

	}
?>
