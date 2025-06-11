<?php
require_once 'includes/functions.php'; // This handles DB connection and loads $APP_SETTINGS
require_once 'includes/compose_functions.php'; // For campaign functions

global $conn; // Ensure $conn is in scope
global $APP_SETTINGS; // Ensure $APP_SETTINGS is in scope

$page_error_message = ''; // Renamed from $error_message to avoid conflicts if any other include uses it
$page_success_message = ''; // Renamed from $success_message

// Form field values - prefill if available
$campaign_name = '';
$subject = '';
$body_html = '';
$selected_template_id = ''; // Template selection might be disabled or handled differently in edit mode
$schedule_send = false;
$scheduled_at_value = '';
$current_campaign_status = ''; // To store the status of the campaign being edited

// Edit mode variables
$edit_mode = false;
$campaign_id_to_edit = null;
$page_title = "Compose New Campaign"; // Default page title

// Check for edit mode (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_campaign_id'])) {
    if (!empty($_GET['edit_campaign_id']) && is_numeric($_GET['edit_campaign_id'])) {
        $campaign_id_to_edit = (int)$_GET['edit_campaign_id'];

        if (isset($conn) && $conn instanceof mysqli && $conn->ping()) { // Check $conn validity
            $campaign_data = get_campaign_by_id($conn, $campaign_id_to_edit);
            if ($campaign_data) {
                $edit_mode = true;
                $page_title = "Edit Campaign: " . htmlspecialchars($campaign_data['name']);

                // Pre-fill form variables from fetched campaign data
                $campaign_name = $campaign_data['name'];
                $subject = $campaign_data['subject'];
                $body_html = $campaign_data['body_html'];
                $current_campaign_status = $campaign_data['status']; // Store current status

                if ($campaign_data['status'] === 'scheduled' && !empty($campaign_data['scheduled_at'])) {
                    $schedule_send = true;
                    // Format for datetime-local input: YYYY-MM-DDTHH:MM
                    try {
                        $dt = new DateTime($campaign_data['scheduled_at']);
                        $scheduled_at_value = $dt->format('Y-m-d\TH:i');
                    } catch (Exception $e) {
                        $scheduled_at_value = '';
                        $page_error_message .= " Error parsing scheduled date for editing. ";
                    }
                } else {
                    $schedule_send = false;
                    $scheduled_at_value = '';
                }
            } else {
                $page_error_message = "Campaign not found for editing (ID: " . htmlspecialchars($campaign_id_to_edit) . ").";
                $campaign_id_to_edit = null;
            }
        } else {
            $page_error_message = "Database connection error. Cannot load campaign for editing.";
            $campaign_id_to_edit = null;
        }
    } else {
        $page_error_message = "Invalid campaign ID for editing.";
    }
}

// Handle loading template content if requested by GET parameter
// This is processed after 'edit_campaign_id' so template can overwrite subject/body from loaded campaign.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['load_template_id']) && is_numeric($_GET['load_template_id'])) {
    $loaded_template_id_from_get = (int)$_GET['load_template_id'];

    // Set $selected_template_id so the dropdown re-selects the loaded template
    $selected_template_id = $loaded_template_id_from_get;

    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $template_content = get_template_content_for_compose($conn, $loaded_template_id_from_get);
        if ($template_content) {
            // Overwrite subject and body with template content
            // $campaign_name is NOT overwritten here by template, preserving existing name (either from edit mode or campaign_name_preserve)
            $subject = $template_content['subject'];
            $body_html = $template_content['body_html'];

            // Optionally, if you want to also set campaign_name from template *if* campaign_name is empty:
            // if (empty($campaign_name) && !empty($template_content['name'])) { // Assuming get_template_content_for_compose could return 'name'
            //    $campaign_name = $template_content['name'];
            // }

        } else {
            // Append to existing error messages if any
            $page_error_message .= " Failed to load content for selected template (ID: " . htmlspecialchars($loaded_template_id_from_get) . "). ";
        }
    } else {
        $page_error_message .= " Database error: Cannot load template content when processing load_template_id. ";
    }
}

// Handle preserved campaign name (e.g., after template selection for a new campaign, or if passed in URL with edit)
// This should apply if $campaign_name is currently empty (i.e., not in edit mode where it's prefilled from DB,
// or if template loading didn't set it).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['campaign_name_preserve'])) {
    if (empty($campaign_name)) { // Only pre-fill if $campaign_name wasn't set by 'edit_campaign_id'
        $campaign_name = trim($_GET['campaign_name_preserve']);
    }
}


