<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;
use App\Core\Csrf;
use App\Models\User;
use Throwable;

require_once BASE_PATH . '/app/Core/Session.php';
require_once BASE_PATH . '/app/Middleware/AuthMiddleware.php';

final class ProviderController
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

    private static function getProviderProfiles(int $userId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                p.id,
                p.user_id,
                p.name,
                p.bio,
                p.service_id,
                s.name AS service_name

             FROM providers p

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             WHERE p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1

             ORDER BY p.id"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    // ==========================================
    // DASHBOARD STATISTICS
    // ==========================================

    private static function getStatistics(int $userId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                COUNT(b.id) AS total_bookings,

                COALESCE(SUM(
                    CASE
                        WHEN b.status = 'confirmed'
                        AND TIMESTAMP(t.slot_date, t.start_time) > NOW()
                        THEN 1 ELSE 0
                    END
                ), 0) AS upcoming_bookings,

                COALESCE(SUM(
                    CASE
                        WHEN b.status = 'completed'
                        THEN 1 ELSE 0
                    END
                ), 0) AS completed_bookings,

                COALESCE(SUM(
                    CASE
                        WHEN b.status = 'cancelled'
                        THEN 1 ELSE 0
                    END
                ), 0) AS cancelled_bookings,

                COALESCE(SUM(
                    CASE
                        WHEN b.status = 'no_show'
                        THEN 1 ELSE 0
                    END
                ), 0) AS no_show_bookings,

                COALESCE(SUM(
                    CASE
                        WHEN b.status = 'confirmed'
                        AND TIMESTAMP(t.slot_date, t.end_time) <= NOW()
                        THEN 1 ELSE 0
                    END
                ), 0) AS pending_review

             FROM bookings b

             INNER JOIN time_slots t
                 ON b.slot_id = t.id

             INNER JOIN providers p
                 ON t.provider_id = p.id

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             WHERE p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1"
        );

        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetch());
    }

    // ==========================================
    // PROVIDER APPOINTMENTS
    // ==========================================

    private static function getAppointments(int $userId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                b.id AS booking_id,
                b.status,
                b.created_at,
                b.cancelled_at,
                b.provider_notes,

                t.id AS slot_id,
                t.slot_date,
                t.start_time,
                t.end_time,

                u.full_name AS student_name,
                u.email AS student_email,

                p.id AS provider_id,
                p.name AS provider_name,

                s.name AS service_name,

                CASE
                    WHEN b.status = 'confirmed'
                    AND TIMESTAMP(t.slot_date, t.start_time) > NOW()
                    THEN 1 ELSE 0
                END AS is_upcoming,

                CASE
                    WHEN b.status = 'confirmed'
                    AND TIMESTAMP(t.slot_date, t.end_time) <= NOW()
                    THEN 1 ELSE 0
                END AS can_review

             FROM bookings b

             INNER JOIN time_slots t
                 ON b.slot_id = t.id

             INNER JOIN providers p
                 ON t.provider_id = p.id

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             INNER JOIN users u
                 ON b.user_id = u.id

             WHERE p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1

             ORDER BY
                 t.slot_date ASC,
                 t.start_time ASC,
                 b.id ASC"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    // ==========================================
    // PROVIDER AVAILABILITY
    // ==========================================

    private static function getAvailability(int $userId): array
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                t.id AS slot_id,
                t.provider_id,
                t.slot_date,
                t.start_time,
                t.end_time,
                t.is_booked,

                p.name AS provider_name,

                s.name AS service_name

             FROM time_slots t

             INNER JOIN providers p
                 ON t.provider_id = p.id

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             WHERE p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1

             AND TIMESTAMP(t.slot_date, t.start_time) > NOW()

             ORDER BY
                 t.slot_date ASC,
                 t.start_time ASC"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    // ==========================================
    // COMBINE DASHBOARD DATA
    // ==========================================

    private static function getDashboardData(int $userId): array
    {
        $profiles = self::getProviderProfiles($userId);

        $statistics = self::getStatistics($userId);

        $appointments = self::getAppointments($userId);

        $availability = self::getAvailability($userId);

        $upcomingAppointments = [];
        $pendingReview = [];
        $appointmentHistory = [];

        foreach ($appointments as $appointment) {

            if ((int) $appointment['is_upcoming'] === 1) {

                $upcomingAppointments[] = $appointment;

            } elseif ((int) $appointment['can_review'] === 1) {

                $pendingReview[] = $appointment;

            } else {

                $appointmentHistory[] = $appointment;
            }
        }

        return [
            'profiles' => $profiles,
            'statistics' => $statistics,
            'upcoming_appointments' => $upcomingAppointments,
            'pending_review' => $pendingReview,
            'appointment_history' => array_reverse(
                $appointmentHistory
            ),
            'availability' => $availability
        ];
    }

    // ==========================================
    // PROVIDER DASHBOARD API
    // ==========================================

    public static function dashboard(array $params): void
    {
        $userId = self::requireEligibleProvider();

        $data = self::getDashboardData($userId);

        if (empty($data['profiles'])) {

            Response::error(
                'No active provider profile found',
                403
            );
        }

        header('Cache-Control: no-store');

        Response::success($data, 200);
    }

    // ==========================================
    // PROVIDER DASHBOARD PAGE
    // ==========================================

    public static function dashboardPage(array $params): void
    {
        $user = \Session::user();

        if (!$user || empty($user['id'])) {

            header(
                'Location: /campus-platform-fused/public/login.html?next=/resources/provider/dashboard'
            );

            exit;
        }

        $userId = (int) $user['id'];

        $account = User::findById($userId);

        if (!$account || $account['status'] !== 'active') {

            \Session::destroy();

            header(
                'Location: /campus-platform-fused/public/login.html?next=/resources/provider/dashboard'
            );

            exit;
        }

        if (!User::isProvider($userId) ||
            $account['role'] === 'admin' ||
            empty($account['email_verified_at'])) {

            http_response_code(403);

            echo 'Provider access only.';

            return;
        }

        $dashboard = self::getDashboardData($userId);

        if (empty($dashboard['profiles'])) {

            http_response_code(403);

            echo 'No active provider profile found.';

            return;
        }

        $profiles = $dashboard['profiles'];

        $statistics = $dashboard['statistics'];

        $upcomingAppointments =
            $dashboard['upcoming_appointments'];

        $pendingReview =
            $dashboard['pending_review'];

        $appointmentHistory =
            $dashboard['appointment_history'];

        $availability =
            $dashboard['availability'];

        $csrfToken = Csrf::token();

        header('Cache-Control: no-store');

        require BASE_PATH .
            '/app/Views/Resources/provider-dashboard.php';
    }

    // ==========================================
    // REQUEST BODY AND CSRF VALIDATION
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
    // VALIDATE BOOKING ID
    // ==========================================

    private static function getBookingId(array $params): int
    {
        $bookingId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$bookingId) {

            Response::error(
                'Invalid booking ID',
                400
            );
        }

        return (int) $bookingId;
    }

    // ==========================================
    // RETRIEVE AND LOCK OWNED APPOINTMENT
    // ==========================================

    private static function getOwnedBooking(
        \PDO $db,
        int $bookingId,
        int $userId
    ): ?array {

        $stmt = $db->prepare(
            "SELECT
                b.id,
                b.status,
                b.slot_id,
                b.provider_notes,

                CASE
                    WHEN TIMESTAMP(
                        t.slot_date,
                        t.end_time
                    ) <= NOW()
                    THEN 1 ELSE 0
                END AS appointment_ended

             FROM bookings b

             INNER JOIN time_slots t
                 ON b.slot_id = t.id

             INNER JOIN providers p
                 ON t.provider_id = p.id

             INNER JOIN resource_services s
                 ON p.service_id = s.id

             WHERE b.id = ?
             AND p.user_id = ?
             AND p.is_active = 1
             AND s.is_active = 1

             FOR UPDATE"
        );

        $stmt->execute([
            $bookingId,
            $userId
        ]);

        return $stmt->fetch() ?: null;
    }

    // ==========================================
    // UPDATE APPOINTMENT STATUS
    // ==========================================

    private static function updateAppointmentStatus(
        array $params,
        string $newStatus
    ): void {

        $userId = self::requireEligibleProvider();

        self::getProtectedBody();

        $bookingId = self::getBookingId($params);

        if (!in_array(
            $newStatus,
            ['completed', 'no_show'],
            true
        )) {

            Response::error(
                'Invalid appointment status',
                400
            );
        }

        $db = Database::connection();

        try {

            $db->beginTransaction();

            $booking = self::getOwnedBooking(
                $db,
                $bookingId,
                $userId
            );

            if (!$booking) {

                $db->rollBack();

                Response::error(
                    'Booking not found',
                    404
                );
            }

            if ($booking['status'] !== 'confirmed') {

                $db->rollBack();

                Response::error(
                    'Only confirmed appointments can be reviewed',
                    409
                );
            }

            if ((int) $booking['appointment_ended'] !== 1) {

                $db->rollBack();

                Response::error(
                    'The appointment has not ended yet',
                    409
                );
            }

            $stmt = $db->prepare(
                "UPDATE bookings
                 SET status = ?
                 WHERE id = ?
                 AND status = 'confirmed'"
            );

            $stmt->execute([
                $newStatus,
                $bookingId
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new \RuntimeException(
                    'Appointment status update failed'
                );
            }

            $db->commit();

            Response::success([
                'message' => $newStatus === 'completed'
                    ? 'Appointment marked as completed'
                    : 'Appointment marked as no-show',

                'booking_id' => $bookingId,

                'status' => $newStatus

            ], 200);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }

            error_log(
                'Appointment status update failed: ' .
                $e->getMessage()
            );

            Response::error(
                'Unable to update appointment status',
                500
            );
        }
    }

    // ==========================================
    // MARK APPOINTMENT AS COMPLETED
    // ==========================================

    public static function complete(array $params): void
    {
        self::updateAppointmentStatus(
            $params,
            'completed'
        );
    }

    // ==========================================
    // MARK APPOINTMENT AS NO-SHOW
    // ==========================================

    public static function noShow(array $params): void
    {
        self::updateAppointmentStatus(
            $params,
            'no_show'
        );
    }

    // ==========================================
    // SAVE PRIVATE PROVIDER NOTES
    // ==========================================

    public static function saveNotes(array $params): void
    {
        $userId = self::requireEligibleProvider();

        $body = self::getProtectedBody();

        $bookingId = self::getBookingId($params);

        if (!isset($body['notes']) ||
            !is_string($body['notes'])) {

            Response::error(
                'Notes must be provided as text',
                400
            );
        }

        $notes = trim($body['notes']);

        if (strlen($notes) > 10000) {

            Response::error(
                'Provider notes exceed the permitted length',
                400
            );
        }

        $db = Database::connection();

        try {

            $db->beginTransaction();

            $booking = self::getOwnedBooking(
                $db,
                $bookingId,
                $userId
            );

            if (!$booking) {

                $db->rollBack();

                Response::error(
                    'Booking not found',
                    404
                );
            }

            if ($booking['status'] === 'cancelled') {

                $db->rollBack();

                Response::error(
                    'Notes cannot be added to cancelled appointments',
                    409
                );
            }

            $stmt = $db->prepare(
                "UPDATE bookings
                 SET provider_notes = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $notes === '' ? null : $notes,
                $bookingId
            ]);

            $db->commit();

            Response::success([
                'message' => 'Provider notes saved successfully',
                'booking_id' => $bookingId
            ], 200);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }

            error_log(
                'Provider notes could not be saved'
            );

            Response::error(
                'Unable to save provider notes',
                500
            );
        }
    }
}