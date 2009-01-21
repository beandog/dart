<?

	require_once 'inc.pgsql.php';
	require_once 'class.dvd.php';
	
	bcscale(3);
	
	$dvd =& new DVD();
	
	$sql = "SELECT DISTINCT(start_time) FROM track_chapters WHERE start_time != '00:00:00.000' AND seconds = 0.000 ORDER BY start_time;";
	$rs = pg_query($sql) or die(pg_last_error());
	
	while($row = pg_fetch_assoc($rs)) {
		extract($row);
		
		$tmp = explode(':', $start_time);
		
		$seconds = ($tmp[0] * 60 * 60) + ($tmp[1] * 60);
		$seconds = bcadd($seconds, $tmp[2]);
		
		$sql = "UPDATE track_chapters SET seconds = $seconds WHERE start_time = '$start_time';";
		echo "$sql\n";
		pg_query($sql) or die(pg_last_error());
	}

?>