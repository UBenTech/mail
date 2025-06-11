<?php
require_once __DIR__ . '/includes/functions.php';     // For $conn, $APP_SETTINGS, and helper functions
require_once __DIR__ . '/includes/compose_functions.php'; // For get_campaign_by_id()

global $conn; // Ensure $conn is in scope
global $APP_SETTINGS; // Ensure $APP_SETTINGS is in scope

$page_title = "View Campaign"; // Default title
$campaign_data = null;
$error_message = '';
$campaign_id = null;

if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
    $campaign_id = (int)$_GET['id'];

    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $campaign_data = get_campaign_by_id($conn, $campaign_id); // Now fetches created_at, sent_at

        if ($campaign_data) {
            $page_title = "View Campaign: " . htmlspecialchars($campaign_data['name']);
        } else {
            http_response_code(404); // Not Found
            $error_message = "Campaign with ID " . htmlspecialchars($campaign_id) . " not found.";
            $page_title = "Campaign Not Found";
        }
    } else {
        http_response_code(500); // Internal Server Error
        $error_message = "Database connection error. Cannot retrieve campaign details.";
        $page_title = "Database Error";
    }
} else {
    http_response_code(400); // Bad Request
    $error_message = "No campaign ID provided or ID is invalid.";
    $page_title = "Invalid Request";
}

// Include header
include __DIR__ . '/includes/header.php';
// Include sidebar
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content for campaign_view.php -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
        <?php if ($campaign_data): ?>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php if (in_array(strtolower($campaign_data['status']), ['draft', 'scheduled'])): ?>
                    <a href="compose.php?edit_campaign_id=<?php echo htmlspecialchars($campaign_data['id']); ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-edit"></i> Edit Campaign
                    </a>
                <?php else: ?>
                    <a href="compose.php?clone_campaign_id=<?php echo htmlspecialchars($campaign_data['id']); ?>" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-clone"></i> Clone Campaign
                    </a>
                    <?php // Clone functionality is not yet implemented, this is a placeholder link. ?>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-sm btn-outline-dark">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php elseif ($campaign_data): ?>
        <div class="card">
            <div class="card-header">
                <h5>Campaign Details</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">ID:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($campaign_data['id']); ?></dd>

                    <dt class="col-sm-3">Name:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($campaign_data['name']); ?></dd>

                    <dt class="col-sm-3">Subject:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($campaign_data['subject']); ?></dd>

                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-<?php
                            $status_class = 'secondary'; // Default
                            switch (strtolower($campaign_data['status'])) {
                                case 'sent': $status_class = 'success'; break;
                                case 'scheduled': $status_class = 'warning'; break;
                                case 'draft': $status_class = 'primary'; break;
                                case 'failed': $status_class = 'danger'; break;
                            }
                            echo $status_class;
                        ?>"><?php echo htmlspecialchars(ucfirst($campaign_data['status'])); ?></span>
                    </dd>

                    <?php if (!empty($campaign_data['created_at'])): ?>
                    <dt class="col-sm-3">Created At:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars(date("Y-m-d H:i A T", strtotime($campaign_data['created_at']))); ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($campaign_data['scheduled_at'])): ?>
                    <dt class="col-sm-3">Scheduled At:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars(date("Y-m-d H:i A T", strtotime($campaign_data['scheduled_at']))); ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($campaign_data['sent_at'])): ?>
                    <dt class="col-sm-3">Sent At:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars(date("Y-m-d H:i A T", strtotime($campaign_data['sent_at']))); ?></dd>
                    <?php endif; ?>
                </dl>

                <h6 class="mt-4">Email Body Preview:</h6>
                <div class="email-body-preview-container border bg-light" style="height: 500px; resize: vertical; overflow: auto;">
                    <iframe
                        sandbox="" /* Enables all restrictions: no scripts, no forms, no popups, no same-origin access etc. */
                        srcdoc="<?php echo htmlspecialchars($campaign_data['body_html']); ?>"
                        style="width: 100%; height: 100%; border: none;"
                        loading="lazy">
                        Your browser does not support iframes or it is disabled.
                    </iframe>
                </div>
                <small class="text-muted">Preview is sandboxed for security. Actual email rendering may vary in email clients.</small>
            </div>
        </div>
    <?php else:
        // This case should ideally not be reached if error_message is properly set for not found/invalid ID
        // or if $campaign_data is null due to DB error and error_message is set.
        // However, as a fallback:
        ?>
        <div class="alert alert-info" role="alert">
            No campaign data to display. This might be due to an earlier error or an empty campaign.
        </div>
    <?php endif; ?>

</main>

<?php
// Include footer (standard scripts, closing body/html tags)
?>
    </div> <!-- .row -->
</div> <!-- .container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
