<?php
require_once "vendor/autoload.php";
require_once "lib.php";

$client = HttpClientCreator();
try {
	echo $client->get('http://packagist.org'. sprintf('/packages/%s.json', $_GET['package']))->send()->getBody(TRUE);
} catch (Exception $e) {
	echo json_encode(['type' => 'error', 'msg' => 'Cannot resolve package repo.', 'hashtag' => ['fail']]);
}

