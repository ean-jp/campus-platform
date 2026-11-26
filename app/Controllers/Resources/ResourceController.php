<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;

final class ResourceController
{
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
        Response::success(self::getServices());
    }

    public static function servicesPage(array $params): void
    {
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

        require BASE_PATH . '/app/Views/Resources/providers.php';
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

    private static function getAvailableSlots(int $providerId): array
    {
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

        $provider = self::getProvider($providerId);

        if ($provider === null) {
            http_response_code(404);
            echo 'Provider not found';
            return;
        }

        $availableSlots = self::getAvailableSlots($providerId);

        $groupedSlots = [];

        foreach ($availableSlots as $slot) {
            $groupedSlots[$slot['slot_date']][] = $slot;
        }

        require BASE_PATH . '/app/Views/Resources/slots.php';
    }
}