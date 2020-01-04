<?php

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

/* @var \Dingo\Api\Routing\Router $api */
$api = app(\Dingo\Api\Routing\Router::class);

$api->group([
    'version' => 'v1',
    'middleware' => 'api.auth',
    'scopes' => ['read_user_data', 'write_user_data'],
], function (\Dingo\Api\Routing\Router $api) {

    $api->get('/', function () {
        return response()->json([
            'message' => 'Hello World!',
            'user' => app('Dingo\Api\Auth\Auth')->user()
        ]);
    });

});