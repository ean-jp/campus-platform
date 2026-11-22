
<?php
// Temporary provider data.
// Later, this will be retrieved from MySQL.

$providers = [
    1 => "Academic Advisor A",
    2 => "Academic Advisor B",
    3 => "Peer Tutor A",
    4 => "Counsellor A",
    5 => "Career Advisor A"
];

// Get provider ID from the URL.
$providerId = filter_input(
    INPUT_GET,
    "provider_id",
    FILTER_VALIDATE_INT
);

// Check that the provider exists.
$providerExists = (
    $providerId !== false &&
    $providerId !== null &&
    isset($providers[$providerId])
);

if (!$providerExists) {
    http_response_code(404);
}

// Sample appointment availability.
// These are demonstration dates and times,
// not real provider appointments.

$availableSlots = [
    [
        "date" => "2026-11-24",
        "times" => ["09:00", "10:00", "11:00"]
    ],
    [
        "date" => "2026-11-25",
        "times" => ["13:00", "14:00", "15:00"]
    ],
    [
        "date" => "2026-11-26",
        "times" => ["09:30", "11:30", "14:30"]
    ]
];

function escapeHtml($value) {
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

    <?php if (!$providerExists): ?>

        <h1>Provider Not Found</h1>

        <p>The selected provider does not exist.</p>

        <a href="services.php">Back to Services</a>

    <?php else: ?>

        <h1>Appointment Availability</h1>

        <h2>
            <?= escapeHtml($providers[$providerId]) ?>
        </h2>

        <p>Select an available appointment date and time.</p>

        <?php foreach ($availableSlots as $day): ?>

            <div class="appointment-day">

                <h3>
                    <?= escapeHtml(
                        date(
                            "l, F j, Y",
                            strtotime($day["date"])
                        )
                    ) ?>
                </h3>

                <div class="time-slots">

                    <?php foreach ($day["times"] as $time): ?>

                        <button type="button" disabled>
                            <?= escapeHtml(
                                date(
                                    "g:i A",
                                    strtotime($time)
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
                when the database is connected.
            </em>
        </p>

        <p>
            <a href="services.php">Back to Services</a>
        </p>

    <?php endif; ?>

</body>
</html>
