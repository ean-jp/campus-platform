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
                    <?= htmlspecialchars($selectedService, ENT_QUOTES, "UTF-8") ?>
                </p>

                <?php if (!empty($provider["bio"])): ?>

                    <p>
                        <?= htmlspecialchars($provider["bio"], ENT_QUOTES, "UTF-8") ?>
                    </p>

                <?php endif; ?>

                <a href="/resources/providers/<?= (int) $provider["id"] ?>/slots">
                    View Availability
                </a>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        <a href="/resources">Back to Services</a>
    </p>

</body>
</html>