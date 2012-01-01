<?

	require_once 'class.matroska.php';
	require_once 'class.shell.php';
	
	$options = shell::parseArguments();
	
	if($options['v'] || $options['verbose'] || $ini['verbose'] || $debug) {
		$verbose = true;
	}
	
	$filename = shell::formatTitle($title);
		
	$matroska = new Matroska($filename.".mkv");
	
	if($aspect)
		$this->setAspectRatio($aspect);
	
 	$matroska->addTag();
 	$matroska->addSimpleTag("ORIGINAL_MEDIA_TYPE", "DVD");
 	$matroska->addSimpleTag("DATE_TAGGED", date("Y-m-d"));
 	$matroska->addSimpleTag("PLAY_COUNTER", 0);
 	
 	$matroska->addTag();
 	
 	$matroska->addTarget(70, "COLLECTION");
 	$matroska->addSimpleTag("TITLE", $title);
 	$matroska->addSimpleTag("SORT_WITH", $title);
 	
 	$xml = $matroska->getXML();
 	print_r($xml);
?> 	