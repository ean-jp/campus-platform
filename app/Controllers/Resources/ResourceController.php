<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;
use App\Models\User;

require_once BASE_PATH . '/app/Core/Session.php';
require_once BASE_PATH . '/app/Middleware/AuthMiddleware.php';

final class ResourceController
{
    // ==========================================
    // API ACCESS PROTECTION
    // ==========================================

    private static function requireEligibleUser(): void
    {
        $user = \requireAshesi();

        $account = User::findById((int) $user['id']);

        if (
            !$account ||
            $account['status'] !== 'active' ||
            $account['account_type'] !== 'ashesi' ||
            empty($account['email_verified_at'])
        ) {
            Response::error(
                'Campus Resources is available to verified Ashesi accounts only',
                403
            );
        }
    }

    // ==========================================
    // PAGE ACCESS PROTECTION
    // ==========================================

    private static function requireEligiblePageUser(
        string $next
    ): void {

        $user = \Session::user();

        if (!$user || empty($user['id'])) {

            header(
                'Location: /login.html?next=' .
                rawurlencode($next)
            );

            exit;
        }

        $account = User::findById((int) $user['id']);

        if (!$account || $account['status'] !== 'active') {

            \Session::destroy();

            header(
                'Location: /login.html?next=' .
                rawurlencode($next)
            );

            exit;
        }

        if (
            $account['account_type'] !== 'ashesi' ||
            empty($account['email_verified_at'])
        ) {

            http_response_code(403);

            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');

            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport"
                      content="width=device-width, initial-scale=1.0">
                <title>Access Restricted</title>
            </head>
            <body>
                <h1>Access Restricted</h1>
                <p>Campus Resources is available to verified
                   Ashesi accounts only.</p>
                <p>Please link or use a verified Ashesi email
                   to access this feature.</p>
                <a href="/login.html">Return to Login</a>
            </body>
            </html>';

            exit;
        }

        header('Cache-Control: no-store');
    }

    // ==========================================
    // SERVICES
    // ==========================================

    private static function getServices(): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT id, name, description
             FROM resource_services
             WHERE is_active = 1
             ORDER BY id"
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function services(array $params): void
    {
        self::requireEligibleUser();

        Response::success(self::getServices());
    }

    public static function servicesPage(array $params): void
    {
        self::requireEligiblePageUser('/resources');

        $services = self::getServices();

        require BASE_PATH . '/app/Views/Resources/services.php';
    }

    // ==========================================
    // PROVIDERS
    // ==========================================

    private static function getService(int $serviceId): ?array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT id, name, description
             FROM resource_services
             WHERE id = ?
             AND is_active = 1"
        );

        $stmt->execute([$serviceId]);

        $service = $stmt->fetch();

        return $service ?: null;
    }

    private static function getProviders(int $serviceId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT id, service_id, name, bio
             FROM providers
             WHERE service_id = ?
             AND is_active = 1
             ORDER BY name"
        );

        $stmt->execute([$serviceId]);

        return $stmt->fetchAll();
    }

    public static function providers(array $params): void
    {
        self::requireEligibleUser();

        $serviceId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$serviceId) {
            Response::error('Invalid service ID', 400);
        }

        $service = self::getService($serviceId);

        if ($service === null) {
            Response::error('Service not found', 404);
        }

        Response::success(
            self::getProviders($serviceId)
        );
    }

    public static function providersPage(array $params): void
    {
        $serviceId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$serviceId) {
            http_response_code(400);
            echo 'Invalid service ID';
            return;
        }

        self::requireEligiblePageUser(
            '/resources/services/' .
            $serviceId .
            '/providers'
        );

        $service = self::getService($serviceId);

        if ($service === null) {
            http_response_code(404);
            echo 'Service not found';
            return;
        }

        $providers = self::getProviders($serviceId);

        $selectedService = $service['name'];
        $filteredProviders = $providers;

        foreach ($filteredProviders as &$provider) {
            $provider['service'] = $selectedService;
        }

        unset($provider);

        require BASE_PATH .
            '/app/Views/Resources/providers.php';
    }

    // ==========================================
    // APPOINTMENT SLOTS
    // ==========================================

    private static function getProvider(int $providerId): ?array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.bio, p.service_id,
                    s.name AS service_name
             FROM providers p
             INNER JOIN resource_services s
                 ON p.service_id = s.id
             WHERE p.id = ?
             AND p.is_active = 1
             AND s.is_active = 1"
        );

        $stmt->execute([$providerId]);

        $provider = $stmt->fetch();

        return $provider ?: null;
    }

    private static function getAvailableSlots(
        int $providerId
    ): array {

        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT id, provider_id, slot_date,
                    start_time, end_time
             FROM time_slots
             WHERE provider_id = ?
             AND is_booked = 0
             AND TIMESTAMP(slot_date, start_time) > NOW()
             ORDER BY slot_date, start_time"
        );

        $stmt->execute([$providerId]);

        return $stmt->fetchAll();
    }

    public static function slots(array $params): void
    {
        self::requireEligibleUser();

        $providerId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$providerId) {
            Response::error('Invalid provider ID', 400);
        }

        $provider = self::getProvider($providerId);

        if ($provider === null) {
            Response::error('Provider not found', 404);
        }

        Response::success(
            self::getAvailableSlots($providerId)
        );
    }

    public static function slotsPage(array $params): void
    {
        $providerId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$providerId) {
            http_response_code(400);
            echo 'Invalid provider ID';
            return;
        }

        self::requireEligiblePageUser(
            '/resources/providers/' .
            $providerId .
            '/slots'
        );

        $provider = self::getProvider($providerId);

        if ($provider === null) {
            http_response_code(404);
            echo 'Provider not found';
            return;
        }

        $availableSlots = self::getAvailableSlots(
            $providerId
        );

        $groupedSlots = [];

        foreach ($availableSlots as $slot) {
            $groupedSlots[$slot['slot_date']][] = $slot;
        }

        require BASE_PATH .
            '/app/Views/Resources/slots.php';
    }
}