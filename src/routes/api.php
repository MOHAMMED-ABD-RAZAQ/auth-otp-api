<?php


use Illuminate\Support\Facades\Route;
use Ma\AuthOtpApi\Models\UserOtp;

Route::group([
    'namespace' => 'Ma\AuthOtpApi\Http\Controllers',
], function () {

    Route::group(['prefix' => 'api/auth', 'middleware' =>  'auth:api'], function () {

        Route::post('/signup/verify_otp', 'AuthController@verify');

        Route::get('/signup/resend_otp', 'AuthController@resend');

        Route::post('/change_password', 'AuthController@changePassword');

        Route::post('/onesignal/{player_id}', 'ProfileController@one_signal_subscribe');

        Route::post('/lang/{lang}', 'ProfileController@lang');

        Route::post('/logout', 'AuthController@logout');
    });


    Route::group(['prefix' => 'api/auth', 'middleware'   =>  'guest'], function () {

        Route::get('otps', function () {
            return UserOtp::all();
        });

        //Signup create new account.
        Route::post('signup', 'AuthController@signup');

        //Login used to generate access tokens.
        Route::post('login', 'AuthController@login');

        //Forgot user password.
        Route::post('/password/request', 'AuthController@requestPassword');

        //Verify otp code of forgot user password.
        Route::post('/password/verify_otp', 'AuthController@requestPasswordVerifyOtp');

        //Resend new otp token for forgot password.
        Route::post('/password/resend_otp', 'AuthController@requestPasswordResendOtp');
    });
});
