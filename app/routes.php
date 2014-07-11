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

# /documents/show/:id

//Route::get('documents/show/{id}', 'BibsysController@getShow');

Route::get('subjects/{vocabulary}/{term}', 'SubjectsController@getShow');
Route::get('documents/show/{id}', 'DocumentsController@getShow')
	->where(array('id' => '[0-9X-]+'));

// Route::get('bibsys/{id}', function($id) {
// 	return Redirect::action('BibsysController@getShow', $id);
// });

# /documents/search
Route::get('documents/search', 'BibsysController@getSearch');
Route::get('bibsys/search', function() {
	return Redirect::action('BibsysController@getSearch');
});

# /covers/show/:id
Route::get('covers/show/{id}', 'CoversController@getShow');
//Route::get('covers/select/{id}', 'CoversController@getSelect');

//Route::get('covers/{id}/store', 'CoversController@postStore');
//Route::get('covers/{id}/remove', 'CoversController@getRemove');
//Route::get('covers/{id}/list', 'CoversController@getList');
//Route::get('covers/{id}/set-preferred/{index}', 'CoversController@postSetPreferred');


//Route::controller('documents', 'DocumentsController');
//Route::controller('subjects', 'SubjectsController');
//Route::controller('covers', 'CoversController');

Route::controller('examples', 'ExamplesController');

