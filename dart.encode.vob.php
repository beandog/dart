<?php

			/*
			if($dumpvob) {

				$vob = "$episode_filename.vob";

				if(!file_exists($vob)) {

					$tmpfname = tempnam(dirname($episode_filename), "vob.$episode_id.");
					$dvdtrack = new DvdTrack($track_number, $iso, $debug);
					$dvdtrack->getNumAudioTracks();
					$dvdtrack->setBasename($tmpfname);
					$dvdtrack->setStartingChapter($episode_starting_chapter);
					$dvdtrack->setEndingChapter($episode_ending_chapter);
					$dvdtrack->setAudioStreamID($default_audio_streamid);
					unlink($tmpfname);
					$dvdtrack->dumpStream();

					rename("$tmpfname.vob", $vob);

				}

				$src = $vob;

			} else {
				$src = $episode['src_iso'];
			}
			*/
