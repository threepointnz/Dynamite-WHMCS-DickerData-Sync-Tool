<?php
/**
 * O365 Sync Dashboard - Main Entry Point
 * Refactored modular structure with separated concerns
 */

// Load configuration and initialization
require_once __DIR__ . '/includes/config.php';

// Handle API requests (POST actions)
require_once __DIR__ . '/includes/api_handlers.php';

// Load mapping.json content for the editor (if missing provide empty template)
$mappingPath = __DIR__ . '/mapping.json';
$mappingContent = '{}';
if (file_exists($mappingPath)) {
    $mappingContent = file_get_contents($mappingPath);
}

// Clean up output buffer
if (ob_get_length() > 0) {
    ob_end_clean();
}

// Fetch all data
$dicker_data = $dicker->getSubscriptionDetails();
$whmcs_data = $whmcs->getO365Clients();
$problems = $whmcs->getProblematicClients();

// Append distinct packages to the mappingContent json['dicker'] and json['whmcs']
$dicker_packages = $dicker->getDistinctPackages();
$whmcs_packages = $whmcs->getDistinctPackages();

$mappingContent = json_decode($mappingContent, true);
$mappingContent['dicker']['packages'] = $dicker_packages;
$mappingContent['whmcs']['packages'] = $whmcs_packages;

// Remove found packages from the mappingContent['dicker'] and mappingContent['whmcs'] to leave only unmatched packages
$mappingContent['d'] = $mappingContent['d'] ?? [];
$mappingContent['whmcs']['packages'] = $mappingContent['whmcs']['packages'] ?? [];
$mappingContent['dicker']['packages'] = $mappingContent['dicker']['packages'] ?? [];

foreach ($mappingContent['d'] as $k => $pkg) {
    // match by whmcs_id (loose comparison)
    if (isset($pkg['whmcs_id'])) {
        $pkgWhmcsId = (string) $pkg['whmcs_id'];
        foreach ($mappingContent['whmcs']['packages'] as $key => $whmcs_pkg) {
            if (isset($whmcs_pkg['id']) && ((string) $whmcs_pkg['id'] == $pkgWhmcsId)) {
                // update the mapping.json whmcs_product_name in case it changed
                if (isset($whmcs_pkg['name'])) {
                    $mappingContent['d'][$k]['whmcs_product_name'] = $whmcs_pkg['name'];
                }
                unset($mappingContent['whmcs']['packages'][$key]);
            }
        }
    }

    if (isset($pkg['dicker']) && is_array($pkg['dicker'])) {
        foreach ($pkg['dicker'] as $dicker_entry) {
            $msc = isset($dicker_entry['manufacturer_stock_code']) ? strtolower(trim((string) $dicker_entry['manufacturer_stock_code'])) : null;
            foreach ($mappingContent['dicker']['packages'] as $index => $package) {
                $pkg_msc = isset($package['ManufacturerStockCode']) ? strtolower(trim((string) $package['ManufacturerStockCode'])) : (isset($package['manufacturer_stock_code']) ? strtolower(trim((string) $package['manufacturer_stock_code'])) : null);

                if (
                    ($msc !== null && $pkg_msc !== null && $msc === $pkg_msc)
                ) {
                    unset($mappingContent['dicker']['packages'][$index]);
                }
            }
        }
    }
}

// if we have additional whmcs packages left over, add them to the $mappingContent['d'] for dicker matching
if (!empty($mappingContent['whmcs']['packages'])) {
    foreach ($mappingContent['whmcs']['packages'] as $whmcs_pkg) {
        $mappingContent['d'][] = [
            'whmcs_id' => $whmcs_pkg['id'],
            'whmcs_product_name' => $whmcs_pkg['name'],
            'dicker' => []
        ];
    }
}

// reindex arrays so frontend iterates cleanly
$mappingContent['whmcs']['packages'] = array_values($mappingContent['whmcs']['packages']);
$mappingContent['dicker']['packages'] = array_values($mappingContent['dicker']['packages']);

$mappingContent = json_encode($mappingContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Generate report
$report_ = new Report($whmcs_data, $dicker_data);
$report = $report_->generate();

// Filter problematic clients based on exceptions
$problematicClientsFiltered = $report_->filterProblematicClients($problems);
$problems = $problematicClientsFiltered['filtered'];
$clientExceptionsApplied = $problematicClientsFiltered['exceptions_applied'];

// Handle cron mode (JSON output only)
if (isset($_GET['cron']) && $_GET['cron'] === '1') {
    $emailer = new Mail();

    // Count total issues (already filtered by exceptions)
    // Use defensive checks to handle missing keys
    $discrepancies = $report['discrepancy_report'] ?? [];
    $unmatchedSubs = $report['unmatched_subscriptions_report'] ?? [];
    $exceptionsApplied = $report['exceptions_applied'] ?? [];

    $totalIssues = count($problems) + count($discrepancies) + count($unmatchedSubs);

    // Check if we should send email (configurable)
    $onlySendIfIssues = filter_var($_ENV['MAIL_ONLY_SEND_IF_ISSUES'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    $shouldSend = $totalIssues > 0 || !$onlySendIfIssues;

    $response = ['sent' => false, 'issues_found' => $totalIssues];

    if ($shouldSend) {
        // Generate comprehensive email report
        $emailContent = $emailer->generateDailyReport(
            $problems,                  // Filtered problematic clients
            $discrepancies,             // Filtered quantity mismatches
            $unmatchedSubs,             // Filtered unmatched subscriptions
            $exceptionsApplied,         // Quantity/subscription exceptions
            $clientExceptionsApplied    // Client-level exceptions
        );

        if (!empty($emailContent)) {
            $mailResult = $emailer->send($_ENV['MAIL_TO'], $_ENV['MAIL_SUBJECT'], $emailContent);
            $response = array_merge($response, (array) $mailResult);
        }
    } else {
        $response['message'] = 'No issues found - email not sent';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    $db->disconnect();
    exit;
}

// Set page title
$pageTitle = 'O365 Sync - API Testing Dashboard';

// Render HTML page
require_once __DIR__ . '/includes/header.php';

// Render page sections
require_once __DIR__ . '/pages/problems.php';
require_once __DIR__ . '/pages/mapping.php';
require_once __DIR__ . '/pages/dashboard.php';
require_once __DIR__ . '/pages/exceptions.php';
require_once __DIR__ . '/pages/debug.php';

// Debug output (only visible when not in cron mode)
// Uncomment any of these to debug:
// dd($report, 'O365 Clients Raw Data');
// dd($dicker->getSubscriptionDetails(null, null, null, null, 'CSP', false), 'Subscription Details');
// dd($problems, 'WHMCS Problematic Clients');
// dd($mappingContent, 'Mapping Configuration');

require_once __DIR__ . '/includes/footer.php';

// Clean up
$db->disconnect();
