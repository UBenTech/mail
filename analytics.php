<?php
require_once 'config/database.php';
require_once 'includes/analytics_functions.php';

$summary_stats = [];
$campaign_details = [];
$error_message = '';

if (!isset($conn) || $conn === null) {
    $error_message = "Database connection failed. Please check configuration.";
} else {
    // Initialize with default structure to avoid undefined index errors in HTML
    $summary_stats_default = [
        'total_campaigns_sent' => 0, 'grand_total_successfully_sent' => 0,
        'overall_open_rate' => 0, 'overall_click_rate' => 0
        // Add other keys from get_overall_analytics_summary if you display them directly
    ];
    $summary_stats = get_overall_analytics_summary($conn);
    if ($summary_stats === false || !is_array($summary_stats)) { // Check if functions returned an error indicator or unexpected type
        $error_message = "An error occurred while fetching summary statistics.";
        $summary_stats = $summary_stats_default; // Reset to default on error
    } else {
        // Ensure all expected keys are present even if DB returned NULLs (e.g. no campaigns sent yet)
        $summary_stats = array_merge($summary_stats_default, $summary_stats);
    }


    $campaign_details = get_all_campaign_details_for_analytics($conn);
    if ($campaign_details === false || !is_array($campaign_details)) { // Check if functions returned an error indicator
        if(empty($error_message)) { // Don't overwrite previous error
             $error_message = "An error occurred while fetching campaign details.";
        }
        $campaign_details = []; // Reset to empty on error
    }
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Campaign Analytics</h1>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($error_message) && !empty($summary_stats)): ?>
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Total Campaigns Sent</h5>
                    <h2 class="card-text"><?php echo number_format($summary_stats['total_campaigns_sent']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Total Emails Sent</h5>
                    <h2 class="card-text"><?php echo number_format($summary_stats['grand_total_successfully_sent']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Overall Open Rate</h5>
                    <h2 class="card-text"><?php echo htmlspecialchars($summary_stats['overall_open_rate']); ?>%</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Overall Click Rate</h5>
                    <h2 class="card-text"><?php echo htmlspecialchars($summary_stats['overall_click_rate']); ?>%</h2>
                </div>
            </div>
        </div>
        <!-- Add more summary cards if needed e.g. bounce rate -->
         <div class="col-md-3 mt-3"> <!-- Example for total bounces -->
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Total Bounces</h5>
                    <h2 class="card-text"><?php echo number_format($summary_stats['grand_total_bounces'] ?? 0); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3"> <!-- Example for overall bounce rate -->
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">Overall Bounce Rate</h5>
                    <h2 class="card-text"><?php echo htmlspecialchars($summary_stats['overall_bounce_rate'] ?? 0); ?>%</h2>
                </div>
            </div>
        </div>
    </div>
    <?php elseif (empty($error_message)): ?>
        <div class="alert alert-info">No summary statistics available yet. This may be because no campaigns have been marked as 'sent'.</div>
    <?php endif; ?>


    <!-- Recent Campaigns Table -->
    <?php if (empty($error_message)): ?>
    <div class="card">
        <div class="card-header">
            <h5>Campaign Performance Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Campaign Name</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Sent</th>
                            <th>Opens (Count / Rate)</th>
                            <th>Clicks (Count / Rate)</th>
                            <th>Bounces (Count / Rate)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($campaign_details)): ?>
                            <?php foreach ($campaign_details as $campaign): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                    <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            switch (strtolower($campaign['status'])) {
                                                case 'sent': echo 'success'; break;
                                                case 'scheduled': echo 'info'; break; // Changed scheduled to info for better contrast
                                                case 'failed': echo 'danger'; break;
                                                case 'archived': echo 'secondary'; break;
                                                default: echo 'light'; break; // Default for other statuses
                                            }
                                        ?>"><?php echo htmlspecialchars(ucfirst($campaign['status'])); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($campaign['sent_at'] ? date("Y-m-d H:i", strtotime($campaign['sent_at'])) : ($campaign['created_at'] ? date("Y-m-d H:i", strtotime($campaign['created_at'])) . ' (Created)' : 'N/A')); ?></td>
                                    <td><?php echo number_format($campaign['successfully_sent'] ?? 0); ?></td>
                                    <td><?php echo number_format($campaign['opens_count'] ?? 0); ?> (<?php echo htmlspecialchars($campaign['open_rate'] ?? 0); ?>%)</td>
                                    <td><?php echo number_format($campaign['clicks_count'] ?? 0); ?> (<?php echo htmlspecialchars($campaign['click_rate'] ?? 0); ?>%)</td>
                                    <td><?php echo number_format($campaign['bounces_count'] ?? 0); ?> (<?php echo htmlspecialchars($campaign['bounce_rate'] ?? 0); ?>%)</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No campaign data available for detailed analytics (e.g., no campaigns are 'sent', 'archived', 'failed' etc.).</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; // End $error_message check for table display ?>
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
