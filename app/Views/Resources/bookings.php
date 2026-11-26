<?php

declare(strict_types=1);

function escapeBooking(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatBookingDate(string $date): string
{
    return date('l, d F Y', strtotime($date));
}

function formatBookingTime(string $time): string
{
    return date('g:i A', strtotime($time));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Bookings | Campus Platform</title>

    <script src="/campus-platform-fused/public/assets/js/resources.js" defer></script>

</head>

<body>

    <header>

        <h1>My Bookings</h1>

        <p>View and manage your campus resource appointments.</p>

        <nav>
            <a href="/campus-platform-fused/public/resources">Browse Campus Resources</a>
        </nav>

    </header>

    <hr>

    <main>

        <div id="resource-message" role="status"></div>

        <!-- Upcoming appointments -->

        <section>

            <h2>Upcoming Appointments</h2>

            <?php if (empty($upcomingBookings)): ?>

                <p>You have no upcoming appointments.</p>

                <a href="/campus-platform-fused/public/resources">Book an appointment</a>

            <?php else: ?>

                <?php foreach ($upcomingBookings as $booking): ?>

                    <article>

                        <h3>
                            <?= escapeBooking($booking['service_name']) ?>
                        </h3>

                        <p>
                            <strong>Provider:</strong>
                            <?= escapeBooking($booking['provider_name']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= formatBookingDate($booking['slot_date']) ?>
                        </p>

                        <p>
                            <strong>Time:</strong>

                            <?= formatBookingTime($booking['start_time']) ?>

                            -

                            <?= formatBookingTime($booking['end_time']) ?>
                        </p>

                        <p>
                            <strong>Booking ID:</strong>
                            <?= (int) $booking['booking_id'] ?>
                        </p>

                        <p>
                            <strong>Status:</strong>

                            <?= escapeBooking(
                                ucfirst($booking['status'])
                            ) ?>
                        </p>

                        <button
                            type="button"
                            data-booking-id="<?= (int) $booking['booking_id'] ?>"
                        >
                            Cancel Appointment
                        </button>

                    </article>

                    <hr>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

        <!-- Appointment history -->

        <section>

            <h2>Appointment History</h2>

            <?php if (empty($bookingHistory)): ?>

                <p>You have no previous appointments.</p>

            <?php else: ?>

                <?php foreach ($bookingHistory as $booking): ?>

                    <article>

                        <h3>
                            <?= escapeBooking($booking['service_name']) ?>
                        </h3>

                        <p>
                            <strong>Provider:</strong>
                            <?= escapeBooking($booking['provider_name']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= formatBookingDate($booking['slot_date']) ?>
                        </p>

                        <p>
                            <strong>Time:</strong>

                            <?= formatBookingTime($booking['start_time']) ?>

                            -

                            <?= formatBookingTime($booking['end_time']) ?>
                        </p>

                        <p>
                            <strong>Booking ID:</strong>
                            <?= (int) $booking['booking_id'] ?>
                        </p>

                        <p>
                            <strong>Status:</strong>

                            <?= escapeBooking(
                                ucfirst($booking['status'])
                            ) ?>
                        </p>

                        <?php if (!empty($booking['cancelled_at'])): ?>

                            <p>
                                <strong>Cancelled on:</strong>

                                <?= escapeBooking(
                                    date(
                                        'd F Y, g:i A',
                                        strtotime($booking['cancelled_at'])
                                    )
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </article>

                    <hr>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    </main>

    <footer>
        <p>Campus Platform | Campus Resources</p>
    </footer>

</body>

</html>