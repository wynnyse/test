<?php
session_start();

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../functions.php';

check_login($con);

// Allow only the Admin/Owner.
if (
    !isset($_SESSION['user_type']) ||
    $_SESSION['user_type'] !== 'Admin'
) {
    header('Location: ../login.php');
    exit;
}

// Create a CSRF token for form security.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function imagePath($path)
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    // Remove old relative prefixes, if present.
    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }

    return '../' . ltrim($path, '/');
}


function portfolioResponse($success, $message, $redirectUrl, $extra = [])
{
    // Return JSON when JavaScript sends an asynchronous request.
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $extra));

        exit;
    }

    // Preserve normal PHP form behavior as a fallback.
    $_SESSION['portfolio_flash'] = [
        'type' => $success ? 'success' : 'error',
        'message' => $message
    ];

    header("Location: $redirectUrl");
    exit;
}


// Delete an image file we uploaded (only inside uploads/portfolio/).
function deletePortfolioImage($path)
{
    $path = trim((string) $path);

    // External URLs or other folders are left alone.
    if (dirname($path) !== 'uploads/portfolio') {
        return;
    }

    $absolute = __DIR__ . '/../' . $path;

    if (is_file($absolute)) {
        unlink($absolute);
    }
}

// Show messages after a form submission.
$flash = $_SESSION['portfolio_flash'] ?? null;
unset($_SESSION['portfolio_flash']);


