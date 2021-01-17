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
});*/

Route::group([
    'prefix' => 'v1'
], function ($router) {
    Route::get('difficulty/{last_n_hours}', 'APIController@difficulty')->where('last_n_hours', '[0-9]+')->name('difficulty_api');
    Route::get('blocksize/{last_n_hours}', 'APIController@blockSize')->where('last_n_hours', '[0-9]+')->name('blocksize_api');
});
