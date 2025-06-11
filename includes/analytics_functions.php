<?php
// No direct access
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

// require_once __DIR__ . '/../config/database.php'; // Assuming $conn is passed

if (!function_exists('get_all_campaign_details_for_analytics')) {
    function get_all_campaign_details_for_analytics($conn) {
        if ($conn === null || $conn->connect_error) {
            // error_log("Database connection error in get_all_campaign_details_for_analytics");
            return []; // Return empty if no DB connection
        }

        $sql = "SELECT id, name, subject, status, sent_at, created_at, total_recipients, successfully_sent, opens_count, clicks_count, bounces_count
                FROM campaigns
                WHERE status != 'draft'
                ORDER BY COALESCE(sent_at, created_at) DESC"; // Order by sent_at, fallback to created_at if sent_at is NULL

        $result = $conn->query($sql);
        $campaigns_analytics = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Ensure numeric types for calculations
                $successfully_sent = (int)$row['successfully_sent'];
                $opens_count = (int)$row['opens_count'];
                $clicks_count = (int)$row['clicks_count'];
                $total_recipients = (int)$row['total_recipients'];
                $bounces_count = (int)$row['bounces_count'];

                $row['open_rate'] = ($successfully_sent > 0) ? round(($opens_count / $successfully_sent) * 100, 2) : 0;
                $row['click_rate'] = ($successfully_sent > 0) ? round(($clicks_count / $successfully_sent) * 100, 2) : 0;
                $row['bounce_rate'] = ($total_recipients > 0) ? round(($bounces_count / $total_recipients) * 100, 2) : 0;

                $campaigns_analytics[] = $row;
            }
        } elseif ($result === false) {
            // error_log("Query failed in get_all_campaign_details_for_analytics: " . $conn->error);
        }
        return $campaigns_analytics;
    }
}

if (!function_exists('get_overall_analytics_summary')) {
    function get_overall_analytics_summary($conn) {
         if ($conn === null || $conn->connect_error) {
            // error_log("Database connection error in get_overall_analytics_summary");
            // Return default structure on connection error
            return [
                'total_campaigns_sent' => 0,
                'grand_total_recipients' => 0,
                'grand_total_successfully_sent' => 0,
                'grand_total_opens' => 0,
                'grand_total_clicks' => 0,
                'grand_total_bounces' => 0,
                'overall_open_rate' => 0,
                'overall_click_rate' => 0,
                'overall_bounce_rate' => 0,
            ];
        }

        $sql = "SELECT
                    COUNT(id) as total_campaigns_processed, -- Renamed to avoid confusion with 'sent' status filter
                    SUM(total_recipients) as grand_total_recipients,
                    SUM(successfully_sent) as grand_total_successfully_sent,
                    SUM(opens_count) as grand_total_opens,
                    SUM(clicks_count) as grand_total_clicks,
                    SUM(bounces_count) as grand_total_bounces
                FROM campaigns
                WHERE status = 'sent'"; // Only 'sent' campaigns for summary of actual performance

        $result = $conn->query($sql);
        $summary = [
            'total_campaigns_sent' => 0, // This will specifically count campaigns with status 'sent'
            'grand_total_recipients' => 0,
            'grand_total_successfully_sent' => 0,
            'grand_total_opens' => 0,
            'grand_total_clicks' => 0,
            'grand_total_bounces' => 0,
            'overall_open_rate' => 0,
            'overall_click_rate' => 0,
            'overall_bounce_rate' => 0,
        ];

        if ($result && $row = $result->fetch_assoc()) {
            // The COUNT(id) will be the number of campaigns that matched WHERE status = 'sent'
            $summary['total_campaigns_sent'] = (int)$row['total_campaigns_processed'];
            $summary['grand_total_recipients'] = (int)$row['grand_total_recipients'];
            $summary['grand_total_successfully_sent'] = (int)$row['grand_total_successfully_sent'];
            $summary['grand_total_opens'] = (int)$row['grand_total_opens'];
            $summary['grand_total_clicks'] = (int)$row['grand_total_clicks'];
            $summary['grand_total_bounces'] = (int)$row['grand_total_bounces'];

            if ($summary['grand_total_successfully_sent'] > 0) {
                $summary['overall_open_rate'] = round(($summary['grand_total_opens'] / $summary['grand_total_successfully_sent']) * 100, 2);
                $summary['overall_click_rate'] = round(($summary['grand_total_clicks'] / $summary['grand_total_successfully_sent']) * 100, 2);
            }
            if ($summary['grand_total_recipients'] > 0) {
                $summary['overall_bounce_rate'] = round(($summary['grand_total_bounces'] / $summary['grand_total_recipients']) * 100, 2);
            }
        } elseif ($result === false) {
             // error_log("Query failed in get_overall_analytics_summary: " . $conn->error);
        }
        return $summary;
    }
}
?>
