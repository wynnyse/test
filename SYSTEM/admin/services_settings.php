<?php
session_start();

require_once "../connection.php";
require_once "../functions.php";

check_login($con);

// Only Admin can manage services.
if (
    !isset($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'Admin'
) {
    header("Location: ../login.php");
    exit;
}

// Create CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================================================================
// HANDLE POST ACTIONS (ADD, EDIT, DELETE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $action = $_POST['action'] ?? 'add';

    // Handle Delete
    if ($action === 'delete') {
        $service_id = intval($_POST['service_id'] ?? 0);

        if ($service_id > 0) {
            // Delete image file if exists
            $fetch_stmt = mysqli_prepare($con, "SELECT sample_image FROM services WHERE id = ?");
            mysqli_stmt_bind_param($fetch_stmt, "i", $service_id);
            mysqli_stmt_execute($fetch_stmt);
            $res = mysqli_stmt_get_result($fetch_stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                if (!empty($row['sample_image'])) {
                    $file_to_delete = '../' . ltrim($row['sample_image'], '/');
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }
            }
            mysqli_stmt_close($fetch_stmt);

            // Delete record from DB
            $delete_stmt = mysqli_prepare($con, "DELETE FROM services WHERE id = ?");
            mysqli_stmt_bind_param($delete_stmt, "i", $service_id);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }

        header("Location: services_settings.php");
        exit;
    }

    // Handle Add / Edit
    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = floatval($_POST['base_price'] ?? 0);
    $estimated_days = !empty($_POST['estimated_days']) ? intval($_POST['estimated_days']) : null;
    $status = isset($_POST['is_available']) ? 'Available' : 'Unavailable';

    $sample_image = "";
    if (isset($_FILES['sample_image']) && $_FILES['sample_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['sample_image']['tmp_name'];
        $fileName = $_FILES['sample_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadDir = '../uploads/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $sample_image = 'uploads/' . $newFileName;
            }
        }
    }

    if ($action === 'add') {
        $stmt = mysqli_prepare($con, "INSERT INTO services (service_name, description, sample_image, base_price, estimated_days, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssdis", $service_name, $description, $sample_image, $base_price, $estimated_days, $status);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } elseif ($action === 'edit') {
        $service_id = intval($_POST['service_id']);

        if (!empty($sample_image)) {
            $stmt = mysqli_prepare($con, "UPDATE services SET service_name=?, description=?, sample_image=?, base_price=?, estimated_days=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssdisi", $service_name, $description, $sample_image, $base_price, $estimated_days, $status, $service_id);
        } else {
            $stmt = mysqli_prepare($con, "UPDATE services SET service_name=?, description=?, base_price=?, estimated_days=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssdisi", $service_name, $description, $base_price, $estimated_days, $status, $service_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: services_settings.php");
    exit;
}

// Fetch Listings
$services = [];
$query = "SELECT * FROM services ORDER BY id DESC";
$result = mysqli_query($con, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
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

    <title>Services Settings | Kvffie Arts</title>
    <link rel="icon" type="image/x-icon" href="../imgs/mascot.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/services_settings.css">
    <script src="../script/services_settings.js" defer></script>
</head>

<body>

<div class="admin-layout">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">

        <!-- Page heading -->
        <header class="page-header">
            <div class="page-header__text">
                <p class="page-header__eyebrow">MANAGE LISTINGS</p>
                <h1>Services Setup</h1>
                <p>Manage the services clients can commission.</p>
            </div>

            <button type="button" class="button button--primary" id="add-service-button">
                + Add Listing
            </button>
        </header>

        <!-- Search and filters -->
        <section class="service-controls" aria-label="Search and filter services">
            <div class="service-controls__field service-controls__field--search">
                <label for="service-search">Search services</label>
                <input type="search" id="service-search" placeholder="Search by service name...">
            </div>

            <div class="service-controls__field">
                <label for="service-filter">Availability</label>
                <select id="service-filter">
                    <option value="all">All services</option>
                    <option value="active">Active</option>
                    <option value="hidden">Hidden</option>
                </select>
            </div>
        </section>

        <!-- Service listings -->
        <section class="service-listings" aria-labelledby="listings-heading">
            <header class="section-heading">
                <h2 id="listings-heading">My Listings</h2>
                <p><?= count($services) ?> total</p>
            </header>

            <?php if (count($services) > 0): ?>

                <div class="service-grid" id="service-grid">
                    <?php foreach ($services as $service): ?>
                        <article class="service-card"
                                 data-id="<?= (int) $service['id'] ?>"
                                 data-name="<?= escape($service['service_name']) ?>"
                                 data-status="<?= escape($service['status']) ?>"
                                 data-days="<?= (int) ($service['estimated_days'] ?? 0) ?>"
                                 data-price="<?= (float) $service['base_price'] ?>"
                                 data-description="<?= escape($service['description'] ?? '') ?>">

                            <figure class="service-card__image">
                                <?php if (!empty($service['sample_image'])): ?>
                                    <img src="../<?= escape(ltrim($service['sample_image'], '/')) ?>"
                                         alt="<?= escape($service['service_name']) ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="service-card__placeholder">No image uploaded</div>
                                <?php endif; ?>
                            </figure>

                            <div class="service-card__content">
                                <div class="service-card__details">
                                    <h3><?= escape($service['service_name']) ?></h3>

                                    <p class="service-card__price">
                                        From ₱<?= number_format((float) $service['base_price'], 2) ?>
                                    </p>

                                    <?php if (!empty($service['estimated_days'])): ?>
                                        <p class="service-card__meta">
                                            Estimated duration: <?= (int) $service['estimated_days'] ?> day(s)
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <p class="service-card__status">
                                    <?= $service['status'] === 'Available' ? 'Active' : 'Hidden from clients' ?>
                                </p>

                                <div class="service-card__actions">
                                    <button type="button" class="button button--primary edit-service-button" data-id="<?= (int) $service['id'] ?>">
                                        Edit
                                    </button>
                                    <button type="button" class="button button--outline preview-service-button" data-id="<?= (int) $service['id'] ?>">
                                        Preview
                                    </button>
                                    <button type="button" class="button button--danger delete-service-button" data-id="<?= (int) $service['id'] ?>" data-name="<?= escape($service['service_name']) ?>">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <section class="empty-state" id="service-empty-state">
                    <h3>No services yet</h3>
                    <p>Add your first listing to show clients what they can commission.</p>

                    <button type="button" class="button button--primary" id="first-service-button">
                        Add Your First Listing
                    </button>
                </section>

            <?php endif; ?>

        </section>

    </main>
</div>

<!-- ADD / EDIT SERVICE POPUP MODAL -->
<dialog class="service-modal" id="service-modal">
    <form id="service-form" action="services_settings.php" method="POST" enctype="multipart/form-data">

        <header class="service-modal__header">
            <h2 id="service-modal-title">Add Listing</h2>
            <button type="button" class="service-modal__close" id="close-service-modal" aria-label="Close dialog">
                &times;
            </button>
        </header>

        <!-- Hidden Security & Identification Fields -->
        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" id="service-action" value="add">
        <input type="hidden" name="service_id" id="service-id" value="">

        <!-- Service Name -->
        <div class="form-group">
            <label for="service-name">Service Name</label>
            <input type="text" name="service_name" id="service-name" required>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="service-description">Description</label>
            <textarea name="description" id="service-description" rows="4"></textarea>
        </div>

        <!-- Pricing & Estimated Days -->
        <div class="form-row">
            <div class="form-group">
                <label for="service-price">Base Price (₱)</label>
                <input type="number" name="base_price" id="service-price" min="0" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="service-days">Estimated Duration (Days)</label>
                <input type="number" name="estimated_days" id="service-duration" min="1" placeholder="e.g. 5">
            </div>
        </div>

        <!-- Image Upload -->
        <div class="form-group">
            <label for="service-image">Sample Image</label>
            <input type="file" name="sample_image" id="service-image" accept="image/jpeg,image/png,image/webp,image/gif">
            <p class="form-help">Upload a JPG, PNG, WEBP, or GIF image.</p>
        </div>

        <!-- Availability Status -->
        <div class="form-group">
            <label class="checkbox-label" for="service-active">
                <input type="checkbox" name="is_available" id="service-active" value="1" checked>
                Show this service to clients (Available)
            </label>
        </div>

        <!-- Action Buttons -->
        <footer class="service-modal__actions">
            <button type="button" class="button button--outline" id="cancel-service-button">
                Cancel
            </button>
            <button type="submit" class="button button--primary" id="save-service-button">
                Save Listing
            </button>
        </footer>

    </form>
</dialog>

<!-- HIDDEN FORM FOR DELETION -->
<form id="delete-service-form" action="services_settings.php" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="service_id" id="delete-service-id" value="">
</form>

</body>
</html>