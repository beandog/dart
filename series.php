#!/usr/bin/php
<?

	$start = time();

	require_once 'class.shell.php';
	require_once 'class.drip.php';
	require_once 'class.dvd.php';
	require_once 'class.dvdtrack.php';
	require_once 'class.drip.series.php';
	require_once 'class.matroska.php';
	require_once 'DB.php';
	

	$db =& DB::connect("pgsql://steve@charlie/movies");
	$db->setFetchMode(DB_FETCHMODE_ASSOC);
	PEAR::setErrorHandling(PEAR_ERROR_DIE);
	
	
	$series = new DripSeries(26);
	
	
 	
	print_r($series);
	
	
?>