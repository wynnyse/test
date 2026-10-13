<?php

// One sidebar for every admin page, so the markup and spacing never drift apart.

$currentPage = basename($_SERVER['PHP_SELF']);

$links = [
    'admin_dashboard.php'       => 'Overview',
    'my_services_portfolio.php' => 'My Services / Portfolio',
    'services_settings.php'     => 'Services Settings',
    'client_requests.php'       => 'Client Requests',
    'projects.php'              => 'Projects',
    'messages.php'              => 'Messages',
    'reports.php'               => 'Reports',
];
?>
<aside class="sidebar">
    <header class="sidebar__brand">
        <h2>Kvffie Arts</h2>
        <p>Admin Panel</p>
    </header>

    <nav class="sidebar__nav" aria-label="Admin navigation">
        <?php foreach ($links as $file => $label): ?>
            <a href="<?= $file ?>"
               <?= $file === $currentPage ? 'class="active" aria-current="page"' : '' ?>>
                <?= $label ?>
            </a>
        <?php endforeach; ?>

        <a href="../login.php">Log Out</a>
    </nav>
</aside>