<?

	if($ftp) {

		$src = getenv('HOME')."/dvds/";
		$target = "/var/media/updates/";

		// Continually look for files to send
		while(count($arr =& glob($src."*/*.mkv"))) {

			$src_filename = current($arr);

			$dest_filename = str_replace($src, "", $src_filename);
			$dest_dir = $target.dirname($dest_filename);

			$exec = "ncftpput -m -z -DD zotac ".shell::escape_string($dest_dir)." ".shell::escape_string($src_filename);

			passthru($exec);

		}

	}
