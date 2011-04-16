<?
	// If polling for a new disc, check to see if one is in the
	// drive.  If there is, start over.
	if($poll && $rip) {

		$notice = false;
		
		while(true) {

			if($dvd->cddetect()) {
				shell::msg("Found a disc, starting over!");
				goto start;
			} else {
				if(!$notice)
					shell::msg("Waiting for a new disc on $device");
				$notice = true;
				sleep(60);
			}

		}

	}