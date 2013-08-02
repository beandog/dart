<?php
	require_once("php-activerecord/ActiveRecord.php");

	$connections = array(
    		'pgsql' => 'pgsql://steve@localhost/dvds',
	);

	ActiveRecord\Config::initialize(function($cfg) use ($connections) {
		$cfg->set_model_directory('/home/steve/git/dart/activerecord');
		$cfg->set_connections($connections);

		$cfg->set_default_connection('pgsql');
	});