// ========================================
// PROCESS ADD / EDIT / DELETE ARTWORK
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $redirectUrl = 'my_services_portfolio.php?tab=portfolio';

    // Verify the form token.
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        portfolioResponse(
            false,
            'Invalid form request. Please try again.',
            $redirectUrl
        );
    }

    $action = $_POST['action'] ?? '';
    $artworkId = (int) ($_POST['artwork_id'] ?? 0);


    // ========================================
    // DELETE ARTWORK
    // ========================================

    if ($action === 'delete_artwork') {

        if ($artworkId < 1) {

            portfolioResponse(
                false,
                'Invalid artwork ID.',
                $redirectUrl
            );
        }

        // Find the artwork so we can remove its image file afterwards.
        $stmt = $con->prepare('SELECT image_path FROM portfolio WHERE id = ?');
        $stmt->bind_param('i', $artworkId);
        $stmt->execute();
        $artwork = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$artwork) {
            portfolioResponse(
                false,
                'That artwork could not be found.',
                $redirectUrl
            );
        }

        $stmt = $con->prepare('DELETE FROM portfolio WHERE id = ?');
        $stmt->bind_param('i', $artworkId);
        $deleted = $stmt->execute();
        $stmt->close();

        if ($deleted) {
            // Delete the image after the database row is removed.
            deletePortfolioImage($artwork['image_path']);

            portfolioResponse(
                true,
                'Artwork deleted.',
                $redirectUrl,
                ['artwork_id' => $artworkId]
            );
        } else {
            portfolioResponse(
                false,
                'Unable to delete the artwork. Please try again.',
                $redirectUrl
            );
        }
    }


    // ========================================
    // ADD / EDIT: READ AND VALIDATE THE FORM
    // ========================================

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isVisible = isset($_POST['is_visible']) ? 1 : 0;

    $errors = [];

    if (!in_array($action, ['add_artwork', 'edit_artwork'], true)) {
        $errors[] = 'Invalid action.';
    }

    if ($action === 'edit_artwork' && $artworkId < 1) {
        $errors[] = 'Invalid artwork ID.';
    }

    if ($title === '') {
        $errors[] = 'Please enter an artwork title.';
    }

    if (strlen($title) > 150) {
        $errors[] = 'The title must not exceed 150 characters.';
    }

    if (strlen($category) > 100) {
        $errors[] = 'The category must not exceed 100 characters.';
    }

    if (strlen($description) > 50000) {
        $errors[] = 'The description is too long.';
    }

    // Find the existing artwork when editing.
    $existingArtwork = null;

    if ($action === 'edit_artwork' && empty($errors)) {
        $stmt = $con->prepare(
            'SELECT image_path FROM portfolio WHERE id = ?'
        );

        $stmt->bind_param('i', $artworkId);
        $stmt->execute();

        $result = $stmt->get_result();
        $existingArtwork = $result->fetch_assoc();

        $stmt->close();

        if (!$existingArtwork) {
            $errors[] = 'The artwork you are editing could not be found.';
        }
    }

    // Handle an uploaded image.
    $newImagePath = null;
    $uploadedAbsolutePath = null;

    $file = $_FILES['artwork_image'] ?? null;
    $hasUploadedFile = $file && $file['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasUploadedFile) {

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The image upload failed. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'The image must be 5 MB or smaller.';
        } else {
            // Check the actual file type, not just its filename.
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];

            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Upload a JPG, PNG, WEBP, or GIF image.';
            } elseif (@getimagesize($file['tmp_name']) === false) {
                $errors[] = 'The uploaded file is not a valid image.';
            } else {
                $uploadDirectory = __DIR__ . '/../uploads/portfolio/';

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true) &&
                    !is_dir($uploadDirectory)
                ) {
                    $errors[] = 'The upload folder could not be created.';
                } else {
                    // Generate a unique filename.
                    $fileName = bin2hex(random_bytes(16))
                        . '.'
                        . $allowedTypes[$mimeType];

                    $uploadedAbsolutePath = $uploadDirectory . $fileName;
                    $newImagePath = 'uploads/portfolio/' . $fileName;

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $uploadedAbsolutePath
                        )
                    ) {
                        $errors[] = 'Unable to save the uploaded image.';
                        $uploadedAbsolutePath = null;
                        $newImagePath = null;
                    }
                }
            }
        }
    }

    // New artwork requires an image.
    if (
        $action === 'add_artwork' &&
        !$hasUploadedFile
    ) {
        $errors[] = 'Please select an artwork image.';
    }

    if (!empty($errors)) {

        // Remove a new upload if the database operation cannot proceed.
        if ($uploadedAbsolutePath && is_file($uploadedAbsolutePath)) {
            unlink($uploadedAbsolutePath);
        }

        $_SESSION['portfolio_flash'] = [
            'type' => 'error',
            'message' => implode(' ', $errors)
        ];

        header("Location: $redirectUrl");
        exit;
    }

    // ========================================
    // SAVE NEW ARTWORK
    // ========================================

    if ($action === 'add_artwork') {

        $stmt = $con->prepare(
            'INSERT INTO portfolio
                (title, description, image_path, category, is_visible)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'ssssi',
            $title,
            $description,
            $newImagePath,
            $category,
            $isVisible
        );

        $saved = $stmt->execute();
        $stmt->close();

        if ($saved) {
            $_SESSION['portfolio_flash'] = [
                'type' => 'success',
                'message' => 'Artwork added successfully.'
            ];
        } else {
            if ($uploadedAbsolutePath && is_file($uploadedAbsolutePath)) {
                unlink($uploadedAbsolutePath);
            }

            $_SESSION['portfolio_flash'] = [
                'type' => 'error',
                'message' => 'Unable to save the artwork. Please try again.'
            ];
        }
    }

    // ========================================
    // UPDATE EXISTING ARTWORK
    // ========================================

    if ($action === 'edit_artwork') {

        // Keep the current image if no replacement was uploaded.
        $imagePathToSave = $newImagePath
            ?? $existingArtwork['image_path'];

        $stmt = $con->prepare(
            'UPDATE portfolio
             SET title = ?, description = ?, image_path = ?,
                 category = ?, is_visible = ?
             WHERE id = ?'
        );

        $stmt->bind_param(
            'ssssii',
            $title,
            $description,
            $imagePathToSave,
            $category,
            $isVisible,
            $artworkId
        );

        $saved = $stmt->execute();
        $stmt->close();

        if ($saved) {
            $_SESSION['portfolio_flash'] = [
                'type' => 'success',
                'message' => 'Artwork updated successfully.'
            ];

            // Remove the old local image only after the update succeeds.
            if ($newImagePath) {
                deletePortfolioImage($existingArtwork['image_path']);
            }
        } else {
            if ($uploadedAbsolutePath && is_file($uploadedAbsolutePath)) {
                unlink($uploadedAbsolutePath);
            }

            $_SESSION['portfolio_flash'] = [
                'type' => 'error',
                'message' => 'Unable to update the artwork. Please try again.'
            ];
        }
    }

    header("Location: $redirectUrl");
    exit;
}


