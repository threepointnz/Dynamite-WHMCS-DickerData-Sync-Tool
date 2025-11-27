<!-- Exceptions Applied Section -->
<?php if (!empty($report['exceptions_applied'])): ?>
    <div class="mt-8 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3">
                    ℹ️ Global Exceptions Applied
                    (<?php echo count($report['exceptions_applied']); ?>)
                </h3>
                <p class="text-sm text-blue-800 dark:text-blue-400 mb-4">
                    These exceptions override normal quantity mismatch detection across all clients.
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-200 dark:divide-blue-700">
                        <thead class="bg-blue-100 dark:bg-blue-900/40">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Client</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Product</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-blue-900 dark:text-blue-300">
                                    Stock Code</th>
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
                            <?php foreach ($report['exceptions_applied'] as $exc): ?>
                                <?php
                                // Determine exception type: 'quantity_mismatch' or 'unmatched'
                                $isQuantityMismatch = isset($exc['product_name']) && isset($exc['product_qty']) && isset($exc['sub_qty']);
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
                                        <?php if ($isQuantityMismatch): ?>
                                            <span class="text-gray-900 dark:text-gray-100">
                                                <?php echo htmlspecialchars($exc['product_name']); ?>
                                            </span>
                                            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs">Matched</span>
                                        <?php else: ?>
                                            <span class="text-red-600 dark:text-red-400 font-semibold">Unmatched Subscription</span>
                                            <span
                                                class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">Unmatched</span>
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <?php echo htmlspecialchars($exc['stock_description'] ?? 'N/A'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">
                                        <?php echo htmlspecialchars($exc['manufacturer_stock_code']); ?>
                                        <?php if (!$isQuantityMismatch && !empty($exc['subscription_reference'])): ?>
                                            <div class="text-xs mt-1">Ref:
                                                <?php echo htmlspecialchars($exc['subscription_reference']); ?>
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
                                        <?php else: ?>
                                            <span class="text-green-600 dark:text-green-400"><?php echo $exc['quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                                        <?php echo htmlspecialchars($exc['exception_reason'] ?? $exc['reason'] ?? 'No reason provided'); ?>
                                        <?php if (!empty($exc['exception_created_at'])): ?>
                                            <span class="text-xs block mt-1 text-gray-500">
                                                Created: <?php echo htmlspecialchars($exc['exception_created_at']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <?php if ($isQuantityMismatch): ?>
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