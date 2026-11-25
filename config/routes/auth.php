<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Core\Router;

/**
 * Person 1 routes. index.php loads every file in config/routes/ and calls the
 * function this file returns.
 *
 * ASSUMPTION: the Router has get() and post() taking (path, [Class, 'method']).
 * If Person 2's Router.php differs, only this file needs to change.
 */
return function (Router $router): void {
    $router->get('/api/v1/auth/csrf',    [AuthController::class, 'csrf']);
    $router->post('/api/v1/auth/signup', [AuthController::class, 'signup']);
    $router->post('/api/v1/auth/verify', [AuthController::class, 'verifyEmail']);
    $router->post('/api/v1/auth/resend', [AuthController::class, 'resendCode']);
    $router->post('/api/v1/auth/login',  [AuthController::class, 'login']);
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);
    $router->get('/api/v1/auth/me',      [AuthController::class, 'me']);
};
