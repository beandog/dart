<?

	set_include_path(get_include_path().PATH_SEPARATOR.'/home/steve/php/inc');
	require_once 'inc.mysql.php';

	$sql = "SELECT intid, filename FROM videometadata WHERE (coverfile = '' OR coverfile = 'No Cover');";
	$rs = mysql_query($sql);

	while($row = mysql_fetch_assoc($rs)) {

		$base_dir = basename(dirname($row['filename']));

		$dir = '/var/media/posters/'.$base_dir.'/';
		$mkv = $row['filename'];
		$base = basename($mkv, '.mkv');

		$jpg1 = $dir.$base.".mkv.jpg";
		$jpg2 = $dir.$base.".jpg";

		$filename = false;

		if(file_exists($jpg1)) {
			$filename = $jpg1;
		} elseif(file_exists($jpg2)) {
			$filename = $jpg2;
		}

		if($filename) {
			$filename = mysql_escape_string($filename);
			$sql = "UPDATE videometadata SET coverfile = '$filename' WHERE intid = ".$row['intid'].";";
			
			mysql_query($sql);

			$x++;

		}
	}

	echo "Updated $x covers\n";
?>
