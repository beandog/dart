<?

	/**
	 * --rip
	 *
	 * Add episodes from a device to the queue
	 *
	 */
	 
	if($rip && $disc_archived && !$num_episodes) {
	
		shell::msg("The disc is archived, but there are no episodes to rip.");
		shell::msg("Check the frontend to see if titles need to be added.");
		$eject = false;
	
	}
	
	if($rip && $disc_archived && $num_episodes) {
	
		/** Create directory to dump files to */
 		if(!is_dir($export_dir))
 			@mkdir($export_dir, 0755);
 		
		// Extract episodes
		if(count($dvd_episodes)) {
		
			/** Testing DVD episodes #s for a disc **/
// 			foreach($dvd_episodes as $episode_id) {
// 			
// 				$episodes_model = new Episodes_Model($episode_id);
// 				$series_id = $episodes_model->get_series_id();
// 				$series_model = new Series_Model($series_id);
// 				
// 				$episode_number = $episodes_model->get_number();
// 				
// 				echo "Episode ID: ";
// 				echo $episodes_model->id."\n";
// 				echo $episodes_model->title."\n";
// 				
// 				var_dump($episode_number);
// 			
// 			}

			$bar = new Console_ProgressBar('[%bar%] %percent%'." ($num_episodes episodes)", ':', ' :D ', 80, $num_episodes);
			$i = 0;
			
			foreach($dvd_episodes as $episode_id) {
			
				// New instance of a DB episode
				$episodes_model = new Episodes_Model($episode_id);
				$episode_season = $episodes_model->get_season();
				$episode_title = $episodes_model->title;
				$episode_part = $episodes_model->part;
				$episode_filename = get_episode_filename($episode_id);
				
				$tracks_model = new Tracks_Model($episodes_model->track_id);
 				$track_number = $tracks_model->ix;
 				
 				$dvd_track = new DVDTrack($track_number, $iso);
				
				$dvd_track->setDebug($debug);
				$dvd_track->setBasename($episode_filename);
				$dvd_track->setStartingChapter($episodes_model->starting_chapter);
				$dvd_track->setEndingChapter($episodes_model->ending_chapter);
				
				// Get the series ID
				$series_id = $episodes_model->get_series_id();
				
				// New instance of a DB series
				$series_model = new Series_Model($series_id);
				$series_title = $series_model->title;
				
				// Get and create our export directory
				$series_dir = $export_dir.formatTitle($series_title)."/";
 				if(!is_dir($series_dir))
 					mkdir($series_dir, 0755) or die("Can't create export directory $series_dir");
 				
				// Get the episode #
				// FIXME This should be a function call to keep the names the same
				if($series_model->indexed == 't') {
					
					$indexed_series = true;
					$episode_number = $episodes_model->get_number();
					
					if(!$episode_season)
						$episode_season = 1;
					
					if($episode_season)
						$episode_prefix = "${episode_season}x${episode_number}._";
					
				} else
					$indexed_series= false;
				
				if($episode_part > 1)
					$episode_suffix = ", Part $episode_part";
				
				$mkv = "$episode_filename.mkv";
				
				// Check to see if file exists, if not, rip it 				
				if(!file_exists($mkv))
					$queue_model->add_episode($episode_id, php_uname('n'));
				
				$i++;
				
				$bar->update($i);
				
				if(($i + 1) == $max)
					break(2);
				
			}
		
		}
		
		if($eject) {
			$dvd->eject();
			$ejected = true;
		}
		
		echo "\n";
	
	}