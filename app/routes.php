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


Route::get('bibsys/search', 'BibsysController@getSearch');
Route::get('bibsys/{id}', 'BibsysController@getShow');

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
			'message' => $exception->getMessage() ?: 'Page not found'
		), 404);
	} else if ($format->getValue() == 'application/json') {
		return Response::json(array(
			'error' => $exception->getMessage() ?: 'Page not found'
		), 404);
	}
});