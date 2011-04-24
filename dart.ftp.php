<?

	if($ftp) {
	
		chdir("/home/steve/dvds");
		$exec = "find . -mindepth 1 -type d -print0 | xargs -0 -I {} ncftpput -m -z -R -DD zotac /var/media/updates/ {}";
		passthru($exec);
	
	}