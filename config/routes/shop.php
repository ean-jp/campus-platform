<?php
declare(strict_types=1);

use App\Controllers\Marketplace\CartController;
use App\Controllers\Marketplace\CatalogController;
use App\Controllers\Marketplace\CheckoutController;
use App\Controllers\Marketplace\OrderController;
use App\Controllers\Marketplace\ReportController;
use App\Controllers\Marketplace\ReviewController;
use App\Core\Router;

// P3 - catalog, cart, checkout, orders
// Public: categories, items, vendors.  Login required: cart, checkout, orders, reports.
return function (Router $router): void {
    // catalogue (no login needed)
    $router->get('/api/v1/categories',   [CatalogController::class, 'categories']);
    $router->get('/api/v1/items',        [CatalogController::class, 'items']);
    $router->get('/api/v1/items/{id}',   [CatalogController::class, 'item']);
    $router->get('/api/v1/vendors',      [CatalogController::class, 'vendors']);
    $router->get('/api/v1/vendors/{id}', [CatalogController::class, 'vendor']);

    // cart
    $router->get('/api/v1/cart',               [CartController::class, 'show']);
    $router->post('/api/v1/cart/items',        [CartController::class, 'add']);
    $router->patch('/api/v1/cart/items/{id}',  [CartController::class, 'update']);
    $router->delete('/api/v1/cart/items/{id}', [CartController::class, 'remove']);

    // checkout + orders
    $router->post('/api/v1/checkout',            [CheckoutController::class, 'place']);
    $router->get('/api/v1/orders',               [OrderController::class, 'index']);
    $router->get('/api/v1/orders/{id}',          [OrderController::class, 'show']);
    $router->post('/api/v1/orders/{id}/cancel',  [OrderController::class, 'cancel']);

    // reports & reviews
    $router->post('/api/v1/reports', [ReportController::class, 'create']);
    $router->post('/api/v1/reviews', [ReviewController::class, 'create']);
};
