<?php

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogUdpHandler;

/*
|--------------------------------------------------------------------------
| Register The Laravel Class Loader
|--------------------------------------------------------------------------
|
| In addition to using Composer, you may use the Laravel class loader to
| load your controllers and models. This is useful for keeping all of
| your classes in the "global" namespace without Composer updating.
|
*/

ClassLoader::addDirectories(array(

	app_path().'/commands',
	app_path().'/controllers',
	app_path().'/models',
	app_path().'/database/seeds',

));

/*
|--------------------------------------------------------------------------
| Application Error Logger
|--------------------------------------------------------------------------
|
| Here we will configure the error logger setup for the application which
| is built on top of the wonderful Monolog library. By default we will
| build a basic log file setup which creates a single file for logs.
|
*/

Log::useFiles(storage_path().'/logs/laravel.log');


if (Config::get('database.papertrail.enable')) {

	$logger = Log::getMonolog();

	// Set the format
	$dateFormat = 'Y-m-d\TH:i:s\Z';
	$output = '%datetime% localhost katapi - - - %level_name%: %message%';
	$formatter = new LineFormatter($output, $dateFormat);

	// Setup the logger
	// $logger = new Logger('my_logger');
	$syslogHandler = new SyslogUdpHandler(Config::get('database.papertrail.host'), Config::get('database.papertrail.port'));
	$syslogHandler->setFormatter($formatter);
	$logger->pushHandler($syslogHandler);

	// Use the new logger
	// $logger->addInfo('Monolog test');

}


/*
|--------------------------------------------------------------------------
| Application Error Handler
|--------------------------------------------------------------------------
|
| Here you may handle any errors that occur in your application, including
| logging them or displaying custom views for specific errors. You may
| even register several error handlers to handle different types of
| exceptions. If nothing is returned, the default error view is
| shown, which includes a detailed stack trace during debug.
|
*/

function myExceptionHandler(Exception $exception, $code) {
	$c = new BaseController;
	return $c->abort($code, $exception->getMessage());
}

App::error(function(Exception $exception, $code)
{
	Log::error($exception);

	Mail::send('emails.exception', array('msg' => nl2br((string) $exception)), function($message) {
		$message->to(Config::get('mail.admin'))
			->subject('[KatApi] Error');
	});

	if ($code != 500) { // We want to debug those directly
		return myExceptionHandler($exception, $code);
	}
});


App::missing(function(Exception $exception) {
	return myExceptionHandler($exception, 404);
});

/*
|--------------------------------------------------------------------------
| Maintenance Mode Handler
|--------------------------------------------------------------------------
|
| The "down" Artisan command gives you the ability to put an application
| into maintenance mode. Here, you will define what is displayed back
| to the user if maintenance mode is in effect for the application.
|
*/

App::down(function()
{
	return Response::make("Be right back!", 503);
});

/*
|--------------------------------------------------------------------------
| Require The Filters File
|--------------------------------------------------------------------------
|
| Next we will load the filters file for the application. This gives us
| a nice separate location to store our route and application filter
| definitions instead of putting them all in the main routes file.
|
*/

require app_path().'/filters.php';
