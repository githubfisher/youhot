<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*Route::get('/', function () {
    return view('welcome');
});
*/
Route::get('/jpush', 'product@jpush');
Route::get('/mail/sendRemindEmail/{id}', 'product@sendRemindEmail');
Route::get('/mail/send', 'product@send');
Route::get('/note', 'NoteController@postNote');
Route::get('/push', 'NoteController@push');
Route::get('/mongodb', 'product@mongo');
Route::get('/testdb', 'product@testDb');
Route::get('/readfile', 'UploadFile@read');
