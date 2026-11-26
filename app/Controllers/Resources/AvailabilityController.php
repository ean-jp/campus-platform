<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;
use App\Core\Csrf;
use App\Models\User;
use DateTimeImmutable;
use PDO;
use Throwable;

require_once BASE_PATH . '/app/Core/Session.php';
require_once BASE_PATH . '/app/Middleware/AuthMiddleware.php';

final class AvailabilityController
{
    // ==========================================
    // PROVIDER AUTHENTICATION
    // ==========================================

    private static function requireEligibleProvider(): int
    {
        $user = \requireProvider();

        $userId = (int) $user['id'];

        $account = User::findById($userId);

        if (!$account ||
            $account['status'] !== 'active' ||
            $account['role'] === 'admin' ||
            empty($account['email_verified_at'])) {

            Response::error('Provider access only', 403);
        }

        return $userId;
    }

    // ==========================================
    // REQUEST BODY AND CSRF PROTECTION
    // ==========================================

    private static function getProtectedBody(): array
    {
        $body = $_POST;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {

            $decoded = json_decode(
                file_get_contents('php://input'),
                true
            );

            if (!is_array($decoded)) {

                Response::error(
                    'Invalid JSON request body',
                    400
                );
            }

            $body = $decoded;
        }

        if (!Csrf::verify($body)) {

            Response::error(
                'Invalid CSRF token',
                403
            );
        }

        return $body;
    }

    // ==========================================
    // VALIDATE POSITIVE INTEGER ID
    // ==========================================

    private static function validateId($value): int
    {
        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$id) {

            Response::error('Invalid identifier', 400);
        }