// ========================================
// LOAD SERVICES AND ARTWORKS
// ========================================

$services = [];
$artworks = [];

$serviceResult = $con->query(
    'SELECT id, service_name, description, sample_image,
            base_price, estimated_days, status
     FROM services
     ORDER BY created_at DESC'
);

if ($serviceResult) {
    while ($row = $serviceResult->fetch_assoc()) {
        $services[] = $row;
    }
}

$portfolioResult = $con->query(
    'SELECT id, title, description, image_path,
            category, is_visible, created_at
     FROM portfolio
     ORDER BY created_at DESC'
);

if ($portfolioResult) {
    while ($row = $portfolioResult->fetch_assoc()) {
        $artworks[] = $row;
    }
}

$activeTab = ($_GET['tab'] ?? 'services') === 'portfolio'
    ? 'portfolio'
    : 'services';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../imgs/mascot.png">
    <title>My Services & Portfolio | Kvffie Arts</title>

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/my_services_portfolio.css">
    <script src="../script/my_services_portfolio.js" defer></script>

</head>

<body>

<div class="admin-layout">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">

        <header class="page-header">
            <p class="page-header__eyebrow">YOUR CREATIVE SPACE</p>
            <h1>My Services & Portfolio</h1>
            <p>Manage your services and showcase your finished artwork.</p>
        </header>

        <?php if ($flash): ?>
            <p class="notice notice--<?= escape($flash['type']) ?>"
               role="status">
                <?= escape($flash['message']) ?>
            </p>
        <?php endif; ?>

        <nav class="content-tabs" aria-label="Page sections" role="tablist">

            <button
                type="button"
                id="services-tab"
                class="content-tabs__button <?= $activeTab === 'services' ? 'active' : '' ?>"
                role="tab"
                aria-controls="services-panel"
                aria-selected="<?= $activeTab === 'services' ? 'true' : 'false' ?>"
                data-tab="services">
                My Services
            </button>

            <button
                type="button"
                id="portfolio-tab"
                class="content-tabs__button <?= $activeTab === 'portfolio' ? 'active' : '' ?>"
                role="tab"
                aria-controls="portfolio-panel"
                aria-selected="<?= $activeTab === 'portfolio' ? 'true' : 'false' ?>"
                data-tab="portfolio">
                My Portfolio
            </button>

        </nav>

        <!-- SERVICES TAB -->
        <section
            class="content-panel"
            id="services-panel"
            role="tabpanel"
            aria-labelledby="services-tab"
            data-panel="services"
            <?= $activeTab === 'services' ? '' : 'hidden' ?>>

            <header class="section-heading">
                <div>
                    <h2>Commission Services</h2>
                    <p>Manage the services clients can request.</p>
                </div>

                <a href="services_settings.php" class="button button--primary">
                    + Add Service
                </a>
            </header>

            <?php if (empty($services)): ?>

                <section class="empty-state">
                    <h3>No services yet</h3>
                    <p>Add your first commission service.</p>
                    <a href="services_settings.php" class="button button--primary">
                        Add Your First Service
                    </a>
                </section>

            <?php else: ?>

                <div class="service-grid">

                    <?php foreach ($services as $service): ?>

                        <article
                            class="service-card"
                            data-service-card
                            data-service-id="<?= (int) $service['id'] ?>"
                            data-service-title="<?= escape($service['service_name']) ?>"
                            data-service-description="<?= escape($service['description'] ?? '') ?>"
                            data-service-image="<?= escape(imagePath($service['sample_image'])) ?>"
                            data-service-price="<?= escape($service['base_price']) ?>"
                            data-service-days="<?= escape($service['estimated_days'] ?? '') ?>"
                            data-service-status="<?= escape($service['status']) ?>"
                            tabindex="0">

                            <figure class="service-card__image">
                                <?php if (!empty($service['sample_image'])): ?>
                                    <img
                                        src="<?= escape(imagePath($service['sample_image'])) ?>"
                                        alt="Sample for <?= escape($service['service_name']) ?>">
                                <?php else: ?>
                                    <figcaption>No sample image</figcaption>
                                <?php endif; ?>
                            </figure>

                            <div class="service-card__content">
                                <p class="service-card__status"
                                   data-status="<?= escape(strtolower($service['status'])) ?>">
                                    <?= escape($service['status']) ?>
                                </p>

                                <h3><?= escape($service['service_name']) ?></h3>

                                <p>
                                    Starting at ₱<?= number_format((float) $service['base_price'], 2) ?>
                                </p>

                                <p>
                                    Estimated time:
                                    <?= $service['estimated_days'] !== null
                                        ? (int) $service['estimated_days'] . ' days'
                                        : 'To be discussed' ?>
                                </p>

                                <a
                                    href="services_settings.php?id=<?= (int) $service['id'] ?>"
                                    class="button button--outline service-card__edit">
                                    Edit Service
                                </a>
                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <!-- PORTFOLIO TAB -->
        <section
            class="content-panel"
            id="portfolio-panel"
            role="tabpanel"
            aria-labelledby="portfolio-tab"
            data-panel="portfolio"
            <?= $activeTab === 'portfolio' ? '' : 'hidden' ?>>

            <header class="section-heading">
                <div>
                    <h2>Portfolio</h2>
                    <p>Showcase your completed creative work.</p>
                </div>

                <button
                    type="button"
                    class="button button--primary"
                    id="add-artwork-button">
                    + Add Artwork
                </button>
            </header>

            <?php if (empty($artworks)): ?>

                <section class="empty-state" id="portfolio-empty-state">
                    <h3>No artworks yet</h3>
                    <p>Your completed artworks will appear here.</p>

                    <button
                        type="button"
                        class="button button--primary"
                        id="first-artwork-button">
                        Post Your First Artwork
                    </button>
                </section>

            <?php else: ?>

                <div class="portfolio-grid">

                    <?php foreach ($artworks as $artwork): ?>

                        <article class="portfolio-card">

                            <figure class="portfolio-card__image">
                                <img
                                    src="<?= escape(imagePath($artwork['image_path'])) ?>"
                                    alt="<?= escape($artwork['title']) ?>"
                                    loading="lazy">
                            </figure>

                            <div class="portfolio-card__content">
                                <h3><?= escape($artwork['title']) ?></h3>

                                <?php if (!empty($artwork['category'])): ?>
                                    <p class="portfolio-card__category">
                                        <?= escape($artwork['category']) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="portfolio-card__visibility portfolio-card__visibility--<?= !empty($artwork['is_visible']) ? 'on' : 'off' ?>">
                                    <?= !empty($artwork['is_visible'])
                                        ? 'Visible to clients'
                                        : 'Hidden from clients' ?>
                                </p>

                                <div class="portfolio-card__actions">
                                    <button
                                        type="button"
                                        class="button button--outline edit-artwork-button"
                                        data-id="<?= (int) $artwork['id'] ?>"
                                        data-title="<?= escape($artwork['title']) ?>"
                                        data-category="<?= escape($artwork['category'] ?? '') ?>"
                                        data-description="<?= escape($artwork['description'] ?? '') ?>"
                                        data-image="<?= escape(imagePath($artwork['image_path'])) ?>"
                                        data-visible="<?= (int) $artwork['is_visible'] ?>">
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="button button--danger delete-artwork-button"
                                        data-id="<?= (int) $artwork['id'] ?>"
                                        data-title="<?= escape($artwork['title']) ?>">
                                        Delete
                                    </button>
                                </div>
                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>
