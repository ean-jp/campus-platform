<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Response;
use App\Core\Router;

// Health check: open /api/v1/ping to confirm routing + database both work.
return function (Router $router): void {
    $router->get('/api/v1/ping', function () {
        Database::connection()->query('SELECT 1');
        Response::success(['pong' => true]);
    });
};
