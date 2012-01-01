<?

	// Three Tags: Collection, Season, Episode

	require_once "class.shell.php";

	$filename = $argv[1];
	
	$exec = "mkvextract tags \"$filename\"";
	
	$arr = shell::cmd($exec);
	
	// Exit quietly if there isn't any tags
	if(!count($arr)) {
		if($verbose)
 			shell::msg("No metadata", true);
		exit(1);
	}
	
	$str = implode("\n", $arr);
	
 	$xml = simplexml_load_string($str);
 	
	$arr_sections = array(
		'COLLECTION' => 'series',
		'SEASON' => 'season',
		'EPISODE' => 'episode',
	);
	
	for($x = 0; $x < count($xml->children()); $x++) {
//   		print_r($xml->Tag[$x]->children());
		
		// Get the entity we are setting
		// Series, Season, Episode
		$section = (string)$xml->Tag[$x]->Targets->TargetType;
		
// 		print_r($xml->Tag[$x]->children());
		
		foreach($xml->Tag[$x]->children() as $obj) {
			if($obj->Name && $obj->String) {
// 				echo "$section\n";
				$name = (string)$obj->Name;
				$str = (string)$obj->String;
// 				echo "$name=$str\n";
				$arr[$section][$name] = $str;
			}
		}
		
	}
	
// 	print_r($arr);
	
	if(count($argc) == 1) {
	
		shell::msg("[Series]");
		shell::msg("Title: ".$arr['COLLECTION']['TITLE']);
		shell::msg("Studio: ".$arr['COLLECTION']['PRODUCTION_STUDIO']);
// 		shell::msg("Type: ".$arr['COLLECTION']['CONTENT_TYPE']);
		echo "\n";
		shell::msg("[Season]");
		shell::msg("Year: ".$arr['SEASON']['DATE_RELEASE']);
		shell::msg("Number: ".$arr['SEASON']['PART_NUMBER']);
		echo "\n";
		shell::msg("[Episode]");
		shell::msg("Title: ".$arr['EPISODE']['TITLE']);
		shell::msg("Number: ".$arr['EPISODE']['PART_NUMBER']);
	
	}
	

?>