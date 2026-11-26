<?php

declare(strict_types=1);

namespace App\Controllers\Resources;

use App\Core\Database;
use App\Core\Response;
use Throwable;

require_once BASE_PATH . '/app/Core/Session.php';

final class BookingController
{
    // Book an appointment.
    public static function book(array $params): void
    {
        $user = \Session::user();

        if (!$user || empty($user['id'])) {
            Response::error(
                'You must log in to book an appointment',
                401
            );
        }

        $userId = (int) $user['id'];

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

        // Verify the user's eligibility.
        $stmt = $db->prepare(
            "SELECT id, account_type, status, email_verified_at
             FROM users
             WHERE id = ?"
        );

        $stmt->execute([$userId]);

        $account = $stmt->fetch();

        if (!$account) {
            Response::error('User account not found', 401);
        }

        if (
            $account['account_type'] !== 'ashesi' ||
            $account['status'] !== 'active' ||
            empty($account['email_verified_at'])
        ) {
            Response::error(
                'You are not eligible to book appointments',
                403
            );
        }

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
                 AND TIMESTAMP(t.slot_date, t.start_time) > NOW()"
            );

            $stmt->execute([$slotId]);

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

            $stmt->execute([$userId, $slotId]);

            $bookingId = (int) $db->lastInsertId();

            $db->commit();

            Response::success([
                'message' => 'Appointment booked successfully',
                'booking_id' => $bookingId,
                'slot_id' => $slotId,
                'status' => 'confirmed'
            ], 201);

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
        }
    }

    // Cancel an existing appointment.
    public static function cancel(array $params): void
    {
        $user = \Session::user();

        if (!$user || empty($user['id'])) {
            Response::error(
                'You must log in to cancel an appointment',
                401
            );
        }

        $userId = (int) $user['id'];

        // Validate the booking ID.
        $bookingId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$bookingId) {
            Response::error('Invalid booking ID', 400);
        }

        $db = Database::connection();

        try {
            $db->beginTransaction();

            // Retrieve and lock the user's booking.
            $stmt = $db->prepare(
                "SELECT b.id,
                        b.slot_id,
                        b.status,
                        TIMESTAMP(t.slot_date, t.start_time) > NOW()
                            AS is_future
                 FROM bookings AS b
                 INNER JOIN time_slots AS t
                     ON b.slot_id = t.id
                 WHERE b.id = ?
                 AND b.user_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$bookingId, $userId]);

            $booking = $stmt->fetch();

            // Verify booking ownership.
            if (!$booking) {
                $db->rollBack();

                Response::error('Booking not found', 404);
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

            $stmt->execute([$bookingId, $userId]);

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

            $stmt->execute([$booking['slot_id']]);

            if ($stmt->rowCount() !== 1) {
                throw new \RuntimeException(
                    'Unable to release appointment slot'
                );
            }

            // Commit both database changes.
            $db->commit();

            Response::success([
                'message' => 'Appointment cancelled successfully',
                'booking_id' => $bookingId,
                'slot_id' => (int) $booking['slot_id'],
                'status' => 'cancelled'
            ], 200);

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Cancellation failed: ' . $e->getMessage()
            );

            Response::error(
                'Unable to cancel appointment',
                500
            );
        }
    }

    // Retrieve bookings belonging to a specific user.
    private static function getUserBookings(int $userId): array
    {
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
                    AND TIMESTAMP(t.slot_date, t.start_time) > NOW()
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
             ORDER BY t.slot_date DESC, t.start_time DESC"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    // Return the authenticated user's bookings as JSON.
    public static function bookings(array $params): void
    {
        $user = \Session::user();

        if (!$user || empty($user['id'])) {
            Response::error(
                'You must log in to view your bookings',
                401
            );
        }

        $userId = (int) $user['id'];

        $bookings = self::getUserBookings($userId);

        Response::success($bookings, 200);
    }

    // Display the My Bookings page.
    public static function bookingsPage(array $params): void
    {
        $user = \Session::user();

        if (!$user || empty($user['id'])) {
            Response::error(
                'You must log in to view your bookings',
                401
            );
        }

        $userId = (int) $user['id'];

        $bookings = self::getUserBookings($userId);

        $upcomingBookings = [];
        $bookingHistory = [];

        // Separate upcoming appointments from booking history.
        foreach ($bookings as $booking) {

            if ((int) $booking['can_cancel'] === 1) {
                $upcomingBookings[] = $booking;
            } else {
                $bookingHistory[] = $booking;
            }
        }

        // Display upcoming appointments chronologically.
        $upcomingBookings = array_reverse($upcomingBookings);

        // Load the My Bookings view.
        require BASE_PATH . '/app/Views/Resources/bookings.php';
    }
}