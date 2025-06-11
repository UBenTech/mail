<?php
require_once 'includes/functions.php'; // This handles DB connection and loads $APP_SETTINGS
require_once 'includes/compose_functions.php'; // For save_campaign_to_db, get_all_templates_for_compose, get_template_content_for_compose

$all_templates = [];
$error_message = '';
$success_message = '';

// Form field values - prefill if available (e.g. from loaded template or POST error)
$campaign_name = $_POST['campaign_name'] ?? '';
$subject = $_POST['subject'] ?? '';
$body_html = $_POST['body_html'] ?? '';
$selected_template_id = $_POST['template_id'] ?? '';
$schedule_send = isset($_POST['schedule_send']);
$scheduled_at_value = $_POST['scheduled_at'] ?? '';


if (!isset($conn) || $conn === null) {
    $error_message = "Database connection failed. Please check configuration.";
} else {
    $all_templates = get_all_templates_for_compose($conn);

    // If a template is selected to be loaded via GET (and not a POST request which might have data)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['load_template_id'])) {
        $selected_template_id = $_GET['load_template_id'];
        if (!empty($selected_template_id)) {
            $template_content = get_template_content_for_compose($conn, $selected_template_id);
            if ($template_content) {
                $subject = $template_content['subject']; // Prefill subject
                $body_html = $template_content['body_html']; // Prefill body
                // Campaign name might also be prefilled based on template, e.g.
                // $campaign_name = "Campaign for " . $template_content['name']; // Assuming template name is available
            } else {
                $error_message = "Could not load selected template.";
                // Clear selected_template_id if it's invalid to prevent form from showing it as selected
                $selected_template_id = '';
            }
        }
    }

    // Handle POST request to save campaign
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // Values are already pre-filled from $_POST at the top
        // $campaign_name = trim($_POST['campaign_name'] ?? '');
        // $subject = trim($_POST['subject'] ?? '');
        // $body_html = trim($_POST['body_html'] ?? '');
        // $selected_template_id = $_POST['template_id'] ?? '';
        // $schedule_send = isset($_POST['schedule_send']);
        // $scheduled_at_value = $_POST['scheduled_at'] ?? '';

        $status = 'draft'; // Default status
        $scheduled_at_for_db = null;

        if ($action === 'save_draft') {
            $status = 'draft';
        } elseif ($action === 'schedule_campaign') {
            if (!$schedule_send || empty($scheduled_at_value)) {
                $error_message = "To schedule a campaign, please check 'Schedule Send' and select a date and time.";
            } else {
                $status = 'scheduled';
                $scheduled_at_for_db = $scheduled_at_value;
            }
        }
        // Add other actions like 'send_now' if needed

        if (empty($error_message)) { // Proceed if no scheduling error
            if (empty($campaign_name) || empty($subject) || empty($body_html)) {
                $error_message = "Campaign Name, Subject, and Email Body are required.";
            } else {
                $save_result = save_campaign_to_db($conn, $campaign_name, $subject, $body_html, $status, $scheduled_at_for_db);
                if ($save_result === true) {
                    $success_message = "Campaign '" . htmlspecialchars($campaign_name) . "' saved successfully as " . htmlspecialchars($status) . "!";
                    // Clear form fields after successful save
                    $campaign_name = $subject = $body_html = $selected_template_id = $scheduled_at_value = '';
                    $schedule_send = false;
                } else {
                    $error_message = is_string($save_result) ? $save_result : "Failed to save campaign.";
                }
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Compose New Campaign</h1>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!isset($conn) || $conn === null): ?>
        <div class="alert alert-danger">Database connection is not available. Cannot load or save campaigns.</div>
    <?php else: ?>
    <form id="composeForm" method="POST" action="compose.php">
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="campaign_name" class="form-label">Campaign Name</label>
                    <input type="text" class="form-control" id="campaign_name" name="campaign_name" value="<?php echo htmlspecialchars($campaign_name); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Email Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="template_id" class="form-label">Load from Template</label>
                    <select class="form-select" id="template_id" name="template_id">
                        <option value="">-- Select a Template --</option>
                        <?php if (!empty($all_templates)): ?>
                            <?php foreach ($all_templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template['id']); ?>" <?php echo ($selected_template_id == $template['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="body_html" class="form-label">Email Body</label>
                    <textarea class="form-control" id="body_html" name="body_html" rows="15" required><?php echo htmlspecialchars($body_html); ?></textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h5>Actions & Options</h5></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="schedule_send_checkbox" name="schedule_send" <?php echo $schedule_send ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="schedule_send_checkbox">Schedule Send</label>
                        </div>
                        <div class="mb-3" id="schedule_options" style="<?php echo $schedule_send ? '' : 'display: none;'; ?>">
                            <label for="scheduled_at" class="form-label">Schedule Date & Time</label>
                            <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="<?php echo htmlspecialchars($scheduled_at_value); ?>">
                        </div>
                        <hr>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="save_draft" class="btn btn-secondary"><i class="fas fa-save"></i> Save Draft</button>
                            <button type="submit" name="action" value="schedule_campaign" class="btn btn-primary"><i class="fas fa-clock"></i> Schedule Campaign</button>
                            <!-- <button type="submit" name="action" value="send_now" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send Now</button> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; // End $conn check ?>
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const templateSelect = document.getElementById('template_id');
        const scheduleCheckbox = document.getElementById('schedule_send_checkbox');
        const scheduleOptionsDiv = document.getElementById('schedule_options');
        const campaignNameInput = document.getElementById('campaign_name');
        const subjectInput = document.getElementById('subject');
        const bodyHtmlTextarea = document.getElementById('body_html');

        if (templateSelect) {
            templateSelect.addEventListener('change', function() {
                const templateId = this.value;
                const currentCampaignName = campaignNameInput.value;
                const currentSubject = subjectInput.value;
                const currentBody = bodyHtmlTextarea.value;

                let params = new URLSearchParams();
                if (templateId) {
                    params.append('load_template_id', templateId);
                }
                // Preserve current form data in URL to repopulate after reload
                if (currentCampaignName) params.append('campaign_name', currentCampaignName);
                if (currentSubject && templateId) params.append('preserve_subject', currentSubject); // only preserve subject if loading new template
                if (currentBody && templateId) params.append('preserve_body', currentBody); // only preserve body if loading new template


                // If user selects "-- Select a Template --"
                if (!templateId) {
                    // Optionally, you might want to clear the subject and body if the user deselects a template
                    // subjectInput.value = '';
                    // bodyHtmlTextarea.value = '';
                    // Or simply reload without load_template_id to reset to blank/POST state
                    window.location.href = 'compose.php?' + params.toString();

                } else {
                     window.location.href = 'compose.php?' + params.toString();
                }

            });
        }

        // Repopulate form fields from URL parameters if they exist (after template load)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('load_template_id')) { // Only do this if a template was just loaded
            if (urlParams.has('campaign_name') && campaignNameInput.value === '') { // only if not already filled by PHP (e.g. POST error)
                campaignNameInput.value = urlParams.get('campaign_name');
            }
             // Subject and Body are filled by PHP based on template_id
             // However, if we had 'preserve_subject' or 'preserve_body', this means user typed something THEN changed template.
             // PHP would have loaded template. If we want to override with what user typed before selecting template:
            // if (urlParams.has('preserve_subject')) subjectInput.value = urlParams.get('preserve_subject');
            // if (urlParams.has('preserve_body')) bodyHtmlTextarea.value = urlParams.get('preserve_body');
        }


        if (scheduleCheckbox) {
            scheduleCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    scheduleOptionsDiv.style.display = 'block';
                } else {
                    scheduleOptionsDiv.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>
