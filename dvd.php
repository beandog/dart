<?

	require_once "ar/pg.dvds.php";
	
	require_once 'class.shell.php';
	
	require_once "class.dvd.php";
	require_once "class.dvdtrack.php";
	require_once "class.dvdaudio.php";
	
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
		
			$d = dvds::create(array($arr));
		
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
			
				// Get lsdvd XML to pass to DVDAudio class
				$xml = $dvd_track->getXML();
				
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
			
			}
		
		}
		
	}

	
?>