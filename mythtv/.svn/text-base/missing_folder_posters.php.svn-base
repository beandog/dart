<?
	require_once 'inc.mythtv.php';
	require_once 'inc.pgsql.php';

	$sql = "SELECT title FROM tv_shows ORDER BY title;";
	$rs = pg_query($sql);

	while($row = pg_fetch_row($rs)) {
		$name = str_replace(' ', '_', $row[0]);

		# Create dvds/dir
		$dir = '/var/media/dvds/'.$name;

		if(!is_dir($dir))
			mkdir($dir) or die("Couldn't mkdir $dir");

		$poster1 = $dir.'/folder.jpg';

		# Create posters/dir
		$dir = '/var/media/posters/'.$name;
		
		if(!is_dir($dir))
			mkdir($dir) or die("Couldn't mkdir $dir");

		$poster2 = $dir.'/folder.jpg';

		if(file_exists($poster1) && !file_exists($poster2))
			copy($poster1, $poster2);
		elseif(file_exists($poster2) && !file_exists($poster1))
			copy($poster2, $poster1);
		elseif(!file_exists($poster1) && !file_exists($poster2))
			$arr[] = $name;

	}

	print_r($arr);
?>
