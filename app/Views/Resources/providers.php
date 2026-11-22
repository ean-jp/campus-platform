
<?php
// Temporary sample provider data.
// Later, this information will come from MySQL.

$providers = [
    [
        "id" => 1,
        "name" => "Academic Advisor A",
        "service" => "Academic Advising"
    ],
    [
        "id" => 2,
        "name" => "Academic Advisor B",
        "service" => "Academic Advising"
    ],
    [
        "id" => 3,
        "name" => "Peer Tutor A",
        "service" => "Peer Tutoring"
    ],
    [
        "id" => 4,
        "name" => "Counsellor A",
        "service" => "Counselling"
    ],
    [
        "id" => 5,
        "name" => "Career Advisor A",
        "service" => "Career Services"
    ]
];

// Get the selected service from the URL.
$selectedService = $_GET["service"] ?? "";

// Filter providers by the selected service.
$filteredProviders = array_filter(
    $providers,
    function ($provider) use ($selectedService) {
        return $provider["service"] === $selectedService;
    }
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Service Providers</title>
</head>

<body>

    <h1>
        <?= htmlspecialchars($selectedService, ENT_QUOTES, "UTF-8") ?>
    </h1>

    <p>Select a provider to view available appointments.</p>

    <?php if (empty($filteredProviders)): ?>

        <p>No providers available for this service.</p>

    <?php else: ?>

        <?php foreach ($filteredProviders as $provider): ?>

            <div class="provider-card">

                <h2>
                    <?= htmlspecialchars($provider["name"], ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars($provider["service"], ENT_QUOTES, "UTF-8") ?>
                </p>

                <a href="slots.php?provider_id=<?= $provider["id"] ?>">
                    View Availability
                </a>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        <a href="services.php">Back to Services</a>
    </p>

</body>
</html>
