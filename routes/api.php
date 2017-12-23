<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/
Route::get('/product', 'product@getProduct');
Route::get('/search', 'search@search');
Route::match(['get', 'post'], '/coru', 'search@coru');
Route::get('/sum', 'search@getSum');
Route::get('/indices', 'search@getIndices');
Route::post('/create', 'search@create');
Route::post('/update', 'search@update');
Route::post('/delete', 'search@delete');
Route::get('/get', 'search@getDocument');
Route::get('/mapping', 'search@mapping');
Route::get('/scws', 'search@splitWords');
// For Test Server
Route::match(['get', 'post'], '/coru_test', 'SearchForTest@coru');
Route::get('/search_test', 'SearchForTest@search');
Route::post('/create_test', 'SearchForTest@create');
Route::post('/update_test', 'SearchForTest@update');
Route::post('/delete_test', 'SearchForTest@delete');
Route::get('/get_test', 'SearchForTest@getDocument');
// Data
Route::get('/data/get', 'Data@get');
Route::put('/data/update', 'Data@update');
Route::delete('/data/delete', 'Data@delete');
Route::post('/data/add', 'Data@create');
// Data For Test Server
Route::post('/data/add_test', 'DataTest@create');
// Upload File
Route::post('/upload', 'UploadFile@upload');
