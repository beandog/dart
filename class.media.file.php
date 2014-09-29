<?php

	class MediaFile {


		public function filename_title($str = 'Title', $underlines = false) {

			$str = preg_replace("/[^A-Za-z0-9 \-,.?':!_]/", '', $str);
			if($underlines)
				$str = str_replace(' ', '_', $str);
			return $str;

		}

		public function safe_filename_title($str = 'Title', $underlines = false) {

			$str = preg_replace("/[^A-Za-z0-9 _]/", '', $str);
			$str = str_replace("/", "-", $str);
			if($underlines)
				$str = str_replace(' ', '_', $str);
			return $str;

		}

	}

?>
