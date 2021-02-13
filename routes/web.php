<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return "<h1>Comming Soon</h1>";
});

Route::get('/a@dmin', function () {
    return view('login');
});
Route::post('admin_login','AdminController@dologin');
Route::get('logout', 'AdminController@dologout');

Route::group(['middleware' => ['checkwebadmin']], function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    });

    //Building
    Route::get('/buildingslist', function () {
        return view('building');
    });
    Route::post('savebuilding','BuildingController@savebuilding');
    Route::post('getbuildings','BuildingController@getbuildings');
    Route::post('changebuildingstatus','BuildingController@changebuildingstatus');
    Route::post('deletebuilding','BuildingController@deletebuilding');
    Route::post('getbuildingbyid','BuildingController@getbuildingbyid');
    Route::post('updatebuilding','BuildingController@updatebuilding');
    //Building


    //Flat Holders
    Route::get('/flatholders', function () {
        return view('flatholders');
    });
    Route::post('getbuldingsforflatholder','BuildingController@getbuldingsforflatholder');
    Route::post('addflatholder','FlatholderController@saveflatholder');
    Route::post('getflatholders','FlatholderController@getflatholders');
    Route::post('changeflatholderstatus','FlatholderController@changeflatholderstatus');
    Route::post('deleteflatholder','FlatholderController@deleteflatholder');
    Route::post('getflatholderbyid','FlatholderController@getflatholderbyid');
    Route::post('updateflatholder','FlatholderController@updateflatholder');

    Route::post('saveflatholderasadmin','FlatholderController@saveflatholderasadmin');
    //Flat Holders
    
    //Category
    Route::get('/category', function () {
        return view('category');
    });
    Route::post('savecategory','CategoryController@savecategory');
    Route::post('getcategory','CategoryController@getcategory');
    Route::post('changecategorystatus','CategoryController@changecategorystatus');
    Route::post('deletecategory','CategoryController@deletecategory');
    Route::post('getcategorybyid','CategoryController@getcategorybyid');
    Route::post('updatecategory','CategoryController@updatecategory');
    //Category

    //ledger
    Route::get('/ledger', function () {
        return view('ledger');
    });
    Route::post('getcategoryforledger','CategoryController@getcategoryforledger');
    // Route::post('getbuildingsforledger','CategoryController@getbuildingsforledger');
    Route::post('getbuildingsforledger','BuildingController@getbuldingsforflatholder');
    Route::post('getflatholderforledger','CategoryController@getflatholderforledger');

    Route::post('addledgerpayment','LedgerController@addledgerpayment');
    Route::post('getcategory','CategoryController@getcategory');
    Route::post('changecategorystatus','CategoryController@changecategorystatus');
    Route::post('deletecategory','CategoryController@deletecategory');
    Route::post('getcategorybyid','CategoryController@getcategorybyid');
    Route::post('updatecategory','CategoryController@updatecategory');

    Route::post('getledgerdata','LedgerController@getledgerdata');
    //ledger
});