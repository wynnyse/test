<?php
session_start();

require_once "../connection.php";
require_once "../functions.php";

check_login($con);

// Only Admin can manage commission requests
if (
    !isset($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'Admin'
) {
    header("Location: ../login.php");
    exit;
}

// Generate CSRF token for form security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Escape output helper
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================================================================
// 1. PHP FUNCTION: HANDLE STATUS & ADMIN NOTES UPDATE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Pending');
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        $allowed_statuses = ['Pending', 'Accepted', 'Rejected', 'Cancelled', 'Completed'];

        if ($request_id > 0 && in_array($status, $allowed_statuses)) {
            $stmt = mysqli_prepare($con, "UPDATE commission_request SET status = ?, admin_notes = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_notes, $request_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        header("Location: client_requests.php");
        exit;
    }
}

// =========================================================================
// 2. PHP FUNCTION: FETCH REQUESTS WITH CLIENT & SERVICE DETAILS
// =========================================================================
$requests = [];

// JOIN with users (or clients) and services tables to get names and details
$query = "SELECT cr.*, 
                 u.user_name AS client_name, 
                 u.email AS client_email,
                 s.service_name 
          FROM commission_requests cr
          LEFT JOIN users u ON cr.client_id = u.id
          LEFT JOIN services s ON cr.service_id = s.id
          ORDER BY cr.id DESC";

$result = mysqli_query($con, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
} else {
    die("Database query failed: " . mysqli_error($con));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Client Requests | Kvffie Arts</title>
    <link rel="icon" type="image/x-icon" href="../imgs/mascot.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/client_requests.css">
    <script src="../script/client_requests.js" defer></script>
</head>

<body>

<div class="admin-layout">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">

        <!-- Page Heading -->
        <header class="page-header">
            <div class="page-header__text">
                <p class="page-header__eyebrow">COMMISSIONS</p>
                <h1>Client Requests</h1>
                <p>Review, accept, reject, or complete incoming client requests.</p>
            </div>
        </header>

        <!-- Search & Status Filters -->
        <section class="request-controls" aria-label="Search and filter requests">
            <div class="request-controls__field request-controls__field--search">
                <label for="request-search">Search Requests</label>
                <input type="search" id="request-search" placeholder="Search by title, client name, or email...">
            </div>

            <div class="request-controls__field">
                <label for="status-filter">Status Filter</label>
                <select id="status-filter">
                    <option value="all">All Requests</option>
                    <option value="Pending">Pending</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
        </section>

        <!-- Requests List Table -->
        <section class="request-listings">
            <header class="section-heading">
                <h2>All Requests</h2>
                <p><?= count($requests) ?> total</p>
            </header>

            <?php if (!empty($requests)): ?>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="request-table">
                            <thead>
                                <tr>
                                    <th>Title & Client</th>
                                    <th>Service / Type</th>
                                    <th>Budget</th>
                                    <th>Target Deadline</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr class="request-row" 
                                        data-id="<?= (int) $req['id'] ?>"
                                        data-title="<?= escape($req['title']) ?>"
                                        data-status="<?= escape($req['status']) ?>"
                                        data-client-name="<?= escape($req['client_name'] ?? 'Client #' . $req['client_id']) ?>"
                                        data-client-email="<?= escape($req['client_email'] ?? 'N/A') ?>"
                                        data-service-name="<?= escape($req['service_name'] ?? 'Custom Service') ?>"
                                        data-commission-type="<?= escape($req['commission_type'] ?? 'Standard') ?>"
                                        data-budget="<?= (float) ($req['budget'] ?? 0) ?>"
                                        data-deadline="<?= !empty($req['requested_deadline']) ? date('M d, Y', strtotime($req['requested_deadline'])) : 'N/A' ?>"
                                        data-description="<?= escape($req['description'] ?? '') ?>"
                                        data-admin-notes="<?= escape($req['admin_notes'] ?? '') ?>">
                                        
                                        <!-- Title & Client Info -->
                                        <td>
                                            <div class="client-cell">
                                                <strong><?= escape($req['title']) ?></strong>
                                                <small><?= escape($req['client_name'] ?? 'Client #' . $req['client_id']) ?> &bull; <?= escape($req['client_email'] ?? '') ?></small>
                                            </div>
                                        </td>

                                        <!-- Service / Type -->
                                        <td>
                                            <span class="service-tag"><?= escape($req['service_name'] ?? 'Custom Service') ?></span>
                                            <?php if (!empty($req['commission_type'])): ?>
                                                <small class="type-tag">(<?= escape($req['commission_type']) ?>)</small>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Budget -->
                                        <td>
                                            <strong>₱<?= number_format((float) ($req['budget'] ?? 0), 2) ?></strong>
                                        </td>

                                        <!-- Deadline -->
                                        <td>
                                            <?= !empty($req['requested_deadline']) ? date('M d, Y', strtotime($req['requested_deadline'])) : 'N/A' ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td>
                                            <?php $statusClass = strtolower($req['status']); ?>
                                            <span class="badge badge--<?= $statusClass ?>">
                                                <?= escape($req['status']) ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="table-actions">
                                                <button type="button" class="button button--outline view-request-btn">
                                                    View Details
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>

                <!-- Empty State -->
                <section class="empty-state">
                    <h3>No requests found</h3>
                    <p>Client commission requests will appear here once submitted.</p>
                </section>

            <?php endif; ?>
        </section>

    </main>
</div>

<!-- VIEW / MANAGE REQUEST MODAL -->
<dialog class="request-modal" id="request-modal">
    <div class="request-modal__container">
        <header class="request-modal__header">
            <div>
                <p class="request-modal__eyebrow" id="modal-request-id">REQUEST #000</p>
                <h2 id="modal-request-title">Commission Title</h2>
            </div>
            <button type="button" class="request-modal__close" id="close-request-modal" aria-label="Close dialog">&times;</button>
        </header>

        <div class="request-modal__body">
            <!-- Client & Commission Meta Grid -->
            <div class="meta-grid">
                <div class="meta-item">
                    <label>Client Name</label>
                    <p id="modal-client-name">-</p>
                </div>
                <div class="meta-item">
                    <label>Email Address</label>
                    <p id="modal-client-email">-</p>
                </div>
                <div class="meta-item">
                    <label>Service Category</label>
                    <p id="modal-service-name">-</p>
                </div>
                <div class="meta-item">
                    <label>Commission Type</label>
                    <p id="modal-commission-type">-</p>
                </div>
                <div class="meta-item">
                    <label>Offered Budget</label>
                    <p id="modal-budget">₱0.00</p>
                </div>
                <div class="meta-item">
                    <label>Target Deadline</label>
                    <p id="modal-deadline">N/A</p>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Request Description / Instructions</label>
                <div class="details-box" id="modal-description">
                    No description provided.
                </div>
            </div>

            <!-- Status Update Form -->
            <form id="update-status-form" action="client_requests.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" id="modal-input-request-id" value="">

                <div class="form-group">
                    <label for="request-status-select">Update Request Status</label>
                    <select name="status" id="request-status-select">
                        <option value="Pending">Pending</option>
                        <option value="Accepted">Accepted</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="admin-notes">Artist Feedback / Notes to Client</label>
                    <textarea name="admin_notes" id="admin-notes" rows="3" placeholder="Add feedback, terms, or reasons for status change..."></textarea>
                </div>

                <footer class="request-modal__actions">
                    <button type="button" class="button button--outline" id="cancel-request-btn">Close</button>
                    <button type="submit" class="button button--primary">Update Request</button>
                </footer>
            </form>
        </div>
    </div>
</dialog>

</body>
</html>