<?

	function escape($str) {
	
	
 		$str = escapeshellarg($str);
		
		return $str;
	
	}

	$str = "An Episode's Title Named \"Trouble\" - ";	
	echo escape($str);
	
	echo "\n";
	
	$str = "A \/ B.txt";
	
	var_dump(file_exists($str));
	
	$bool = file_put_contents($str, "test");
	
	var_dump($bool);
	
	var_dump(file_exists($str));

?>