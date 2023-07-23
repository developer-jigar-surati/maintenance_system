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

Route::post('admin_forgotpassword','AdminController@forgotpassword');
Route::get('/a@dmin/forgotpassword/{linkid}', 'AdminController@forgotpasswordlink');
Route::match(['get', 'post'],'forgotpasslink','AdminController@forgotpasslink');

Route::group(['middleware' => ['checkwebadmin']], function () {

    Route::get('/resetpass', function () {
        return view('resetpass');
    });
    Route::post('resetpassword','AdminController@resetpass');

    // Route::get('/dashboard', function () {
    //     return view('dashboard');
    // });
    Route::match(['get', 'post'],'dashboard','AdminController@showdashboard');

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
    Route::post('getbuildingLogs','BuildingController@getbuildingLogs');
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
    Route::post('getRentDetails','FlatholderController@getRentDetails');
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