<?php
require_once 'includes/functions.php'; // This handles DB connection and loads $APP_SETTINGS
require_once 'includes/contacts_functions.php'; // For contact functions

// Initialize variables
$all_contacts = [];
$edit_contact = null;
$error_message = '';
$success_message = '';
$possible_statuses = ['subscribed', 'unsubscribed', 'pending', 'bounced']; // Define possible statuses

if (!isset($conn) || $conn === null) {
    $error_message = "Database connection failed. Please check configuration.";
} else {
    // Initial fetch of all contacts
    // This might be refreshed after POST operations if no redirect occurs due to error
    if (empty($error_message)) { // Avoid DB call if connection already failed
        $all_contacts = get_all_contacts($conn);
    }


    // Handle POST requests for create, update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $status = $_POST['status'] ?? 'subscribed'; // Default status

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "A valid email address is required.";
        } elseif (!in_array($status, $possible_statuses)) {
            $error_message = "Invalid status selected.";
        } else {
            if ($action === 'create_contact') {
                $create_result = create_contact($conn, $email, $first_name, $last_name, $status);
                if ($create_result === true) {
                    header("Location: contacts.php?created=success");
                    exit;
                } else {
                    $error_message = is_string($create_result) ? $create_result : "Failed to create contact. Database error.";
                }
            } elseif ($action === 'update_contact' && isset($_POST['contact_id'])) {
                $contact_id = $_POST['contact_id'];
                $update_result = update_contact($conn, $contact_id, $email, $first_name, $last_name, $status);
                if ($update_result === true) {
                   header("Location: contacts.php?updated=success");
                   exit;
                } else {
                    $error_message = is_string($update_result) ? $update_result : "Failed to update contact. Database error.";
                }
            }
        }
         // If there was an error, repopulate form fields for editing or creating
        if (!empty($error_message)) {
            if ($action === 'update_contact' && isset($_POST['contact_id'])) {
                // For update error, repopulate $edit_contact to keep form in edit mode
                // And ensure it's an array, not null, to avoid errors in form value population
                $edit_contact = get_contact_by_id($conn, $_POST['contact_id']); // Re-fetch to ensure it's populated
                if ($edit_contact) { // if contact still exists
                    $edit_contact['email'] = $email; // Overwrite with submitted values
                    $edit_contact['first_name'] = $first_name;
                    $edit_contact['last_name'] = $last_name;
                    $edit_contact['status'] = $status;
                } else {
                     // If somehow the contact was deleted during the error, treat as create error
                    $_GET['action'] = 'show_create_form';
                }
            } elseif ($action === 'create_contact') {
                // For create error, set flag to show create form.
                // Values will be picked up by `htmlspecialchars($_POST['field'] ?? ...)` in the form
                $_GET['action'] = 'show_create_form';
            }
        }
        // Refresh list if an error occurred and we didn't redirect
        if(!empty($error_message)) {
            $all_contacts = get_all_contacts($conn);
        }
    }


    // Handle GET requests for delete and edit view (if not a POST request that failed)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
        if ($action === 'delete_contact' && isset($_GET['id'])) {
            if (delete_contact($conn, $_GET['id'])) {
                header("Location: contacts.php?deleted=success");
                exit;
            } else {
                // $error_message = "Failed to delete contact. Error: " . $conn->error; // $conn->error might not be set by function
                header("Location: contacts.php?deleted=error&msg=" . urlencode($conn->error ?? 'Unknown error'));
                exit;
            }
        } elseif ($action === 'edit_contact' && isset($_GET['id'])) {
            $edit_contact = get_contact_by_id($conn, $_GET['id']);
            if (!$edit_contact) {
                $error_message = "Contact not found for editing.";
                // Clear edit_contact if not found to prevent form from trying to populate with null
                $edit_contact = null;
            }
        }
    }

    // Display messages based on GET parameters from redirects
    if(isset($_GET['created']) && $_GET['created'] == 'success') $success_message = "Contact created successfully!";
    if(isset($_GET['updated']) && $_GET['updated'] == 'success') $success_message = "Contact updated successfully!";
    if(isset($_GET['deleted']) && $_GET['deleted'] == 'success') $success_message = "Contact deleted successfully!";
    if(isset($_GET['deleted']) && $_GET['deleted'] == 'error') {
        $error_message = "Failed to delete contact.";
        if(isset($_GET['msg'])) $error_message .= " Details: " . htmlspecialchars($_GET['msg']);
    }


} // Closing bracket for `if ($conn)`
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content for contacts.php -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Contacts</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="contacts.php?action=show_create_form" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> New Contact
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
    // Determine if the form should be shown
    // 1. GET action is 'edit_contact' and $edit_contact is populated (and not null from a failed fetch)
    // 2. GET action is 'show_create_form'
    // 3. POST request resulted in an error message (form needs to be reshown with data)
    if ((isset($_GET['action']) && $_GET['action'] === 'edit_contact' && $edit_contact !== null) ||
        (isset($_GET['action']) && $_GET['action'] === 'show_create_form') ||
        ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message))
       ) {
        $show_form = true;
    }

    // Populate form field values: prioritize POST data (if error), then $edit_contact data, then empty
    $form_email = '';
    $form_first_name = '';
    $form_last_name = '';
    $form_status = 'subscribed'; // Default

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)) {
        // If POST error, use submitted values
        $form_email = htmlspecialchars($_POST['email'] ?? '');
        $form_first_name = htmlspecialchars($_POST['first_name'] ?? '');
        $form_last_name = htmlspecialchars($_POST['last_name'] ?? '');
        $form_status = htmlspecialchars($_POST['status'] ?? 'subscribed');
    } elseif ($edit_contact !== null) {
        // If editing (and not a POST error), use $edit_contact values
        $form_email = htmlspecialchars($edit_contact['email'] ?? '');
        $form_first_name = htmlspecialchars($edit_contact['first_name'] ?? '');
        $form_last_name = htmlspecialchars($edit_contact['last_name'] ?? '');
        $form_status = htmlspecialchars($edit_contact['status'] ?? 'subscribed');
    }


    if ($show_form && isset($conn) && $conn !== null):
        $form_action_url = 'contacts.php';
        $form_method = 'POST';

        // Determine if form is for "update" or "create"
        // It's "update" if $edit_contact is set AND (it's a GET request OR it's a POST request for update_contact with an error)
        $is_update_mode = false;
        if ($edit_contact !== null) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Came via "Edit" link
                $is_update_mode = true;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contact' && !empty($error_message)) {
                // Failed update attempt, stay in update mode
                $is_update_mode = true;
            }
        }

        $form_page_action = $is_update_mode ? 'update_contact' : 'create_contact';
        $form_title = $is_update_mode ? 'Edit Contact' : 'Create New Contact';
        $submit_button_text = $is_update_mode ? 'Update Contact' : 'Create Contact';
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><?php echo $form_title; ?></h5>
        </div>
        <div class="card-body">
            <form action="<?php echo $form_action_url; ?>" method="<?php echo $form_method; ?>">
                <input type="hidden" name="action" value="<?php echo $form_page_action; ?>">
                <?php if ($is_update_mode): ?>
                    <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($edit_contact['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $form_email; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $form_first_name; ?>">
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $form_last_name; ?>">
                </div>
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach ($possible_statuses as $status_option): ?>
                            <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo ($form_status === $status_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success"><?php echo $submit_button_text; ?></button>
                <a href="contacts.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <?php elseif ($show_form && (!isset($conn) || $conn === null)): ?>
        <div class='alert alert-warning'>Cannot display form due to database connection issue.</div>
    <?php endif; ?>

    <!-- Table of Contacts -->
    <div class="card">
        <div class="card-header">
            <h5>Existing Contacts</h5>
        </div>
        <div class="card-body">
             <?php if (isset($conn) && $conn !== null): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_contacts)): ?>
                            <?php foreach ($all_contacts as $contact): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['last_name']); ?></td>
                                    <td><span class="badge bg-<?php echo htmlspecialchars(strtolower($contact['status']) === 'subscribed' ? 'success' : (strtolower($contact['status']) === 'unsubscribed' ? 'danger' : (strtolower($contact['status']) === 'bounced' ? 'warning' : 'secondary'))); ?>"><?php echo htmlspecialchars(ucfirst($contact['status'])); ?></span></td>
                                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($contact['created_at']))); ?></td>
                                    <td>
                                        <a href="contacts.php?action=edit_contact&id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="contacts.php?action=delete_contact&id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this contact?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No contacts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class='alert alert-warning'>Cannot display contacts due to database connection issue.</div>
            <?php endif; ?>
        </div>
    </div>
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
