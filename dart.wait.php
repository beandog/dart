<?php

	// If polling for a new disc, check to see if one is in the
	// drive.  If there is, start over.
	if($wait && ($rip || $import || $dump_iso) && !$device_is_iso) {
		// Only toggle devices if passed more than one
		// Otherwise, just re-poll the original.
		// This is useful in cases where --wait is called
		// on two separate devices, so two drives can
		// be accessed at the same time
		if(count($devices) > 1) {
			$device = toggle_device($device);
			sleep(1);
		}
		// If there is only one device, then wait until the tray is
		// closed.
		else {
			if($debug)
				shell::stdout("! Waiting for the tray to be closed");
			while($drive->is_open()) {
				sleep(1);
			}
			if($debug)
				shell::stdout("! Wait sequence is closing the tray");
			$drive->close(false);
		}

		if($debug)
			shell::stdout("! Going to start position");

		goto start;
	}
?>
