<?

	require_once 'inc.pgsql.php';
	require_once 'class.dvd.php';
	
	$dvd =& new DVD();
	
	$sql = "SELECT id, chapters FROM tracks WHERE chapters != '' ORDER BY id;";
	$rs = pg_query($sql) or die(pg_last_error());
	
	while($row = pg_fetch_assoc($rs)) {
		extract($row);
		$arr = explode("\n", $chapters);
		$arr = preg_grep('/^CHAPTER\d+=/', $arr);
		$arr = preg_replace('/^CHAPTER\d+=/', '', $arr);
		$arr = array_unique($arr);
		#print_r($arr);
		
		$x = 0;
		
		if(count($arr) > 1) {
			foreach($arr as $start) {
				$sql = "INSERT INTO track_chapters (track, start_time) VALUES ($id, '$start');";
				pg_query($sql) or die(pg_last_error());
			}
		}
	}

?>