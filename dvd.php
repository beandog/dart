<?

	require_once "ar/pg.dvds.php";
	
	require_once 'class.shell.php';
	
	require_once "class.dvd.php";
	require_once "class.dvdtrack.php";
	require_once "class.dvdaudio.php";
	require_once "class.dvdsubs.php";
	
	$dvd = new DVD();
	
	$action = $argv[1];
	
	if($action == "import") {
	
		$uniq_id = $dvd->getID();
	
		$d = dvds::find_by_uniq_id($uniq_id);
		
		if(is_null($d->id)) {
		
			$arr = array(
				'uniq_id' => $uniq_id,
				'title' => $dvd->getTitle(),
				'vmg_id' => $dvd->getVMGID(),
				'provider_id' => $dvd->getProviderID(),
				'longest_track' => $dvd->getLongestTrack(),
			);
		
			$d = dvds::create($arr);
		
		}
		
		$num_tracks = $dvd->getNumTracks();
		
		for($track_number = 1; $track_number <= $num_tracks; $track_number++) {
		
			$dvd_track = new DVDTrack($track_number);
			
			$track = tracks::first(array('conditions' => array('dvd_id' => $d->id, 'ix' => $track_number)));
			
			if(is_null($track->id)) {
			
				$arr = array(
					'dvd_id' => $d->id,
					'ix' => $dvd_track->getIX(),
					'length' => $dvd_track->getLength(),
					'vts_id' => $dvd_track->getVTSID(),
					'vts' => $dvd_track->getVTS(),
					'ttn' => $dvd_track->getTTN(),
					'fps' => $dvd_track->getFPS(),
					'format' => $dvd_track->getVideoFormat(),
					'aspect' => $dvd_track->getAspectRatio(),
					'width' => $dvd_track->getWidth(),
					'height' => $dvd_track->getHeight(),
					'df' => $dvd_track->getDF(),
					'angles' => $dvd_track->getAngles(),
				);
				
				$track = tracks::create($arr);
				
			}
			
			// Get lsdvd XML to pass to sub-classes
			$xml = $dvd_track->getXML();
				
			/** Palettes **/
			$colors = $dvd_track->getPaletteColors();
			
			if(count($colors)) {
			
				foreach($colors as $str) {
				
					$arr = array('track_id' => $track->id, 'color' => $str);
				
					$palette = palettes::first(array('conditions' => array($arr)));
					
					if(is_null($palette))
						$palette = palettes::create($arr);
				
				}
			
			}
				
			/** Audio Streams **/
		
			$audio_streams = $dvd_track->getAudioStreams();
			
			foreach($audio_streams as $streamid) {
			
				$dvd_audio = new DVDAudio($xml, $streamid);
				
				$arr = array(
					'track_id' => $track->id,
					'ix' => $dvd_audio->getXMLIX(),
				);
				
				$audio = audio::first(array('conditions' => $arr));
				
				if(is_null($audio->id)) {
				
					$arr = array_merge($arr, array(
						'langcode' => $dvd_audio->getLangcode(),
						'language' => $dvd_audio->getLanguage(),
						'format' => $dvd_audio->getFormat(),
						'frequency' => $dvd_audio->getFrequency(),
						'quantization' => $dvd_audio->getQuantization(),
						'channels' => $dvd_audio->getChannels(),
						'ap_mode' => $dvd_audio->getAPMode(),
						'content' => $dvd_audio->getContent(),
						'streamid' => $streamid,
					));
					
					$audio = audio::create($arr);
					
				}
				
			}
			
			
			/** Subtitles **/
			
			$subtitle_streams = $dvd_track->getSubtitleStreams();
			
			foreach($subtitle_streams as $streamid) {
			
				$dvd_subp = new DVDSubs($xml, $streamid);
				
				$arr = array(
					'track_id' => $track->id,
					'ix' => $dvd_subp->getXMLIX(),
				);
				
				$subp = subp::first(array('conditions' => $arr));
				
				if(is_null($subp->id)) {
				
					$arr = array_merge($arr, array(
						'langcode' => $dvd_subp->getLangcode(),
						'language' => $dvd_subp->getLanguage(),
						'content' => $dvd_subp->getContent(),
						'streamid' => $streamid,
					));
					
					$subp = subp::create($arr);
					
				}
				
			}
			
			/** Chapters **/
			
			$dvd_chapters = $dvd_track->getChapters();
			
			foreach($dvd_chapters as $chapter_number => $arr) {
			
				$arr = array_merge($arr, array(
					'track_id' => $track->id,
				));
				
				$chapters = chapters::first(array('conditions' => $arr));
				
 				if(is_null($chapters->id))
 					$chapters = chapters::create($arr);
				
			}
			
			/** Cells **/
			
			$dvd_cells = $dvd_track->getCells();
			
			foreach($dvd_cells as $cell_ix => $cell_length) {
			
				$arr = array(
					'ix' => $cell_ix,
					'length' => $cell_length,
					'track_id' => $track->id,
				);
				
				$cells = cells::first(array('conditions' => $arr));
				
 				if(is_null($cells->id))
 					cells::create($arr);
				
			}
				
			
		}
		
	}

	
?>