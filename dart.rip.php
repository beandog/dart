<?php

	/**
	 * --rip
	 *
	 * Cleanup after everything else is run
	 */

	 if($opt_rip) {

	 	if($target_iso_exists) {

			echo "[Rip]\n";
			echo "* Batch rip to $display_iso complete\n";
			$drive->open();

		}

	 }
