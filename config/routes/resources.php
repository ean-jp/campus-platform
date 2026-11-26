<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Resources\ResourceController;
use App\Controllers\Resources\BookingController;

return function (Router $router): void {

    // Resource services
    $router->get(
        '/resources',
        [ResourceController::class, 'servicesPage']
    );

    $router->get(
        '/api/v1/resources/services',
        [ResourceController::class, 'services']
    );

    // Service providers
    $router->get(
        '/resources/services/{id}/providers',
        [ResourceController::class, 'providersPage']
    );

    $router->get(
        '/api/v1/resources/services/{id}/providers',
        [ResourceController::class, 'providers']
    );

    // Available appointment slots
    $router->get(
        '/resources/providers/{id}/slots',
        [ResourceController::class, 'slotsPage']
    );

    $router->get(
        '/api/v1/resources/providers/{id}/slots',
        [ResourceController::class, 'slots']
    );

    // Book an appointment
    $router->post(
        '/api/v1/resources/slots/{id}/book',
        [BookingController::class, 'book']
    );

    // Cancel an appointment
    $router->post(
        '/api/v1/resources/bookings/{id}/cancel',
        [BookingController::class, 'cancel']
    );

    // My Bookings page
    $router->get(
        '/resources/bookings',
        [BookingController::class, 'bookingsPage']
    );

    // My Bookings API
    $router->get(
        '/api/v1/resources/bookings',
        [BookingController::class, 'bookings']
    );
};