// Handle POST request (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Retrieve all form fields from POST to repopulate form if error or for saving
    $campaign_name = trim($_POST['campaign_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body_html = trim($_POST['body_html'] ?? '');
    $selected_template_id = $_POST['template_id'] ?? '';
    $schedule_send = isset($_POST['schedule_send']);
    $scheduled_at_value = $_POST['scheduled_at'] ?? '';
    $action = $_POST['action'];

    // Determine if we are in edit mode for this POST submission
    $campaign_id_for_saving = null;
    if (isset($_POST['campaign_id']) && is_numeric($_POST['campaign_id']) && $_POST['campaign_id'] > 0) {
        $campaign_id_for_saving = (int)$_POST['campaign_id'];
        $edit_mode = true;
        $campaign_id_to_edit = $campaign_id_for_saving; // Ensure $campaign_id_to_edit is set for POST context
         // Update page title for edit mode if it wasn't set by GET (e.g. POST error on edit)
        if ($page_title === "Compose New Campaign" || strpos($page_title, (string)$campaign_id_for_saving) === false) {
            $temp_campaign_name_for_title = !empty($campaign_name) ? $campaign_name : null;
            if (empty($temp_campaign_name_for_title) && isset($conn) && $conn instanceof mysqli && $conn->ping()){
                 $temp_data = get_campaign_by_id($conn, $campaign_id_for_saving);
                 if($temp_data) $temp_campaign_name_for_title = $temp_data['name'];
            }
            $page_title = "Edit Campaign: " . (!empty($temp_campaign_name_for_title) ? htmlspecialchars($temp_campaign_name_for_title) : "ID " . htmlspecialchars($campaign_id_for_saving));
        }
    } else {
        $edit_mode = false;
    }

    $status = 'draft';
    $scheduled_at_for_db = null;

    if ($action === 'save_draft') {
        $status = 'draft';
    } elseif ($action === 'schedule_campaign') {
        if (!$schedule_send || empty($scheduled_at_value)) {
            $page_error_message = "To schedule a campaign, please check 'Schedule Send' and select a date and time.";
        } else {
            $status = 'scheduled';
            $scheduled_at_for_db = $scheduled_at_value;
        }
    } elseif ($action === 'send_now') {
        $status = 'sent';
    }

    if (empty($page_error_message)) {
        if (empty($campaign_name) || empty($subject) || empty($body_html)) {
            $page_error_message = "Campaign Name, Subject, and Email Body are required.";
        } else {
            $save_result_mixed = save_campaign_to_db(
                $conn,
                $campaign_name,
                $subject,
                $body_html,
                $status,
                $scheduled_at_for_db,
                $campaign_id_for_saving // Pass the ID if we are editing
            );

            if (is_numeric($save_result_mixed) && $save_result_mixed > 0) {
                $returned_campaign_id = (int)$save_result_mixed;
                $action_verb = '';

                if ($edit_mode) {
                    if ($returned_campaign_id === $campaign_id_for_saving) {
                        $action_verb = 'updated';
                    } else {
                        $action_verb = 'processed (unexpected ID)';
                    }
                } else {
                    $action_verb = 'created';
                }

                // Refine action_verb based on status for more specific feedback
                if ($status === 'sent' && $action_verb !== 'processed (unexpected ID)') $action_verb = 'sent';
                else if ($status === 'scheduled' && $action_verb !== 'processed (unexpected ID)') $action_verb = 'scheduled';
                // If 'draft', it will use 'created' or 'updated' which is fine.

                $page_success_message = "Campaign '" . htmlspecialchars($campaign_name) . "' " . $action_verb . " successfully (ID: " . $returned_campaign_id . ")!";

                if (!$edit_mode) { // New campaign successfully created
                    $campaign_name = $subject = $body_html = $selected_template_id = $scheduled_at_value = '';
                    $schedule_send = false;
                } else { // Existing campaign successfully updated
                    // Re-load data for the current campaign to show updated values
                    $campaign_data_reloaded = get_campaign_by_id($conn, $returned_campaign_id);
                    if ($campaign_data_reloaded) {
                        // $campaign_name, $subject, $body_html are already from $_POST (latest submission)
                        // Update status and schedule related fields from what's now in DB
                        $current_campaign_status = $campaign_data_reloaded['status'];
                        if ($campaign_data_reloaded['status'] === 'scheduled' && !empty($campaign_data_reloaded['scheduled_at'])) {
                            $schedule_send = true;
                            try {
                                $dt = new DateTime($campaign_data_reloaded['scheduled_at']);
                                $scheduled_at_value = $dt->format('Y-m-d\TH:i');
                            } catch (Exception $e) { $scheduled_at_value = ''; }
                        } else {
                            $schedule_send = false;
                            $scheduled_at_value = '';
                        }
                        // Update page title again in case name changed during update
                        $page_title = "Edit Campaign: " . htmlspecialchars($campaign_data_reloaded['name']);
                    }
                }
            } else {
                $page_error_message = is_string($save_result_mixed) ? $save_result_mixed : "Failed to save/update campaign due to an unknown error.";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // Fallback for non-GET, non-action POST (e.g. if JS disabled and form submitted unexpectedly)
    // Repopulate form from whatever was POSTed to maintain user input.
    $campaign_name = trim($_POST['campaign_name'] ?? $campaign_name);
    $subject = trim($_POST['subject'] ?? $subject);
    $body_html = trim($_POST['body_html'] ?? $body_html);
    $selected_template_id = $_POST['template_id'] ?? $selected_template_id;
    $schedule_send = isset($_POST['schedule_send']) ? true : $schedule_send; // Keep submitted state
    $scheduled_at_value = $_POST['scheduled_at'] ?? $scheduled_at_value; // Keep submitted state
    // If campaign_id was in POST, ensure edit mode is still considered
    if(isset($_POST['campaign_id']) && is_numeric($_POST['campaign_id'])) {
        $campaign_id_to_edit = (int)$_POST['campaign_id'];
        $edit_mode = true;
        if ($page_title === "Compose New Campaign") { // Update title if needed
             $page_title = "Edit Campaign (ID: " . htmlspecialchars($campaign_id_to_edit) . ")";
        }
    }
}


// Fetch all templates for the dropdown, regardless of mode (new/edit)
// This should be done after any potential DB connection check
if (isset($conn) && $conn instanceof mysqli && $conn->ping() && empty($page_error_message)) { // Check $conn and avoid if previous error
    $all_templates = get_all_templates_for_compose($conn);
} elseif (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
    if(empty($page_error_message)) $page_error_message = "Database connection failed. Cannot load templates.";
}

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $page_title; ?></h1>
    </div>

    <?php if (!empty($page_success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($page_success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($page_error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $page_error_message; // May contain HTML like <br> from multiple errors ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping() && empty($page_error_message)): // If $conn error but $page_error_message already shown, don't repeat generic DB error ?>
        <div class="alert alert-danger">Database connection is not available. Cannot load or save campaigns.</div>
    <?php else: ?>
    <form id="composeForm" method="POST" action="compose.php<?php echo $edit_mode ? '?edit_campaign_id=' . htmlspecialchars($campaign_id_to_edit) : ''; ?>">
        <?php if ($edit_mode && !empty($campaign_id_to_edit)): ?>
            <input type="hidden" name="campaign_id" value="<?php echo htmlspecialchars($campaign_id_to_edit); ?>">
        <?php endif; ?>
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
                    <select class="form-select" id="template_id" name="template_id" <?php if ($edit_mode && $current_campaign_status !== 'draft') echo 'disabled'; ?>>
                        <option value="">-- Select a Template --</option>
                        <?php if (!empty($all_templates)): ?>
                            <?php foreach ($all_templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template['id']); ?>" <?php echo ($selected_template_id == $template['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                     <?php if ($edit_mode && $current_campaign_status !== 'draft'): ?>
                        <small class="form-text text-muted">Template selection is disabled for campaigns that are not drafts. Body can be edited directly.</small>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="body_html" class="form-label">Email Body</label>
                    <textarea class="form-control" id="body_html" name="body_html" rows="15" required <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'readonly'; ?>><?php echo htmlspecialchars($body_html); ?></textarea>
                     <?php if ($edit_mode && $current_campaign_status === 'sent'): ?>
                        <small class="form-text text-muted">Body of a sent campaign cannot be edited.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h5>Actions & Options</h5></div>
                    <div class="card-body">
                        <?php if ($edit_mode): ?>
                             <p><strong>Status:</strong> <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($current_campaign_status)); ?></span></p>
                             <?php if ($current_campaign_status === 'sent'): ?>
                                <p class="text-muted"><small>This campaign has already been sent. Most fields cannot be modified. You might consider cloning it to a new campaign if changes are needed.</small></p>
                             <?php endif; ?>
                        <?php endif; ?>

                        <div class="form-check form-switch mb-3" <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'style="display:none;"'; ?>>
                            <input class="form-check-input" type="checkbox" id="schedule_send_checkbox" name="schedule_send" <?php echo $schedule_send ? 'checked' : ''; ?> <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'disabled'; ?>>
                            <label class="form-check-label" for="schedule_send_checkbox">Schedule Send</label>
                        </div>
                        <div class="mb-3" id="schedule_options" style="<?php echo $schedule_send ? '' : 'display: none;'; ?> <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'display:none;'; ?>">
                            <label for="scheduled_at" class="form-label">Schedule Date & Time</label>
                            <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="<?php echo htmlspecialchars($scheduled_at_value); ?>" <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'disabled'; ?>>
                        </div>
                        <hr <?php if ($edit_mode && $current_campaign_status === 'sent') echo 'style="display:none;"'; ?>>
                        <div class="d-grid gap-2">
                            <?php if (!$edit_mode || ($edit_mode && $current_campaign_status !== 'sent')): ?>
                                <button type="submit" name="action" value="send_now" class="btn btn-success mb-2" <?php if ($edit_mode && $current_campaign_status === 'scheduled') echo 'disabled'; /* Maybe allow re-scheduling or unscheduling first */ ?>><i class="fas fa-paper-plane"></i> <?php echo $edit_mode ? 'Send Now (if not already sent)' : 'Send Immediately'; ?></button>
                                <button type="submit" name="action" value="schedule_campaign" class="btn btn-primary mb-2"><i class="fas fa-clock"></i> <?php echo ($edit_mode && $current_campaign_status === 'scheduled') ? 'Update Schedule' : 'Save and Schedule'; ?></button>
                                <button type="submit" name="action" value="save_draft" class="btn btn-secondary"><i class="fas fa-save"></i> <?php echo $edit_mode ? 'Save Changes as Draft' : 'Save Draft'; ?></button>
                            <?php else: // Campaign is 'sent' ?>
                                <a href="compose.php" class="btn btn-primary">Compose New Campaign</a>
                                <!-- Or a "Clone Campaign" button could be here -->
                            <?php endif; ?>
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
        // const subjectInput = document.getElementById('subject'); // Not directly used in this version of JS
        // const bodyHtmlTextarea = document.getElementById('body_html'); // Not directly used

        // JS for template loading (primarily for new campaigns)
        if (templateSelect && !templateSelect.disabled) { // Only add listener if not disabled (i.e. new or draft)
            templateSelect.addEventListener('change', function() {
                const templateId = this.value;
                const currentCampaignName = campaignNameInput ? campaignNameInput.value : '';
                // const currentSubject = subjectInput ? subjectInput.value : ''; // If needed to preserve
                // const currentBody = bodyHtmlTextarea ? bodyHtmlTextarea.value : ''; // If needed to preserve

                let params = new URLSearchParams(window.location.search); // Preserve existing GET params like edit_campaign_id

                if (templateId) {
                    params.set('load_template_id', templateId); // Use .set to override if already there
                } else {
                    params.delete('load_template_id');
                }
                // To preserve other fields, get their current values and add to params
                // For example, to preserve campaign name if user typed it then selected template:
                if (currentCampaignName) {
                    params.set('campaign_name_preserve', currentCampaignName);
                }
                // Add more fields to preserve if necessary

                window.location.href = 'compose.php?' + params.toString();
            });
        }

        // Repopulate campaign_name if preserved in URL (e.g., after template load)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('campaign_name_preserve') && campaignNameInput && campaignNameInput.value === '') {
            campaignNameInput.value = urlParams.get('campaign_name_preserve');
        }
        // PHP should handle prefilling subject and body from template or DB, so JS might not need to.

        if (scheduleCheckbox) {
            scheduleCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    scheduleOptionsDiv.style.display = 'block';
                } else {
                    scheduleOptionsDiv.style.display = 'none';
                }
            });
             // Initial state based on checkbox, important if page is reloaded with error or prefilled
            if (scheduleCheckbox.checked) {
                scheduleOptionsDiv.style.display = 'block';
            } else {
                scheduleOptionsDiv.style.display = 'none';
            }
        }
    });
    </script>
</body>
</html>
