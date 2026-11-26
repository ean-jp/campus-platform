<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Resources\ResourceController;
use App\Controllers\Resources\BookingController;
use App\Controllers\Resources\ProviderController;
use App\Controllers\Resources\AvailabilityController;

return function (Router $router): void {

    // ==========================================
    // RESOURCE SERVICES
    // ==========================================

    $router->get(
        '/resources',
        [ResourceController::class, 'servicesPage']
    );

    $router->get(
        '/api/v1/resources/services',
        [ResourceController::class, 'services']
    );

    // ==========================================
    // SERVICE PROVIDERS
    // ==========================================

    $router->get(
        '/resources/services/{id}/providers',
        [ResourceController::class, 'providersPage']
    );

    $router->get(
        '/api/v1/resources/services/{id}/providers',
        [ResourceController::class, 'providers']
    );

    // ==========================================
    // AVAILABLE APPOINTMENT SLOTS
    // ==========================================

    $router->get(
        '/resources/providers/{id}/slots',
        [ResourceController::class, 'slotsPage']
    );

    $router->get(
        '/api/v1/resources/providers/{id}/slots',
        [ResourceController::class, 'slots']
    );

    // ==========================================
    // APPOINTMENT BOOKING
    // ==========================================

    $router->post(
        '/api/v1/resources/slots/{id}/book',
        [BookingController::class, 'book']
    );

    $router->post(
        '/api/v1/resources/bookings/{id}/cancel',
        [BookingController::class, 'cancel']
    );

    // ==========================================
    // MY BOOKINGS
    // ==========================================

    $router->get(
        '/resources/bookings',
        [BookingController::class, 'bookingsPage']
    );

    $router->get(
        '/api/v1/resources/bookings',
        [BookingController::class, 'bookings']
    );

    // ==========================================
    // PROVIDER DASHBOARD
    // ==========================================

    $router->get(
        '/resources/provider/dashboard',
        [ProviderController::class, 'dashboardPage']
    );

    $router->get(
        '/api/v1/resources/provider/dashboard',
        [ProviderController::class, 'dashboard']
    );

    // ==========================================
    // PROVIDER APPOINTMENT MANAGEMENT
    // ==========================================

    $router->post(
        '/api/v1/resources/provider/bookings/{id}/complete',
        [ProviderController::class, 'complete']
    );

    $router->post(
        '/api/v1/resources/provider/bookings/{id}/no-show',
        [ProviderController::class, 'noShow']
    );

    $router->post(
        '/api/v1/resources/provider/bookings/{id}/notes',
        [ProviderController::class, 'saveNotes']
    );

    // ==========================================
    // PROVIDER AVAILABILITY MANAGEMENT
    // ==========================================

    $router->post(
        '/api/v1/resources/provider/slots',
        [AvailabilityController::class, 'create']
    );

    $router->post(
        '/api/v1/resources/provider/slots/{id}/remove',
        [AvailabilityController::class, 'remove']
    );
};