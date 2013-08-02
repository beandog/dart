<?

	require_once 'PEAR.php';
	require_once 'MDB2.php';

	$dsn = "pgsql://steve@charlie/dvds";

	$options = array(
		'debug'       => 2,
		'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
	);

	$db =& MDB2::factory($dsn, $options);
	$db->loadModule('Manager');
	$db->loadModule('Extended');

	$db->setFetchMode(MDB2_FETCHMODE_ASSOC);

	PEAR::setErrorHandling(PEAR_ERROR_DIE);

	function pearError ($e) {
		echo $e->getMessage().': '.$e->getUserinfo();
	}

	PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'pearError');

?>
