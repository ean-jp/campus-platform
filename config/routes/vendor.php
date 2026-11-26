<?php
declare(strict_types=1);

use App\Controllers\Vendor\ItemController;
use App\Controllers\Vendor\NotificationController;
use App\Controllers\Vendor\OrderController;
use App\Controllers\Vendor\ShopController;
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

    // Stage 3: shop profile + sales report
    $router->get('/api/v1/vendor/profile',        [ShopController::class, 'profile']);
    $router->patch('/api/v1/vendor/profile',      [ShopController::class, 'updateProfile']);
    $router->post('/api/v1/vendor/profile/logo',  [ShopController::class, 'uploadLogo']);
    $router->get('/api/v1/vendor/sales',          [ShopController::class, 'sales']);

    // Stage 3: notifications for ANY logged-in user (the bell on every page)
    $router->get('/api/v1/notifications',              [NotificationController::class, 'index']);
    $router->patch('/api/v1/notifications/{id}/read',  [NotificationController::class, 'markRead']);
    $router->post('/api/v1/notifications/read-all',    [NotificationController::class, 'markAllRead']);
};
