<?

	set_include_path(get_include_path().PATH_SEPARATOR.'/home/steve/php/inc');
	require_once 'inc.mysql.php';

	$sql = "SELECT intid, coverfile FROM videometadata WHERE coverfile != 'No Cover' AND coverfile != '';";
	$rs = mysql_query($sql);

	while($row = mysql_fetch_assoc($rs)) {

		if(!file_exists($row['filename'])) {
			$sql = "UPDATE videometadata SET coverfile = '' WHERE intid = ".$row['intid'].";";
			mysql_query($sql);
		}
	}