</div>


<!-- ADD / EDIT ARTWORK POPUP -->
<dialog class="artwork-modal" id="artwork-modal">

    <form
        action="my_services_portfolio.php?tab=portfolio"
        method="POST"
        enctype="multipart/form-data"
        id="artwork-form">

        <header class="artwork-modal__header">
            <h2 id="artwork-modal-title">Add Artwork</h2>

            <button
                type="button"
                class="artwork-modal__close"
                data-close-artwork
                aria-label="Close form">
                &times;
            </button>
        </header>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="action" id="artwork-action" value="add_artwork">

        <input type="hidden" name="artwork_id" id="artwork-id" value="">

        <fieldset class="artwork-form__fields">

            <legend>Artwork details</legend>

            <label for="artwork-title">Artwork Title</label>
            <input
                type="text"
                name="title"
                id="artwork-title"
                maxlength="150"
                required>

            <label for="artwork-category">Category</label>
            <input
                type="text"
                name="category"
                id="artwork-category"
                maxlength="100"
                placeholder="e.g. Illustration, Character Art">

            <label for="artwork-description">Description</label>
            <textarea
                name="description"
                id="artwork-description"
                rows="4"
                maxlength="50000"
                placeholder="Describe your artwork..."></textarea>

            <label for="artwork-image">Artwork Image</label>
            <input
                type="file"
                name="artwork_image"
                id="artwork-image"
                accept="image/jpeg,image/png,image/webp,image/gif">

            <p class="form-help">
                JPG, PNG, WEBP, or GIF. Maximum file size: 5 MB.
                When editing, leave this blank to keep the current image.
            </p>

            <figure class="artwork-image-preview" id="artwork-image-preview" hidden>
                <img id="artwork-preview-image" src="" alt="Artwork preview">
                <figcaption>Image preview</figcaption>
            </figure>

            <label class="checkbox-label" for="artwork-visible">
                <input
                    type="checkbox"
                    name="is_visible"
                    id="artwork-visible"
                    value="1"
                    checked>
                Show this artwork to clients
            </label>

        </fieldset>

        <footer class="artwork-modal__footer">
            <button
                type="button"
                class="button button--outline"
                data-close-artwork>
                Cancel
            </button>

            <button
                type="submit"
                class="button button--primary"
                id="save-artwork-button">
                Save Artwork
            </button>
        </footer>

    </form>

