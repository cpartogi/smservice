<?php

ini_set('display_errors', 'On');

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::group(['prefix' => '/sms'], function() {
	Route::post('/send', 'SmsController@send');
	Route::post('/dr', 'SmsController@delivery_report');
	Route::post('/send/tw', 'SmsController@send_twilio');
	Route::post('/send/my', 'SmsController@send_twilio_my');
	Route::post('/send/infobip', 'SmsController@infobip');
	Route::any('/send/nexmo', 'SmsController@nexmo');
    Route::post('/send/cn', 'SmsController@send_twilio_cn');
    Route::post('/send/isentricmy', 'SmsController@isentricmy');    
});
