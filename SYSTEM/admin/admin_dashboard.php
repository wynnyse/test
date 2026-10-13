<?php
session_start();

    include("../connection.php");
    include("../functions.php");    

    if (!isset($con)) {
        $con = null;
    }

    $user_data = check_login($con);
//are u sure to log out reminder
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kvffie Arts | Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../imgs/mascot.png">
 
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_dashboard.css">
</head>
<body>
 
<div class="admin-layout">
 
    <?php include __DIR__ . '/sidebar.php'; ?>
 
    <main class="admin-main dashboard">
 
        <header class="dashboard-header">
            <section>
                <h2>Overview</h2>
                <p>
                    Welcome back,
                    <?= htmlspecialchars($user_data['user_name']) ?>!
                </p>
            </section>
 
            <nav aria-label="Dashboard shortcuts">
                <a href="client_requests.php">View Requests</a>
                <a href="messages.php">Messages</a>
            </nav>
        </header>
 
        <section class="summary-section" aria-labelledby="summary-heading">
            <header>
                <h2 id="summary-heading">Business Overview</h2>
            </header>
 
            <article class="summary-card">
                <h3>New Requests</h3>
                <p>0</p>
            </article>
 
            <article class="summary-card">
                <h3>Active Projects</h3>
                <p>0</p>
            </article>
 
            <article class="summary-card">
                <h3>Total Clients</h3>
                <p>0</p>
            </article>
 
            <article class="summary-card">
                <h3>Pending Payments</h3>
                <p>₱0.00</p>
            </article>
        </section>
 
        <section class="recent-requests" aria-labelledby="requests-heading">
            <header>
                <h2 id="requests-heading">Recent Client Requests</h2>
                <a href="client_requests.php">View All</a>
            </header>
 
            <table>
                <caption>Latest commission requests</caption>
                <thead>
                    <tr>
                        <th scope="col">Request ID</th>
                        <th scope="col">Client</th>
                        <th scope="col">Service</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
 
                <tbody>
                    <tr>
                        <td colspan="5">No requests yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
 
        <section class="project-progress" aria-labelledby="progress-heading">
            <header>
                <h2 id="progress-heading">Project Progress</h2>
                <a href="projects.php">View All</a>
            </header>
 
            <article class="project-card">
                <h3>No active projects</h3>
                <p>Accepted commission projects will appear here.</p>
            </article>
        </section>
 
        <section class="recent-messages" aria-labelledby="messages-heading">
            <header>
                <h2 id="messages-heading">Recent Messages</h2>
                <a href="messages.php">View All</a>
            </header>
 
            <article class="message-card">
                <h3>No recent messages</h3>
                <p>Client conversations will appear here.</p>
            </article>
        </section>
 
        <footer class="dashboard-footer">
            <p>&copy; <?= date("Y") ?> Kvffie Arts. All rights reserved.</p>
        </footer>
 
    </main>
 
</div>
 
</body>
</html>