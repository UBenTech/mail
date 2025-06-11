<?php
// It's crucial that database connection is established and functions are defined
// before this page's content tries to use them.
// Assuming index.php (which includes this file) handles including config/database.php.
// We need to include our new dashboard functions here.
require_once 'includes/dashboard_functions.php';

// Fetch dashboard statistics
$dashboard_stats = get_dashboard_stats();

// Fetch recent campaigns
$recent_campaigns = get_recent_campaigns(5); // Get 5 recent campaigns

?>
<?php include 'includes/header.php'; // Header already includes HTML head and start of body/container ?>
<?php include 'includes/sidebar.php'; // Sidebar navigation ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Email Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" id="newCampaignBtn">
                            <i class="fas fa-plus"></i> New Campaign
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Emails Sent</h5>
                                <h2 class="card-text"><?php echo isset($dashboard_stats['total_emails_sent']) ? number_format($dashboard_stats['total_emails_sent']) : '0'; ?></h2>
                                <!-- Static text for change, can be made dynamic later if needed -->
                                <!-- <p class="text-success">+12% from last week</p> -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Open Rate</h5>
                                <h2 class="card-text"><?php echo isset($dashboard_stats['open_rate']) ? $dashboard_stats['open_rate'] . '%' : '0%'; ?></h2>
                                <!-- <p class="text-success">+2% from last week</p> -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Click Rate</h5>
                                <h2 class="card-text"><?php echo isset($dashboard_stats['click_rate']) ? $dashboard_stats['click_rate'] . '%' : '0%'; ?></h2>
                                <!-- <p class="text-danger">-1% from last week</p> -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Bounce Rate</h5>
                                <h2 class="card-text"><?php echo isset($dashboard_stats['bounce_rate']) ? $dashboard_stats['bounce_rate'] . '%' : '0%'; ?></h2>
                                <!-- <p class="text-success">-0.3% from last week</p> -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Campaigns -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Campaigns</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Sent/Scheduled</th>
                                        <th>Opens</th>
                                        <th>Clicks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_campaigns)): ?>
                                        <?php foreach ($recent_campaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo $campaign['name']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                        switch (strtolower($campaign['status'])) {
                                                            case 'sent': echo 'success'; break;
                                                            case 'scheduled': echo 'warning'; break;
                                                            case 'draft': echo 'secondary'; break;
                                                            default: echo 'info'; break;
                                                        }
                                                    ?>"><?php echo $campaign['status']; ?></span>
                                                </td>
                                                <td><?php echo $campaign['sent_at_formatted']; ?></td>
                                                <td><?php echo $campaign['opens_percentage']; ?></td>
                                                <td><?php echo $campaign['clicks_percentage']; ?></td>
                                                <td>
                                                    <?php if (strtolower($campaign['status']) == 'draft' || strtolower($campaign['status']) == 'scheduled'): ?>
                                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No recent campaigns found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <!-- Compose Email Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Email Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Modal content remains unchanged from original HTML -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Recipients</label>
                                <select class="form-select">
                                    <option>All Contacts</option>
                                    <option>Students</option>
                                    <option>Prospects</option>
                                    <option>Alumni</option>
                                    <option>Custom List</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Or upload CSV</label>
                                <input type="file" class="form-control">
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label">Template</label>
                                <select class="form-select" id="templateSelect">
                                    <option>Blank</option>
                                    <option>Newsletter</option>
                                    <option>Course Announcement</option>
                                    <option>Promotional</option>
                                    <option>Event Invitation</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <input type="text" class="form-control" placeholder="Subject" value="CtpaInstitute.org - ">
                            </div>
                            <div class="email-editor border p-3" contenteditable="true">
                                <p>Hello {first_name},</p>
                                <p>We're excited to share our latest updates with you...</p>
                                <p>Best regards,<br>The CtpaInstitute Team</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Send Options</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="scheduleSwitch">
                                        <label class="form-check-label" for="scheduleSwitch">Schedule Send</label>
                                    </div>
                                    <div class="mb-3" id="scheduleOptions" style="display: none;">
                                        <input type="datetime-local" class="form-control">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="trackOpens" checked>
                                        <label class="form-check-label" for="trackOpens">Track Opens</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="trackClicks" checked>
                                        <label class="form-check-label" for="trackClicks">Track Clicks</label>
                                    </div>
                                    <hr>
                                    <button class="btn btn-outline-secondary w-100 mb-2">
                                        <i class="fas fa-paper-plane"></i> Send Test
                                    </button>
                                    <button class="btn btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-save"></i> Save Draft
                                    </button>
                                    <button class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane"></i> Send Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script>
</body>
</html>
