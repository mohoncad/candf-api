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
    return view('welcome');
});

Route::get('/recover/password/new/{token__}', 'API\Auth\RecoverPasswordController@NewPasswordPage');
Route::post('/recover/password/new/{token__}', 'API\Auth\RecoverPasswordController@UpdateUserPassword');

Route::get('/registration/verify_email/{key}', 'API\Registration\RegistrationController@MakeEmailVerified');


Route::get('/test-1', function () {
    // return response()->json([
    //     'success' => true,
    //     'message' => 'Server is running!',
    // ], 200);

    return response()->json([
        'success' => true,
        'data' => config('app.url'),
    ], 200);
});
