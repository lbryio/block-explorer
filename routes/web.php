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


Route::get('/', 'HomeController')->name('home');

Route::get('/blocks', 'BlockController@getBlocks')->name('blocks');
Route::get('/block/{height?}', 'BlockController@getBlock')->where('height', '[0-9]+')->name('block');

Route::get('/txs', 'TransactionController@getTransactions')->name('transactions');
Route::get('/tx/{tx?}', 'TransactionController@getTransaction')->where('tx', '[A-Za-z0-9]{64}')->name('transaction');
Route::get('/mempool', 'TransactionController@getMempoolTransactions')->name('transactions_mempool');

Route::get('/address/{address}', 'AddressController@getAddress')->where('tx', '[A-Za-z0-9]{34}')->name('address');
Route::get('/addresses', 'AddressController@getAddresses')->name('addresses');

Route::get('/claims', 'ClaimController@getClaims')->name('claims');
Route::get('/claim/{claim?}', 'ClaimController@getClaim')->where('claim', '[A-Za-z0-9\-]+')->name('claim');

Route::get('/search', 'SearchController')->where('what', '[A-Za-z0-9]+');

Route::get('/stats/mining', 'StatisticsController@getMiningStats')->name('statistics_mining');
Route::get('/stats/content', 'StatisticsController@getContentStats')->name('statistics_content');

Route::get('/api/stats/mining', 'APIController@miningStats')->name('api_mining_stats');
Route::get('/api/stats/blocks/{time_range}', 'APIController@blocksStats')->name('api_blocks_stats');


// REDIRECT OLD ROUTES TO NEW ONES

Route::get('/blocks/{height?}', function ($height) {
    return redirect(route('block', $height));
})->where('height', '[0-9]+');

Route::get('/txs/{tx?}', function ($tx) {
    return redirect(route('transaction', $tx));
})->where('tx', '[A-Za-z0-9]{64}');

Route::get('/claims/{claim?}', function ($claim) {
    return redirect(route('claim', $claim));
})->where('claim', '[A-Za-z0-9\-]+');
