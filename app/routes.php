<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
	return View::make('hello');
});


Route::get('{vendor}/search', 'DocumentsController@getSearch');
Route::get('{vendor}/{id}', 'DocumentsController@getShow');

//Route::controller('documents', 'DocumentsController');
//Route::controller('subjects', 'SubjectsController');
//Route::controller('covers', 'CoversController');


App::missing(function($exception)
{
	$negotiator = new \Negotiation\FormatNegotiator();
	$acceptHeader = $_SERVER['HTTP_ACCEPT'];

	$priorities = array('text/html', 'application/json');
	$format = $negotiator->getBest($acceptHeader, $priorities);

	if ($format->getValue() == 'text/html') {
		return Response::view('errors.missing', array(
			'message' => $exception->getMessage()
		), 404);
	} else if ($format->getValue() == 'application/json') {
		return Response::json(array(
			'error' => $exception->getMessage()
		), 404);
	}
});