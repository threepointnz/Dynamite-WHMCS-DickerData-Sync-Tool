<!-- Exceptions Applied Section -->
<?php
// Merge quantity/subscription exceptions with client-level exceptions
$allExceptions = array_merge(
    $report['exceptions_applied'] ?? [],
    $clientExceptionsApplied ?? []
);
?>
<?php if (!empty($allExceptions)): ?>
    <div class="mt-8 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3">
                    ℹ️ All Exceptions Applied
                    (<?php echo count($allExceptions); ?>)
                </h3>
                <p class="text-sm text-blue-800 dark:text-blue-400 mb-4">
                    These exceptions override normal issue detection (quantity mismatches, unmatched subscriptions, missing
                    client data).
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-200 dark:divide-blue-700">
                        <thead class="bg-blue-100 dark:bg-blue-900/40">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Client</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Exception Type</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Details</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-blue-900 dark:text-blue-300">
                                    WHMCS</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Dicker</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Reason</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-200 dark:divide-blue-700">
                            <?php foreach ($allExceptions as $exc): ?>
                                <?php
                                // Determine exception type
                                $excType = $exc['type'] ?? 'quantity_mismatch';
                                $isClientLevel = in_array($excType, ['missing_tenant_id', 'missing_expiry']);
                                $isQuantityMismatch = isset($exc['product_name']) && isset($exc['product_qty']) && isset($exc['sub_qty']);
                                $isUnmatched = !$isClientLevel && !$isQuantityMismatch;
                                ?>
                                <tr
                                    class="bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            <?php echo htmlspecialchars($exc['companyname'] ?? 'Unknown'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            ID: <?php echo htmlspecialchars($exc['client_id']); ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        <?php if ($isClientLevel): ?>
                                            <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-medium">
                                                <?php echo $excType === 'missing_tenant_id' ? '🔑 Missing Tenant ID' : '📅 Missing Expiry'; ?>
                                            </span>
                                        <?php elseif ($isQuantityMismatch): ?>
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                                📊 Quantity Mismatch
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-medium">
                                                ⚠️ Unmatched Subscription
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        <?php if ($isClientLevel): ?>
                                            <span class="text-gray-600 dark:text-gray-400 italic">Client-level exception</span>
                                        <?php elseif ($isQuantityMismatch): ?>
                                            <div class="text-gray-900 dark:text-gray-100">
                                                <?php echo htmlspecialchars($exc['product_name']); ?>
                                            </div>
                                            <div class="text-xs font-mono text-gray-600 dark:text-gray-400 mt-1">
                                                <?php echo htmlspecialchars($exc['manufacturer_stock_code']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-gray-900 dark:text-gray-100">
                                                <?php echo htmlspecialchars($exc['stock_description'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-xs font-mono text-gray-600 dark:text-gray-400 mt-1">
                                                <?php echo htmlspecialchars($exc['manufacturer_stock_code']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm font-semibold">
                                        <?php if ($isQuantityMismatch): ?>
                                            <span class="text-blue-600 dark:text-blue-400"><?php echo $exc['product_qty']; ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm font-semibold">
                                        <?php if ($isQuantityMismatch): ?>
                                            <span class="text-green-600 dark:text-green-400"><?php echo $exc['sub_qty']; ?></span>
                                        <?php elseif ($isUnmatched): ?>
                                            <span class="text-green-600 dark:text-green-400"><?php echo $exc['quantity']; ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                                        <?php echo htmlspecialchars($exc['exception_reason'] ?? $exc['reason'] ?? 'No reason provided'); ?>
                                        <?php if (!empty($exc['exception_created_at']) || !empty($exc['created_at'])): ?>
                                            <span class="text-xs block mt-1 text-gray-500">
                                                Created:
                                                <?php echo htmlspecialchars($exc['exception_created_at'] ?? $exc['created_at'] ?? 'N/A'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <?php if ($isClientLevel): ?>
                                            <button
                                                onclick="removeClientException(<?php echo $exc['client_id']; ?>, '<?php echo htmlspecialchars($excType, ENT_QUOTES); ?>')"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs font-medium transition-colors">
                                                Remove
                                            </button>
                                        <?php elseif ($isQuantityMismatch): ?>
                                            <button
                                                onclick="removeException(<?php echo $exc['client_id']; ?>, '<?php echo htmlspecialchars($exc['manufacturer_stock_code'], ENT_QUOTES); ?>')"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs font-medium transition-colors">
                                                Remove
                                            </button>
                                        <?php else: ?>
                                            <button
                                                onclick="removeException(<?php echo $exc['client_id']; ?>, '<?php echo htmlspecialchars($exc['manufacturer_stock_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($exc['subscription_reference'] ?? '', ENT_QUOTES); ?>')"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs font-medium transition-colors">
                                                Remove
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (isset($_GET['debug'])) {
                echo monacoEditor($report_->getExceptions(), 'Exceptions Data', '500px');
            } ?>
        </div>
    </div>
<?php endif; ?>