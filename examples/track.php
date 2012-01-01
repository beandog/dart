<?
	
	require_once "class.shell.php";
	require_once "class.dvd.php";
	require_once "class.dvdtrack.php";
	
	$dvd_track = new DVDTrack(1);
	
	$dvd_track->getXML();