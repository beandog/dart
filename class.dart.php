<?

	class dart {
	
		function archived($dvd_id) {
		
			$dvd = dvds::find_by_uniq_id($dvd_id);
			
			if(!is_null($dvd->id))
				return true;
			else
				return false;
			
		}
		
		/**
		 * Format a title for saving to filesystem
		 *
		 * @param string original title
		 * @return new title
		 */
		function formatTitle($str = 'Title', $underlines = true) {
			$str = preg_replace("/[^A-Za-z0-9 \-,.?':!]/", '', $str);
			$underlines && $str = str_replace(' ', '_', $str);
			return $str;
		}
		
		public function get_episode_filename($episode_id) {
		
			// Class instatiation
			$episodes_model = new Episodes_Model($episode_id);
			$episode_title = $episodes_model->title;
			$track_id = $episodes_model->track_id;
			$episode_number = $episodes_model->get_number();
			$episode_part = $episodes_model->part;
			$episode_season = $episodes_model->get_season();
			
			$series_model = new Series_Model($episodes_model->get_series_id());
			$series_title = $series_model->title;
			$series_dir = $this->export.$this->formatTitle($series_title)."/";
			
			/** Build the episode filename **/
			if($series_model->indexed == 't' && $episode_season)
					$episode_prefix = "${episode_season}x${episode_number}._";
			
			if($episode_part > 1)
				$episode_suffix = ", Part $episode_part";
			
			/** Filenames **/
			$episode_filename = $series_dir.$this->formatTitle($episode_prefix.$episode_title.$episode_suffix);
			
			return $episode_filename;
		
		}
		
	}

?>
