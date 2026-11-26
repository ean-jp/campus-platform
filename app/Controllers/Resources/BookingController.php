<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;
use App\Core\Csrf;
use App\Models\User;
use App\Services\BookingMailService;
use Throwable;

require_once BASE_PATH . '/app/Core/Session.php';
require_once BASE_PATH . '/app/Middleware/AuthMiddleware.php';

final class BookingController
{
    // ==========================================
    // CSRF VALIDATION
    // ==========================================

    private static function validateCsrf(): void
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
    }

    // ==========================================
    // USER ELIGIBILITY
    // ==========================================

    private static function requireEligibleUser(): int
    {
        $user = \requireAshesi();

        $userId = (int) $user['id'];

        $account = User::findById($userId);

        if (!$account) {
            Response::error(
                'User account not found',
                401
            );
        }

        if (
            $account['account_type'] !== 'ashesi' ||
            $account['status'] !== 'active' ||
            empty($account['email_verified_at'])
        ) {
            Response::error(
                'You are not eligible to manage appointments',
                403
            );
        }

        return $userId;
    }

    // ==========================================
    // BOOK AN APPOINTMENT
    // ==========================================

    public static function book(array $params): void
    {
        $userId = self::requireEligibleUser();

        self::validateCsrf();

        $slotId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$slotId) {
            Response::error(
                'Invalid appointment slot ID',
                400
            );
        }

        $db = Database::connection();

        try {

            $db->beginTransaction();

            // Reserve the slot only if it is still available.
            $stmt = $db->prepare(
                "UPDATE time_slots AS t
                 INNER JOIN providers AS p
                     ON t.provider_id = p.id
                 INNER JOIN resource_services AS s
                     ON p.service_id = s.id
                 SET t.is_booked = 1
                 WHERE t.id = ?
                 AND t.is_booked = 0
                 AND p.is_active = 1
                 AND s.is_active = 1
                 AND TIMESTAMP(
                     t.slot_date,
                     t.start_time
                 ) > NOW()"
            );

            $stmt->execute([$slotId]);

            // Prevent double booking.
            if ($stmt->rowCount() !== 1) {

                $db->rollBack();

                Response::error(
                    'This appointment is no longer available',
                    409
                );
            }

            // Create the booking record.
            $stmt = $db->prepare(
                "INSERT INTO bookings (
                    user_id,
                    slot_id,
                    status
                 )
                 VALUES (?, ?, 'confirmed')"
            );

            $stmt->execute([
                $userId,
                $slotId
            ]);

            $bookingId = (int) $db->lastInsertId();

            // Permanently save the booking.
            $db->commit();

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Booking failed: ' . $e->getMessage()
            );

            Response::error(
                'Unable to complete appointment booking',
                500
            );

            return;
        }

        // ==========================================
        // SEND BOOKING CONFIRMATION EMAIL
        // ==========================================

        // Email delivery happens after the transaction
        // has been successfully committed.

        $emailSent = false;

        try {

            $emailSent =
                BookingMailService::sendConfirmation(
                    $bookingId
                );

        } catch (Throwable $mailError) {

            error_log(
                'Booking confirmation email failed: '
                . $mailError->getMessage()
            );
        }

        // Email failure must not cancel the booking.
        if (!$emailSent) {

            error_log(
                'Confirmation email not sent for booking '
                . $bookingId
            );
        }

        // Return the successful booking response.
        Response::success([
            'message' => 'Appointment booked successfully',
            'booking_id' => $bookingId,
            'slot_id' => $slotId,
            'status' => 'confirmed',
            'email_sent' => $emailSent
        ], 201);
    }

    // ==========================================
    // CANCEL AN APPOINTMENT
    // ==========================================

    public static function cancel(array $params): void
    {
        $userId = self::requireEligibleUser();

        self::validateCsrf();

        // Validate the booking ID.
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

        $db = Database::connection();

        try {

            $db->beginTransaction();

            // Retrieve and lock the user's booking.
            $stmt = $db->prepare(
                "SELECT
                    b.id,
                    b.slot_id,
                    b.status,
                    TIMESTAMP(
                        t.slot_date,
                        t.start_time
                    ) > NOW() AS is_future
                 FROM bookings AS b
                 INNER JOIN time_slots AS t
                     ON b.slot_id = t.id
                 WHERE b.id = ?
                 AND b.user_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([
                $bookingId,
                $userId
            ]);

            $booking = $stmt->fetch();

            // Verify booking ownership.
            if (!$booking) {

                $db->rollBack();

                Response::error(
                    'Booking not found',
                    404
                );
            }

            // Only confirmed appointments can be cancelled.
            if ($booking['status'] !== 'confirmed') {

                $db->rollBack();

                Response::error(
                    'Only confirmed appointments can be cancelled',
                    409
                );
            }

            // Prevent cancellation of past appointments.
            if (!(int) $booking['is_future']) {

                $db->rollBack();

                Response::error(
                    'Past appointments cannot be cancelled',
                    409
                );
            }

            // Update the booking status and cancellation time.
            $stmt = $db->prepare(
                "UPDATE bookings
                 SET status = 'cancelled',
                     cancelled_at = NOW()
                 WHERE id = ?
                 AND user_id = ?
                 AND status = 'confirmed'"
            );

            $stmt->execute([
                $bookingId,
                $userId
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new \RuntimeException(
                    'Booking cancellation failed'
                );
            }

            // Make the appointment slot available again.
            $stmt = $db->prepare(
                "UPDATE time_slots
                 SET is_booked = 0
                 WHERE id = ?
                 AND is_booked = 1"
            );

            $stmt->execute([
                $booking['slot_id']
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new \RuntimeException(
                    'Unable to release appointment slot'
                );
            }

            // Commit both database changes.
            $db->commit();

            Response::success([
                'message' =>
                    'Appointment cancelled successfully',

                'booking_id' => $bookingId,

                'slot_id' =>
                    (int) $booking['slot_id'],

                'status' => 'cancelled'
            ], 200);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Cancellation failed: '
                . $e->getMessage()
            );

            Response::error(
                'Unable to cancel appointment',
                500
            );
        }
    }

    // ==========================================
    // RETRIEVE USER BOOKINGS
    // ==========================================

    private static function getUserBookings(
        int $userId
    ): array {

        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                b.id AS booking_id,
                b.slot_id,
                b.status,
                b.created_at,
                b.cancelled_at,
                t.slot_date,
                t.start_time,
                t.end_time,
                p.name AS provider_name,
                s.name AS service_name,

                CASE
                    WHEN b.status = 'confirmed'
                    AND TIMESTAMP(
                        t.slot_date,
                        t.start_time
                    ) > NOW()
                    THEN 1
                    ELSE 0
                END AS can_cancel

             FROM bookings AS b

             INNER JOIN time_slots AS t
                 ON b.slot_id = t.id

             INNER JOIN providers AS p
                 ON t.provider_id = p.id

             INNER JOIN resource_services AS s
                 ON p.service_id = s.id

             WHERE b.user_id = ?

             ORDER BY
                 t.slot_date DESC,
                 t.start_time DESC"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    // ==========================================
    // MY BOOKINGS API
    // ==========================================

    public static function bookings(
        array $params
    ): void {

        $user = \requireLogin();

        $userId = (int) $user['id'];

        $bookings = self::getUserBookings(
            $userId
        );

        Response::success(
            $bookings,
            200
        );
    }

    // ==========================================
    // MY BOOKINGS PAGE
    // ==========================================

    public static function bookingsPage(
        array $params
    ): void {

        $user = \Session::user();

        // Redirect unauthenticated users.
        if (!$user || empty($user['id'])) {

            header(
                'Location: /login.html?next=/resources/bookings'
            );

            exit;
        }

        // Verify that the account is still active.
        $account = User::findById(
            (int) $user['id']
        );

        if (
            !$account ||
            $account['status'] !== 'active'
        ) {

            \Session::destroy();

            header(
                'Location: /login.html?next=/resources/bookings'
            );

            exit;
        }

        $userId = (int) $user['id'];

        $bookings = self::getUserBookings(
            $userId
        );

        $upcomingBookings = [];
        $bookingHistory = [];

        // Separate upcoming appointments from history.
        foreach ($bookings as $booking) {

            if ((int) $booking['can_cancel'] === 1) {

                $upcomingBookings[] = $booking;

            } else {

                $bookingHistory[] = $booking;
            }
        }

        // Display upcoming appointments chronologically.
        $upcomingBookings = array_reverse(
            $upcomingBookings
        );

        // Generate a CSRF token for the booking interface.
        $csrfToken = Csrf::token();

        // Prevent caching of private booking information.
        header('Cache-Control: no-store');

        // Load the My Bookings view.
        require BASE_PATH
            . '/app/Views/Resources/bookings.php';
    }
}