        return (int) $id;
    }

    // ==========================================
    // VALIDATE APPOINTMENT DATE
    // ==========================================

    private static function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        return $parsed !== false &&
            $parsed->format('Y-m-d') === $date;
    }

    // ==========================================
    // VALIDATE APPOINTMENT TIME
    // ==========================================

    private static function normalizeTime(string $time): ?string
    {
        if (preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/D',
            $time
        )) {

            return $time . ':00';
        }

        if (preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/D',
            $time
        )) {

            return $time;
        }

        return null;
    }

    // ==========================================
    // LOCK OWNED PROVIDER PROFILE
    // ==========================================

    private static function lockProvider(
        PDO $db,
        int $providerId,
        int $userId
    ): bool {

        $stmt = $db->prepare(
            "SELECT p.id

             FROM providers p

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             WHERE p.id = ?
             AND p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1

             FOR UPDATE"
        );

        $stmt->execute([
            $providerId,
            $userId
        ]);

        return $stmt->fetchColumn() !== false;
    }

    // ==========================================
    // CREATE APPOINTMENT SLOT
    // ==========================================

    public static function create(array $params): void
    {
        $userId = self::requireEligibleProvider();

        $body = self::getProtectedBody();

        $providerId = self::validateId(
            $body['provider_id'] ?? null
        );

        if (!isset(
            $body['slot_date'],
            $body['start_time'],
            $body['end_time']
        ) ||
            !is_string($body['slot_date']) ||
            !is_string($body['start_time']) ||
            !is_string($body['end_time'])) {

            Response::error(
                'Date, start time and end time are required',
                400
            );
        }

        $date = trim($body['slot_date']);

        $startTime = self::normalizeTime(
            trim($body['start_time'])
        );

        $endTime = self::normalizeTime(
            trim($body['end_time'])
        );

        if (!self::validDate($date) ||
            $startTime === null ||
            $endTime === null) {

            Response::error(
                'Invalid appointment date or time',
                400
            );
        }

        if ($endTime <= $startTime) {

            Response::error(
                'End time must be after start time',
                400
            );
        }

        $db = Database::connection();

        try {

            $db->beginTransaction();

            if (!self::lockProvider(
                $db,
                $providerId,
                $userId
            )) {

                $db->rollBack();

                Response::error(
                    'Provider profile not found',
                    404
                );
            }

            // Reject appointments in the past.

            $stmt = $db->prepare(
                "SELECT TIMESTAMP(?, ?) > NOW()"
            );

            $stmt->execute([
                $date,
                $startTime
            ]);

            if ((int) $stmt->fetchColumn() !== 1) {

                $db->rollBack();

                Response::error(
                    'Appointment must be scheduled in the future',
                    400
                );
            }

            // Detect overlapping appointment slots.

            $stmt = $db->prepare(
                "SELECT id

                 FROM time_slots

                 WHERE provider_id = ?
                 AND slot_date = ?
                 AND start_time < ?
                 AND end_time > ?

                 LIMIT 1

                 FOR UPDATE"
            );

            $stmt->execute([
                $providerId,
                $date,
                $endTime,
                $startTime
            ]);

            if ($stmt->fetchColumn() !== false) {

                $db->rollBack();

                Response::error(
                    'Appointment overlaps an existing slot',
                    409
                );
            }

            // Insert the available appointment.

            $stmt = $db->prepare(
                "INSERT INTO time_slots (
                    provider_id,
                    slot_date,
                    start_time,
                    end_time,
                    is_booked
                )

                VALUES (?, ?, ?, ?, 0)"
            );

            $stmt->execute([
                $providerId,
                $date,
                $startTime,
                $endTime
            ]);

            $slotId = (int) $db->lastInsertId();

            $db->commit();

            Response::success([
                'message' => 'Appointment slot created successfully',
                'slot_id' => $slotId,
                'provider_id' => $providerId,
                'slot_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_booked' => 0
            ], 201);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }

            error_log(
                'Slot creation failed: ' .
                $e->getMessage()
            );

            Response::error(
                'Unable to create appointment slot',
                500
            );
        }
    }

    // ==========================================
    // REMOVE AVAILABLE APPOINTMENT SLOT
    // ==========================================

    public static function remove(array $params): void
    {
        $userId = self::requireEligibleProvider();

        self::getProtectedBody();

        $slotId = self::validateId(
            $params['id'] ?? null
        );

        $db = Database::connection();

        try {

            $db->beginTransaction();

            // Find the provider that owns the slot.

            $stmt = $db->prepare(
                "SELECT provider_id
                 FROM time_slots
                 WHERE id = ?"
            );

            $stmt->execute([$slotId]);

            $providerId = $stmt->fetchColumn();

            if ($providerId === false ||
                !self::lockProvider(
                    $db,
                    (int) $providerId,
                    $userId
                )) {

                $db->rollBack();

                Response::error(
                    'Appointment slot not found',
                    404
                );
            }

            // Lock the appointment slot.

            $stmt = $db->prepare(
                "SELECT
                    id,
                    is_booked,

                    CASE
                        WHEN TIMESTAMP(
                            slot_date,
                            start_time
                        ) > NOW()
                        THEN 1 ELSE 0
                    END AS is_future

                 FROM time_slots

                 WHERE id = ?
                 AND provider_id = ?

                 FOR UPDATE"
            );

            $stmt->execute([
                $slotId,
                $providerId
            ]);

            $slot = $stmt->fetch();

            if (!$slot) {

                $db->rollBack();

                Response::error(
                    'Appointment slot not found',
                    404
                );
            }

            // Booked slots must not be deleted.

            if ((int) $slot['is_booked'] === 1) {

                $db->rollBack();

                Response::error(
                    'Booked appointment slots cannot be removed',
                    409
                );
            }

            // Preserve past availability records.

            if ((int) $slot['is_future'] !== 1) {

                $db->rollBack();

                Response::error(
                    'Past appointment slots cannot be removed',
                    409
                );
            }

            // Preserve slots referenced by booking history.

            $stmt = $db->prepare(
                "SELECT id
                 FROM bookings
                 WHERE slot_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([$slotId]);

            if ($stmt->fetchColumn() !== false) {

                $db->rollBack();

                Response::error(
                    'Slots with booking history cannot be removed',
                    409
                );
            }

            // Delete the unused appointment slot.

            $stmt = $db->prepare(
                "DELETE FROM time_slots

                 WHERE id = ?
                 AND provider_id = ?
                 AND is_booked = 0"
            );

            $stmt->execute([
                $slotId,
                $providerId
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new \RuntimeException(
                    'Appointment slot removal failed'
                );
            }

            $db->commit();

            Response::success([
                'message' => 'Appointment slot removed successfully',
                'slot_id' => $slotId
            ], 200);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }

            error_log(
                'Slot removal failed: ' .
                $e->getMessage()
            );

            Response::error(
                'Unable to remove appointment slot',
                500
            );
        }
    }
}