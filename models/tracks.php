<?php

	require_once(dirname(__FILE__)."/dbtable.php");

	class Tracks_Model extends DBTable {

		function __construct($id = null) {

			$table = "tracks";

			$this->id = parent::__construct($table, $id);

		}

		/**
		 * Do a lookup for a primary key based on
		 * dvd_id and ix.
		 *
		 * @params int $dvd_id tracks.dvd_id
		 * @params int $ix tracks.ix
		 */
		public function find_track_id($dvd_id, $ix) {

			$sql = "SELECT id FROM tracks WHERE dvd_id = $dvd_id AND ix = $ix;";
			$var = $this->get_one($sql);

			return $var;
		}

		public function get_audio_streams() {

			$sql = "SELECT * FROM audio WHERE track_id = ".$this->id." AND active = 1 ORDER BY langcode = 'en' DESC, channels DESC, format = 'dts' DESC, streamid;";

			$arr = $this->get_all($sql);

			return $arr;

		}

		public function get_best_quality_audio_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->id." AND langcode = 'en' AND active = 1 AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->id." AND active = 1) ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";

			$var = $this->get_one($sql);

			return $var;

		}

		// For Blu-rays, order quality by my preferred codecs:
		// LPCM - uncompressed, truhd - Dolby Atmos, DTS Master, DTS HD, DTS, Dolby Digital
		public function get_best_quality_audio_ix($disc_type = 'dvd') {

			if($disc_type == 'dvd')
				$sql = "SELECT COALESCE(ix, 1) FROM audio WHERE track_id = ".$this->id." AND langcode = 'en' AND active = 1 AND channels = (SELECT MAX(channels) FROM audio WHERE track_id = ".$this->id." AND active = 1) ORDER BY CASE WHEN format = 'dts' THEN 0 ELSE 1 END, streamid LIMIT 1;";
			elseif($disc_type == 'bluray')
				$sql = "SELECT COALESCE(ix, 1) FROM audio WHERE track_id = ".$this->id." AND langcode = 'eng' AND active = 1 ORDER BY format = 'lpcm' DESC, format = 'truhd' DESC, format = 'dtshd-ma' DESC, format = 'dtshd' DESC, format = 'dts' DESC, format = 'ac3' DESC, ix;";

			$var = $this->get_one($sql);

			return $var;

		}

		// Get an audio track with a hardware supported codec (DTS, Dolby Digital)
		public function get_fallback_codec_ix() {

			$sql = "SELECT COALESCE(ix, 1) FROM audio WHERE track_id = ".$this->id." AND langcode = 'eng' AND active = 1 ORDER BY format = 'dts' DESC, format = 'ac3' DESC, ix;";

			$var = $this->get_one($sql);

			return $var;

		}

		public function get_first_english_streamid() {

			$sql = "SELECT COALESCE(streamid, '0x80') FROM audio WHERE track_id = ".$this->id." AND langcode = 'en' AND active = 1 ORDER BY streamid LIMIT 1;";

			$var = $this->get_one($sql);

			return $var;

		}

		public function get_first_english_ix() {

			$sql = "SELECT ix FROM audio WHERE track_id = ".$this->id." AND (langcode = 'en' OR langcode = 'eng') AND active = 1 ORDER BY ix LIMIT 1;";

			$var = $this->get_one($sql);

			return $var;

		}

		public function get_bluray_hb_track() {

			$sql = "SELECT dvd_id, ix FROM tracks WHERE id = ".$this->id.";";
			$row = $this->get_row($sql);
			extract($row);

			$sql = "SELECT ix FROM tracks WHERE dvd_id = $dvd_id ORDER BY ix;";
			$arr = $this->get_col($sql);

			$hb_track_number = array_search($ix, $arr);
			$hb_track_number++;

			return $hb_track_number;

		}

		public function get_num_active_audio_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM audio WHERE track_id = ".$this->id." AND active = 1";
			if(strlen($lang) == 2)
				$sql .= " AND langcode = '".pg_escape_string($lang)."'";

			$sql .= ";";
			$var = $this->get_one($sql);
			return $var;

		}

		public function has_closed_captioning() {

			$sql = "SELECT closed_captioning FROM tracks WHERE id = ".$this->id.";";
			$var = $this->get_one($sql);
			if($var)
				return true;
			else
				return false;

		}

		public function get_num_subp_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = ".$this->id;
			if(strlen($lang) == 2)
				$sql .= " AND langcode = '".pg_escape_string($lang)."'";

			$sql .= ";";
			$var = $this->get_one($sql);
			return $var;

		}

		public function get_num_active_subp_tracks($lang = '') {

			$sql = "SELECT COUNT(1) FROM subp WHERE track_id = ".$this->id." AND active = 1";
			if(strlen($lang) == 2)
				$sql .= " AND langcode = '".pg_escape_string($lang)."'";

			$sql .= ";";
			$var = $this->get_one($sql);
			return $var;

		}

		public function get_first_english_subp() {

			$sql = "SELECT ix FROM subp WHERE track_id = ".$this->id." AND (langcode = 'en' OR langcode = 'eng') AND active = 1 ORDER BY ix LIMIT 1;";

			$var = $this->get_one($sql);

			return $var;

		}

		public function get_num_chapters() {

			$sql = "SELECT COUNT(1) FROM chapters WHERE track_id = ".$this->id.";";
			$var = $this->get_one($sql);
			return $var;

		}

		function get_audio_details($audio_stream_id) {

			$track_id = intval($this->id);

			$sql = "SELECT format, channels FROM audio WHERE track_id = $track_id AND streamid = '".pg_escape_string($audio_stream_id)."';";
			$arr = $this->get_row($sql);

			return $arr;

		}

		/**
		 * Find out what subtitle type there is, prioritizing:
		 * - Any English vobsub active track
		 * - Only one vobsub active track
		 * - Closed captioning
		 */
		function get_subtitle_type() {

			if($this->get_num_active_subp_tracks('en'))
				return 'vobsub';

			if($this->get_num_active_subp_tracks() == 1)
				return 'vobsub';

			if($this->has_closed_captioning())
				return 'cc';

			return '';

		}

	}
?>
