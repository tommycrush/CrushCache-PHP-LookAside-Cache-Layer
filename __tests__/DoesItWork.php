<?php

require_once('../CrushCache.class.php');

$cache = new CrushCache();

$data = array(
	'email' => 'myemail@test.com',
	'first_name' => 'Tommy',
	'last_name' => 'Crush',
);

//$cache->insert("user", $data);

print_r( $cache->get("user","user_id",1) );