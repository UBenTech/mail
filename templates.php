<?php
require_once 'includes/functions.php'; // This handles DB connection and loads $APP_SETTINGS
require_once 'includes/templates_functions.php'; // For template functions

// Initialize variables
$all_templates = []; // Default to empty array
$edit_template = null;
$error_message = '';
$success_message = '';

// Ensure $conn is available, otherwise, display an error and exit.
if (!isset($conn) || $conn === null) {
    // This is a critical error, templates page cannot function without DB.
    $error_message = "Database connection failed. Please check configuration.";
    // In a real app, you might log this error and show a more user-friendly page.
} else {
    // Proceed with database operations only if $conn is valid
    $all_templates = get_all_templates($conn);

    // Handle POST requests for create, update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            // Basic validation (can be enhanced)
            if (empty($_POST['name']) || empty($_POST['subject']) || empty($_POST['body_html'])) {
                $error_message = "Name, Subject, and Body HTML are required.";
            } else {
                if ($action === 'create_template') {
                    if (create_template($conn, $_POST['name'], $_POST['subject'], $_POST['body_html'])) {
                        // $success_message = "Template created successfully!";
                        // $all_templates = get_all_templates($conn); // Refresh list
                        header("Location: templates.php?created=success");
                        exit;
                    } else {
                        $error_message = "Failed to create template. Error: " . $conn->error;
                    }
                } elseif ($action === 'update_template' && isset($_POST['template_id'])) {
                    if (update_template($conn, $_POST['template_id'], $_POST['name'], $_POST['subject'], $_POST['body_html'])) {
                        // $success_message = "Template updated successfully!";
                        // $all_templates = get_all_templates($conn); // Refresh list
                        header("Location: templates.php?updated=success");
                        exit;
                    } else {
                        $error_message = "Failed to update template. Error: " . $conn->error;
                    }
                }
            }
        }
    }

    // Handle GET requests for delete and edit view
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        if ($action === 'delete_template' && isset($_GET['id'])) {
            if (delete_template($conn, $_GET['id'])) {
                // $success_message = "Template deleted successfully!";
                // $all_templates = get_all_templates($conn); // Refresh list
                // Redirect to clean URL after delete to prevent re-deletion on refresh
                header("Location: templates.php?deleted=success");
                exit;
            } else {
                // $error_message = "Failed to delete template. Error: " . $conn->error;
                header("Location: templates.php?deleted=error");
                exit;
            }
        } elseif ($action === 'edit_template' && isset($_GET['id'])) {
            $edit_template = get_template_by_id($conn, $_GET['id']);
            if (!$edit_template) {
                $error_message = "Template not found for editing.";
            }
        }
    }

    // Display messages based on GET parameters from redirects
    if(isset($_GET['created']) && $_GET['created'] == 'success') $success_message = "Template created successfully!";
    if(isset($_GET['updated']) && $_GET['updated'] == 'success') $success_message = "Template updated successfully!";
    if(isset($_GET['deleted']) && $_GET['deleted'] == 'success') $success_message = "Template deleted successfully!";
    if(isset($_GET['deleted']) && $_GET['deleted'] == 'error') $error_message = "Failed to delete template.";

} // Closing bracket for `if ($conn)`
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content for templates.php -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Email Templates</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="templates.php?action=show_create_form" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Template
            </a>
        </div>
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

    <!-- Form for Create/Edit -->
    <?php
    $show_form = false;
    if ((isset($_GET['action']) && $_GET['action'] === 'edit_template' && $edit_template) ||
        (isset($_GET['action']) && $_GET['action'] === 'show_create_form')) {
        $show_form = true;
    }

    if ($show_form && isset($conn) && $conn !== null): // Only show form if DB connection is OK
        $form_action_url = 'templates.php'; // Submit to the same page
        $form_method = 'POST';
        $form_page_action = $edit_template ? 'update_template' : 'create_template';
        $form_title = $edit_template ? 'Edit Template' : 'Create New Template';
        $submit_button_text = $edit_template ? 'Update Template' : 'Create Template';
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><?php echo $form_title; ?></h5>
        </div>
        <div class="card-body">
            <form action="<?php echo $form_action_url; ?>" method="<?php echo $form_method; ?>">
                <input type="hidden" name="action" value="<?php echo $form_page_action; ?>">
                <?php if ($edit_template): ?>
                    <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($edit_template['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Template Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($edit_template['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Email Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($edit_template['subject'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="body_html" class="form-label">Body HTML</label>
                    <textarea class="form-control" id="body_html" name="body_html" rows="10" required><?php echo htmlspecialchars($edit_template['body_html'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><?php echo $submit_button_text; ?></button>
                <a href="templates.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <?php elseif ($show_form && (!isset($conn) || $conn === null)): ?>
        <div class='alert alert-warning'>Cannot display form due to database connection issue.</div>
    <?php endif; // End of $show_form ?>

    <!-- Table of Templates -->
    <div class="card">
        <div class="card-header">
            <h5>Existing Templates</h5>
        </div>
        <div class="card-body">
            <?php if (isset($conn) && $conn !== null): // Only show table if DB connection is OK ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_templates)): ?>
                            <?php foreach ($all_templates as $template): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($template['name']); ?></td>
                                    <td><?php echo htmlspecialchars($template['subject']); ?></td>
                                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($template['created_at']))); ?></td>
                                    <td>
                                        <a href="templates.php?action=edit_template&id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="templates.php?action=delete_template&id=<?php echo $template['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this template?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No templates found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class='alert alert-warning'>Cannot display templates due to database connection issue.</div>
            <?php endif; // End of if $conn check for table display ?>
        </div>
    </div>
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
