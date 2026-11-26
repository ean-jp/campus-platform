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

                <a href="/resources/services/<?= (int) $service["id"] ?>/providers">
                    View Providers
                </a>

            </div>

        <?php endforeach; ?>

    </div>

</body>
</html>