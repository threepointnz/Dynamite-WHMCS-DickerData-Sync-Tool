<!-- O365 Client Data Display -->
<div class="mb-8" id="section-dashboard">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-4 flex items-center text-gray-900 dark:text-white">
            <svg class="w-6 h-6 mr-2 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            O365 Client Sync Status
        </h2>

        <?php if (!empty($report)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border-collapse sticky top-0 z-10">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600">
                            <th class="text-left p-3 font-medium text-gray-700 dark:text-gray-300">Company</th>
                            <th class="text-left p-3 font-medium text-gray-700 dark:text-gray-300">Client ID</th>
                            <th class="text-left p-3 font-medium text-gray-700 dark:text-gray-300">Expiry</th>
                            <th class="text-center p-3 font-medium text-gray-700 dark:text-gray-300">WHMCS Licenses
                            </th>
                            <th class="text-center p-3 font-medium text-gray-700 dark:text-gray-300">Dicker Licenses
                            </th>
                            <th class="text-center p-3 font-medium text-gray-700 dark:text-gray-300">Tenant IDs</th>
                            <th class="text-center p-3 font-medium text-gray-700 dark:text-gray-300">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // First, process all clients to determine their status and sort them
                        $sortedClients = [];

                        foreach ($report['matched'] as $clientId => $client) {
                            $skipped = [
                                'discrepancy_report',
                                'unmatched_subscriptions_report',
                                'exceptions_applied'
                            ];
                            if (in_array($clientId, $skipped)) {
                                continue;
                            }                            // Calculate issues and row color using matrix data
                            // Note: matrix has already been filtered to exclude items with matching exceptions
                            $issues = array();
                            $rowClass = '';

                            // Check for sync issues
                            $hasProducts = !empty($client['products']);
                            $hasSubscriptions = !empty($client['subscriptions']);
                            $hasTenantId = !empty($client['tenantID']) && !empty(array_filter($client['tenantID']));
                            $hasMatrix = !empty($client['matrix']);
                            $hasUnmatchedSubscriptions = !empty($client['unmatched_subscriptions']);

                            // Primary issue detection using matrix data
                            if ($hasTenantId && !$hasSubscriptions) {
                                $issues[] = 'Has Tenant ID but no Dicker Data subscriptions';
                                $rowClass = 'bg-red-50 dark:bg-red-900/20';
                            }
                            if ($hasProducts && !$hasTenantId) {
                                $issues[] = 'Has WHMCS products but no Tenant ID';
                                $rowClass = 'bg-red-50 dark:bg-red-900/20';
                            }

                            if ($hasUnmatchedSubscriptions) {
                                $issues[] = 'Unmatched Dicker subscriptions exist';
                                $rowClass = 'bg-red-50 dark:bg-red-900/20';
                            }                            // Detailed matrix analysis for quantity mismatches
                            if ($hasMatrix) {
                                foreach ($client['matrix'] as $matrixItem) {
                                    // Skip items with exceptions - they are approved mismatches
                                    if (!empty($matrixItem['has_exception'])) {
                                        continue;
                                    }

                                    $productQty = (int) ($matrixItem['product_qty'] ?? 0);
                                    $subQty = (int) ($matrixItem['sub_qty'] ?? 0);
                                    $productName = $matrixItem['matched_product_name'] ?? 'Unknown Product';
                                    $subscriptionRef = $matrixItem['subscription_reference'] ?? 'Unknown Subscription';

                                    // Check for quantity mismatches
                                    if ($productQty !== $subQty) {
                                        $difference = $subQty - $productQty;
                                        $issueType = $difference > 0 ? 'Undercharging' : 'Overcharging';
                                        $issues[] = "{$issueType}: {$productName} - WHMCS: {$productQty}, Dicker: {$subQty} (" . ($difference > 0 ? "▲" : "▼") . abs($difference) . ")";
                                        $rowClass = 'bg-red-50 dark:bg-red-900/20';
                                    }
                                }
                            }

                            // Determine priority for sorting
                            if (empty($issues)) {
                                if ($hasProducts || $hasSubscriptions) {
                                    $priority = 3; // Synced
                                    $status = 'synced';
                                } else {
                                    $priority = 4; // No Data
                                    $status = 'no_data';
                                }
                            } else {
                                $criticalIssues = array_filter($issues, function ($issue) {
                                    return strpos($issue, 'charging') !== false;
                                });

                                if (!empty($criticalIssues)) {
                                    $priority = 1; // Critical
                                    $status = 'critical';
                                } else {
                                    $priority = 2; // Warning
                                    $status = 'warning';
                                }
                            }

                            $sortedClients[] = [
                                'clientId' => $clientId,
                                'client' => $client,
                                'issues' => $issues,
                                'rowClass' => $rowClass,
                                'priority' => $priority,
                                'status' => $status,
                                'hasProducts' => $hasProducts,
                                'hasSubscriptions' => $hasSubscriptions,
                                'hasTenantId' => $hasTenantId,
                                'hasMatrix' => $hasMatrix,
                            ];
                        }

                        // Sort by priority (1=Critical, 2=Warning, 3=Synced, 4=No Data)
                        usort($sortedClients, function ($a, $b) {
                            return $a['priority'] - $b['priority'];
                        });

                        // Now render the sorted clients
                        foreach ($sortedClients as $sortedClient):
                            $clientId = $sortedClient['clientId'];
                            $client = $sortedClient['client'];
                            $issues = $sortedClient['issues'];
                            $rowClass = $sortedClient['rowClass'];
                            $hasProducts = $sortedClient['hasProducts'];
                            $hasSubscriptions = $sortedClient['hasSubscriptions'];
                            $hasTenantId = $sortedClient['hasTenantId'];
                            $hasMatrix = $sortedClient['hasMatrix'];

                            // Calculate totals from matrix for display
                            $whmcsCount = $client['estimated_365_count'];
                            $dickerCount = ($hasSubscriptions ? array_sum(array_column($client['subscriptions'], 'ConfirmedQuantity')) : 0);
                            ?>
                            <!-- Main row -->
                            <tr class="border-b dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?php echo $rowClass; ?>"
                                onClick="toggleElement('rawData-<?php echo $clientId; ?>')">
                                <td class="p-3">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <svg class="w-4 h-4 mr-1 transform transition-transform inline-block" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7">
                                            </path>
                                        </svg>
                                        <?php echo htmlspecialchars($client['companyname']); ?>         <?php
                                                    // Check for charging issues in matrix data (excluding exceptions)
                                                    $isUndercharging = false;
                                                    $isOvercharging = false;
                                                    $totalQuantityDiff = 0;

                                                    if ($hasMatrix) {
                                                        foreach ($client['matrix'] as $matrixItem) {
                                                            // Skip items with exceptions - they are approved mismatches
                                                            if (!empty($matrixItem['has_exception'])) {
                                                                continue;
                                                            }

                                                            $productQty = (int) ($matrixItem['product_qty'] ?? 0);
                                                            $subQty = (int) ($matrixItem['sub_qty'] ?? 0);

                                                            if ($productQty !== $subQty) {
                                                                $difference = $subQty - $productQty;
                                                                $totalQuantityDiff += $difference;

                                                                if ($difference > 0) {
                                                                    $isUndercharging = true;
                                                                } else {
                                                                    $isOvercharging = true;
                                                                }
                                                            }
                                                        }
                                                    }

                                                    // Display charging status pill
                                                    if ($isUndercharging && $isOvercharging): ?>
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                                Mixed Billing
                                            </span>
                                        <?php elseif ($isUndercharging): ?>
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                Undercharging
                                                (<?php echo $totalQuantityDiff > 0 ? '+' . $totalQuantityDiff : $totalQuantityDiff; ?>)
                                            </span>
                                        <?php elseif ($isOvercharging): ?>
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                                Overcharging (<?php echo $totalQuantityDiff; ?>)
                                            </span>
                                        <?php endif; ?>

                                        <?php
                                        $isTrial = false;

                                        $looksLikeTrial = function ($val) {
                                            if ($val === null)
                                                return false;
                                            if (is_bool($val))
                                                return $val;
                                            $s = strtolower(trim((string) $val));
                                            if ($s === '')
                                                return false;
                                            if (in_array($s, ['1', 'true', 'yes', 'y', 'trial'], true))
                                                return true;
                                            if (strpos($s, 'trial') !== false)
                                                return true;
                                            return false;
                                        };

                                        if (!empty($client['subscriptions']) && is_array($client['subscriptions'])) {
                                            foreach ($client['subscriptions'] as $sub) {
                                                if (
                                                    (isset($sub['Trial']) && $looksLikeTrial($sub['Trial']))
                                                    || (isset($sub['trial']) && $looksLikeTrial($sub['trial']))
                                                    || (isset($sub['StockDescription']) && $looksLikeTrial($sub['StockDescription']))
                                                    || (isset($sub['SubscriptionReference']) && $looksLikeTrial($sub['SubscriptionReference']))
                                                ) {
                                                    $isTrial = true;
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$isTrial && ((isset($client['Trial']) && $looksLikeTrial($client['Trial'])) || (isset($client['trial']) && $looksLikeTrial($client['trial'])))) {
                                            $isTrial = true;
                                        }

                                        if ($isTrial): ?>
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                Trial
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo $clientId; ?>
                                </td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-400">
                                    <?php echo htmlspecialchars($client['expiry']); ?>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm font-medium">
                                        <?php echo $whmcsCount; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm font-medium">
                                        <?php echo $dickerCount; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-sm font-medium">
                                        <?php echo $hasTenantId ? count(array_filter($client['tenantID'])) : 0; ?>
                                    </span>
                                </td>
                                <?php
                                // Status determination
                                if (empty($issues)) {
                                    if ($hasProducts || $hasSubscriptions) {
                                        $label = '✓ Synced';
                                        $bg = 'bg-green-100 text-green-800';
                                    } else {
                                        $label = 'No Data';
                                        $bg = 'bg-gray-100 text-gray-600';
                                    }
                                } else {
                                    $criticalIssues = array_filter($issues, function ($issue) {
                                        return strpos($issue, 'charging') !== false;
                                    });

                                    if (!empty($criticalIssues)) {
                                        $label = '⚠️ Critical';
                                        $bg = 'bg-red-100 text-red-800';
                                    } else {
                                        $label = '⚠️ Warning';
                                        $bg = 'bg-yellow-100 text-yellow-800';
                                    }
                                }
                                ?>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-1 <?php echo $bg; ?> rounded text-sm font-medium whitespace-nowrap">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                            </tr>

                            <?php require __DIR__ . '/dashboard_details.php'; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Updated Summary Stats using matrix-based logic -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php
                $totalClients = 0;
                $syncedClients = 0;
                $criticalClients = 0;
                $warningClients = 0;
                $noDataClients = 0;

                foreach ($sortedClients as $sortedClient) {
                    $totalClients++;

                    switch ($sortedClient['status']) {
                        case 'critical':
                            $criticalClients++;
                            break;
                        case 'warning':
                            $warningClients++;
                            break;
                        case 'synced':
                            $syncedClients++;
                            break;
                        case 'no_data':
                            $noDataClients++;
                            break;
                    }
                }
                ?>
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">
                        <?php echo $totalClients; ?>
                    </div>
                    <div class="text-sm text-blue-700 dark:text-blue-400">Total Clients</div>
                </div>

                <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-red-900 dark:text-red-300">
                        <?php echo $criticalClients; ?>
                    </div>
                    <div class="text-sm text-red-700 dark:text-red-400">Critical Issues</div>
                </div>

                <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-300">
                        <?php echo $warningClients; ?>
                    </div>
                    <div class="text-sm text-yellow-700 dark:text-yellow-400">Warnings</div>
                </div>

                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-green-900 dark:text-green-300">
                        <?php echo $syncedClients; ?>
                    </div>
                    <div class="text-sm text-green-700 dark:text-green-400">Synced OK</div>
                </div>

            </div>

        <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <p class="text-lg">No O365 client data found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleElement(id) {
        const element = document.getElementById(id);
        if (element) {
            element.classList.toggle('hidden');
        }
    }
</script>
