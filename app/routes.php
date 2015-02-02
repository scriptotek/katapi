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

# /documents
Route::get('documents', 'DocumentsController@getIndex');
Route::get('documents.json', 'DocumentsController@getIndex');
Route::get('documents/{id}', 'DocumentsController@getShow')
    ->where(array('id' => '[0-9a-z.]+'));

# /subjects
Route::get('subjects', 'SubjectsController@getIndex');
Route::get('subjects/{id}', 'SubjectsController@getShow')
    ->where(array('id' => '[0-9a-z.]+'));

# /classes
Route::get('classes', 'ClassesController@getIndex');
Route::get('classes/{id}', 'ClassesController@getShow')
    ->where(array('id' => '[0-9a-z.]+'));


# /libraries/show
Route::get('libraries/show/{id}', 'LibrariesController@getShow');

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

