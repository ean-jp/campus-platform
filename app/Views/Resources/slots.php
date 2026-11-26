<?php

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Appointment Availability</title>
</head>

<body>

    <h1>Appointment Availability</h1>

    <h2>
        <?= escapeHtml($provider["name"]) ?>
    </h2>

    <p>
        Service: <?= escapeHtml($provider["service_name"]) ?>
    </p>

    <p>Select an available appointment date and time.</p>

    <?php if (empty($groupedSlots)): ?>

        <p>No appointments are currently available.</p>

    <?php else: ?>

        <?php foreach ($groupedSlots as $date => $slots): ?>

            <div class="appointment-day">

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
                            disabled
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

            </div>

        <?php endforeach; ?>

        <p>
            <em>
                Appointment booking will be enabled
                in the next implementation stage.
            </em>
        </p>

    <?php endif; ?>

    <p>
        <a href="/resources/services/<?= (int) $provider["service_id"] ?>/providers">
            Back to Providers
        </a>
    </p>

    <p>
        <a href="/resources">Back to Services</a>
    </p>

</body>
</html>