
<?php
// Temporary sample data.
// Later, these services will be retrieved from MySQL.

$services = [
    [
        "id" => 1,
        "name" => "Academic Advising",
        "description" => "Get guidance on your courses and academic progress."
    ],
    [
        "id" => 2,
        "name" => "Peer Tutoring",
        "description" => "Receive academic assistance from peer tutors."
    ],
    [
        "id" => 3,
        "name" => "Counselling",
        "description" => "Access confidential student support services."
    ],
    [
        "id" => 4,
        "name" => "Career Services",
        "description" => "Get advice on internships and career development."
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Campus Resources</title>
</head>

<body>

    <h1>Campus Resources</h1>

    <p>
        Select a service to explore available appointments.
    </p>

    <div class="services-container">

        <?php foreach ($services as $service): ?>

            <div class="service-card">

                <h2>
                    <?= htmlspecialchars($service["name"], ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars($service["description"], ENT_QUOTES, "UTF-8") ?>
                </p>

                <a href="providers.php?service=<?= urlencode($service["name"]) ?>">
                    View Providers
                </a>

            </div>

        <?php endforeach; ?>

    </div>

</body>
</html>
