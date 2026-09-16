<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AVIC Portal</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../partials/topbar.php'; ?>
            <div class="content-wrapper">
                <?php include __DIR__ . '/../partials/alerts.php'; ?>
                <?php echo $content; ?>
            </div>
        </main>
    </div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
