<?

	$xml = <<<XML
<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE Tags SYSTEM "/usr/local/share/matroska/xml/matroskatags.dtd">
<Tags>
</Tags>
XML;

	$sxe = new SimpleXMLElement($xml);
	
	/** Series **/
	
	$tag = $sxe->addChild("Tag");
	$targets = $tag->addChild("Targets");
	$targets->addChild("TargetTypeValue", "70");
	$targets->addChild("TargetType", "COLLECTION");
	
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "TITLE");
	$simple->addChild("String", "The Smurfs");
	
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "PRODUCTION_STUDIO");
	$simple->addChild("String", "Hanna-Barbera");
	
	/**
	 * I've decided that I only want to put strict
	 * metadata into the XML.  Subjective metadata
	 * can be put in the database.
	 */
	// Enum
	// Cartoons, TV Shows, Church Videos, Movie, Educational Film
// 	$simple = $tag->addChild("Simple");
// 	$simple->addChild("Name", "CONTENT_TYPE");
// 	$simple->addChild("String", "Cartoons");
	
	/** Season **/
	
	$tag = $sxe->addChild("Tag");
	$targets = $tag->addChild("Targets");
	$targets->addChild("TargetTypeValue", "60");
	$targets->addChild("TargetType", "SEASON");
	
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "DATE_RELEASE");
	$simple->addChild("String", "1981");
	
	// Season number
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "PART_NUMBER");
	$simple->addChild("String", "1");
	
	/** Episode **/
	
	$tag = $sxe->addChild("Tag");
	$targets = $tag->addChild("Targets");
	$targets->addChild("TargetTypeValue", "50");
	$targets->addChild("TargetType", "EPISODE");
		
	// Episode title
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "TITLE");
	$simple->addChild("String", "The Smurf's Apprentice");
	
	// Episode number
	$simple = $tag->addChild("Simple");
	$simple->addChild("Name", "PART_NUMBER");
	$simple->addChild("String", "1");
	
	
	

	
	$doc = new DOMDocument('1.0');
	$doc->preserveWhiteSpace = false;
	$doc->loadXML($sxe->asXML());
	$doc->formatOutput = true;
	echo $doc->saveXML();

?>