<?
	
	require_once 'class.mediainfo.php';

	$m = new MediaInfo("/home/steve/dvds/movie.vob");

	$bool = $m->hasCC();

	var_dump($bool);

