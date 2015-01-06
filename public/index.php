<?php

$uri = (strpos($_SERVER['REQUEST_URI'], '?') === FALSE)
	? $_SERVER['REQUEST_URI']
	: substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));

$filename = substr($uri, strrpos($uri, '/') + 1);

$useLaravel = false;

$ext = isset($_GET['format']) ? $_GET['format'] : '';
if (empty($ext)) {
	$ext = strrpos($filename, '.') === FALSE ? '' : substr($filename, strrpos($filename, '.') + 1);
}

if (3<= strlen($ext) && strlen($ext) <= 7 && $ext != 'html') {
	//die($ext);
	$useLaravel = true;
}

if ($useLaravel) {

	require __DIR__.'/../bootstrap/autoload.php';
	$app = require_once __DIR__.'/../bootstrap/start.php';
	$app->run();

} else {

	// Serve "Static" HTML (single page app)
	readfile('app.html');

}

