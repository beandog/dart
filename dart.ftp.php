<?

	if($ftp) {
	
		require_once 'File/Find.php';
	
		$src = "/home/steve/dvds/";
		$target = "/var/media/updates/";

		// Continually look for files to send
		while(count($arr = &File_Find::search('mkv$', $src))) {
		
			$src_filename = current($arr);
		
			$dest_filename = str_replace($src, "", $src_filename);
			$dest_dir = $target.dirname($dest_filename);
			
			$exec = "ncftpput -m -z -DD zotac $dest_dir $src_filename";
			
			passthru($exec);
			
		}
	
	}