</dialog>

<!-- DELETE CONFIRMATION DIALOG MODAL -->
<dialog class="service-modal service-modal--confirm" id="delete-modal">
    <div class="service-modal__content">
        <header class="service-modal__header">
            <h2>Confirm Deletion</h2>
            <button type="button" class="service-modal__close" id="close-delete-modal" aria-label="Close dialog">
                &times;
            </button>
        </header>

        <div class="service-modal__body">
            <p>Are you sure you want to delete <strong id="delete-service-name">this listing</strong>?</p>
            <p class="text-danger"><small>This action cannot be undone and will permanently remove the service listing.</small></p>
        </div>

        <footer class="service-modal__actions">
            <button type="button" class="button button--outline" id="cancel-delete-button">
                Cancel
            </button>
            <button type="button" class="button button--danger" id="confirm-delete-button">
                Yes, Delete Service
            </button>
        </footer>
    </div>
</dialog>


<!-- SERVICE DETAILS POPUP -->
<dialog class="service-modal" id="service-modal">

    <button
        type="button"
        class="service-modal__close"
        data-close-service
        aria-label="Close service details">
        &times;
    </button>

    <figure class="service-modal__image">
        <img id="modal-service-image" src="" alt="" hidden>
        <figcaption id="modal-image-placeholder">No sample image</figcaption>
    </figure>

    <section class="service-modal__details">
        <p id="modal-service-status"></p>
        <h2 id="modal-service-title">Service Name</h2>
        <p id="modal-service-description"></p>

        <p>
            Starting at <strong id="modal-service-price"></strong>
        </p>

        <p>
            Estimated time: <strong id="modal-service-days"></strong>
        </p>

        <a
            href="services_settings.php"
            id="modal-edit-service"
            class="button button--primary">
            Edit Service
        </a>
    </section>

</dialog>

</body>
</html>