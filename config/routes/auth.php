<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Core\Router;

// P1 - login, sign up, codes, profile
return function (Router $router): void {
    $router->get('/api/v1/auth/csrf',    [AuthController::class, 'csrf']);
    $router->post('/api/v1/auth/signup', [AuthController::class, 'signup']);
    $router->post('/api/v1/auth/verify', [AuthController::class, 'verifyEmail']);
    $router->post('/api/v1/auth/resend', [AuthController::class, 'resendCode']);
    $router->post('/api/v1/auth/login',  [AuthController::class, 'login']);
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);
    $router->get('/api/v1/auth/me',      [AuthController::class, 'me']);
};
