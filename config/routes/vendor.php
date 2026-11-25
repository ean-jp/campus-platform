<?php
declare(strict_types=1);

use App\Controllers\Vendor\ItemController;
use App\Controllers\Vendor\OrderController;
use App\Controllers\Vendor\VendorController;
use App\Core\Router;

// P4 - vendor dashboard, products, vendor orders
// Only edit your own file to avoid merge conflicts.
//
// Security is inside the controllers (VendorBase): every route checks that the
// person is logged in and owns an APPROVED shop, and every POST/PATCH/DELETE
// checks the CSRF token. Each query is limited to that vendor's own rows.
return function (Router $router): void {
    // account
    $router->get('/api/v1/vendor/me',         [VendorController::class, 'me']);
    $router->post('/api/v1/vendor/apply',     [VendorController::class, 'apply']);
    $router->get('/api/v1/vendor/stats',      [VendorController::class, 'stats']);
    $router->get('/api/v1/vendor/categories', [VendorController::class, 'categories']);

    // products
    $router->get('/api/v1/vendor/items',                         [ItemController::class, 'index']);
    $router->post('/api/v1/vendor/items',                        [ItemController::class, 'store']);
    $router->get('/api/v1/vendor/items/{id}',                    [ItemController::class, 'show']);
    $router->patch('/api/v1/vendor/items/{id}',                  [ItemController::class, 'update']);
    $router->delete('/api/v1/vendor/items/{id}',                 [ItemController::class, 'destroy']);
    $router->post('/api/v1/vendor/items/{id}/images',            [ItemController::class, 'uploadImages']);
    $router->delete('/api/v1/vendor/items/{id}/images/{imageId}', [ItemController::class, 'deleteImage']);

    // orders
    $router->get('/api/v1/vendor/orders',         [OrderController::class, 'index']);
    $router->patch('/api/v1/vendor/orders/{id}',  [OrderController::class, 'update']);
};
