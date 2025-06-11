<?php

/**
 * Fetches overall dashboard statistics from the database.
 * Assumes $conn (database connection) is available globally.
 *
 * @return array An associative array with keys: 'total_sent', 'open_rate', 'click_rate', 'bounce_rate'.
 *               Returns default values (0 or 0.0) if no data or on error.
 */
function get_dashboard_stats() {
    global $conn; // Use the global connection variable from config/database.php

    $stats = [
        'total_emails_sent' => 0,
        'open_rate' => 0.0,
        'click_rate' => 0.0,
        'bounce_rate' => 0.0 // Placeholder, as specific bounce tracking might need more detail
    ];

    if (!$conn) {
        // Optionally log an error here
        error_log("get_dashboard_stats: Database connection is not available.");
        return $stats;
    }

    // Query to sum up relevant counts from all 'Sent' campaigns
    // We are interested in campaigns that have actually been sent
    $query = "SELECT
                SUM(successfully_sent) as total_successfully_sent,
                SUM(opens_count) as total_opens,
                SUM(clicks_count) as total_clicks,
                SUM(bounces_count) as total_bounces
              FROM campaigns
              WHERE status = 'Sent'";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        $total_successfully_sent = (int)$row['total_successfully_sent'];
        $total_opens = (int)$row['total_opens'];
        $total_clicks = (int)$row['total_clicks'];
        // $total_bounces = (int)$row['total_bounces']; // For bounce rate calculation

        if ($total_successfully_sent > 0) {
            $stats['total_emails_sent'] = $total_successfully_sent;
            $stats['open_rate'] = round(($total_opens / $total_successfully_sent) * 100, 2);
            $stats['click_rate'] = round(($total_clicks / $total_successfully_sent) * 100, 2);
            // Example: Bounce rate based on successfully sent that then bounced.
            // $stats['bounce_rate'] = round(($total_bounces / $total_successfully_sent) * 100, 2);
            // For now, we'll use the example data's bounce rate directly for the card if needed,
            // as the card seems to imply an overall bounce rate not tied to a specific campaign's success.
            // The sample data has a direct bounce_count. Let's sum that up for an overall "bounce events" count.
            // The card text is "Bounce Rate", implying a rate.
            // Let's calculate bounce rate against total_recipients for 'Sent' campaigns for now.
             $query_total_recipients_for_sent = "SELECT SUM(total_recipients) as sum_total_recipients FROM campaigns WHERE status = 'Sent'";
             $res_total_recipients = mysqli_query($conn, $query_total_recipients_for_sent);
             if($res_total_recipients && mysqli_num_rows($res_total_recipients) > 0){
                $row_recipients = mysqli_fetch_assoc($res_total_recipients);
                $sum_total_recipients = (int)$row_recipients['sum_total_recipients'];
                if($sum_total_recipients > 0 && $row['total_bounces'] > 0){
                     $stats['bounce_rate'] = round(((int)$row['total_bounces'] / $sum_total_recipients) * 100, 2);
                }
             }
        }
    } else if (!$result) {
        error_log("get_dashboard_stats query failed: " . mysqli_error($conn));
    }

    return $stats;
}

/**
 * Fetches a list of recent campaigns from the database.
 * Assumes $conn (database connection) is available globally.
 *
 * @param int $limit The maximum number of recent campaigns to fetch.
 * @return array An array of associative arrays, each representing a campaign.
 *               Each campaign array includes: 'name', 'status', 'sent_at_formatted',
 *               'opens_percentage', 'clicks_percentage'.
 *               Returns an empty array if no campaigns or on error.
 */
function get_recent_campaigns($limit = 5) {
    global $conn;
    $campaigns = [];

    if (!$conn) {
        error_log("get_recent_campaigns: Database connection is not available.");
        return $campaigns;
    }

    // Fetch campaigns, prioritizing sent ones, then scheduled, then others by creation date.
    $query = "SELECT
                id, name, status, subject, body_html, created_at, scheduled_at, sent_at,
                total_recipients, successfully_sent, opens_count, clicks_count, bounces_count
              FROM campaigns
              ORDER BY
                CASE status
                    WHEN 'Sent' THEN 1
                    WHEN 'Scheduled' THEN 2
                    WHEN 'Draft' THEN 3
                    ELSE 4
                END,
                COALESCE(sent_at, scheduled_at, created_at) DESC
              LIMIT ?";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("get_recent_campaigns prepare failed: " . mysqli_error($conn));
        return $campaigns;
    }

    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $opens_percentage = 0;
            $clicks_percentage = 0;

            if ((int)$row['successfully_sent'] > 0) {
                $opens_percentage = round(((int)$row['opens_count'] / (int)$row['successfully_sent']) * 100, 1);
                $clicks_percentage = round(((int)$row['clicks_count'] / (int)$row['successfully_sent']) * 100, 1);
            } elseif ((int)$row['total_recipients'] > 0 && $row['status'] !== 'Draft') {
                // If not successfully_sent but has recipients (e.g. scheduled, failed), use total_recipients as base for potential rates
                $opens_percentage = round(((int)$row['opens_count'] / (int)$row['total_recipients']) * 100, 1);
                $clicks_percentage = round(((int)$row['clicks_count'] / (int)$row['total_recipients']) * 100, 1);
            }


            $sent_display_date = '-';
            if ($row['status'] == 'Sent' && $row['sent_at']) {
                $sent_display_date = date("M j, Y", strtotime($row['sent_at']));
            } elseif ($row['status'] == 'Scheduled' && $row['scheduled_at']) {
                $sent_display_date = date("M j, Y", strtotime($row['scheduled_at']));
            }


            $campaigns[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'status' => htmlspecialchars($row['status']),
                'sent_at_formatted' => $sent_display_date,
                'opens_percentage' => $opens_percentage . '%',
                'clicks_percentage' => $clicks_percentage . '%',
                // Raw counts can be added if needed by the view directly
                'successfully_sent' => (int)$row['successfully_sent'],
                'opens_count' => (int)$row['opens_count'],
                'clicks_count' => (int)$row['clicks_count']
            ];
        }
    } else {
        error_log("get_recent_campaigns execute failed: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    return $campaigns;
}

?>
