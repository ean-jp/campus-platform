<?php

declare(strict_types=1);

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Appointment Availability | Campus Platform</title>

    <script src="/assets/js/resources.js" defer></script>

</head>

<body>

    <header>

        <h1>Appointment Availability</h1>

        <nav>

            <a href="/resources">Campus Resources</a>

            |

            <a href="/resources/bookings">My Bookings</a>

        </nav>

    </header>

    <hr>

    <main>

        <div id="resource-message" role="status"></div>

        <h2>
            <?= escapeHtml($provider["name"]) ?>
        </h2>

        <p>
            <strong>Service:</strong>
            <?= escapeHtml($provider["service_name"]) ?>
        </p>

        <p>Select an available appointment date and time.</p>

        <?php if (empty($groupedSlots)): ?>

            <p>No appointments are currently available.</p>

        <?php else: ?>

            <?php foreach ($groupedSlots as $date => $slots): ?>

                <section class="appointment-day">

                    <h3>
                        <?= escapeHtml(
                            date("l, F j, Y", strtotime($date))
                        ) ?>
                    </h3>

                    <div class="time-slots">

                        <?php foreach ($slots as $slot): ?>

                            <button
                                type="button"
                                data-slot-id="<?= (int) $slot["id"] ?>"
                            >
                                <?= escapeHtml(
                                    date(
                                        "g:i A",
                                        strtotime($slot["start_time"])
                                    )
                                ) ?>

                                -

                                <?= escapeHtml(
                                    date(
                                        "g:i A",
                                        strtotime($slot["end_time"])
                                    )
                                ) ?>
                            </button>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

        <hr>

        <p>
            <a href="/resources/services/<?= (int) $provider["service_id"] ?>/providers">
                Back to Providers
            </a>
        </p>

        <p>
            <a href="/resources">Back to Services</a>
        </p>

    </main>

    <footer>
        <p>Campus Platform | Campus Resources</p>
    </footer>

</body>

</html>