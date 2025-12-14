<!-- Detailed breakdown row -->
<tr class="<?php echo !empty($issues) ? 'bg-red-50 dark:bg-red-900/20' : ''; ?> border-b dark:border-gray-600 hidden"
    id="rawData-<?php echo $clientId; ?>">
    <td colspan="7" class="p-3">
        <div class="space-y-4">
            <?php if (!empty($issues)): ?>
                <div class="text-sm text-red-700 dark:text-red-300">
                    <strong>Issues Found:</strong>
                    <ul class="list-disc list-inside mt-2 space-y-1">
                        <?php foreach ($issues as $issue): ?>
                            <li><?php echo htmlspecialchars($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($hasMatrix): ?>
                <div class="text-sm">
                    <strong class="text-gray-900 dark:text-white">Product Matrix Details:</strong>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs border border-gray-200 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-1 text-left">Dicker Subscription</th>
                                    <th class="px-2 py-1 text-left">WHMCS Product</th>
                                    <th class="px-2 py-1 text-center">Mapping</th>
                                    <th class="px-2 py-1 text-center">WHMCS Qty</th>
                                    <th class="px-2 py-1 text-center">Dicker Qty</th>
                                    <th class="px-2 py-1 text-center">Status</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                <?php foreach ($client['matrix'] as $item):
                                    $pQty = (int) ($item['product_qty'] ?? 0);
                                    $sQty = (int) ($item['sub_qty'] ?? 0);
                                    $matchedVia = $item['matched_via'] ?? 'unknown';
                                    $isQtyMatch = $pQty === $sQty;
                                    $hasException = !empty($item['has_exception']);

                                    // Determine row styling
                                    if ($hasException) {
                                        $rowClass = 'bg-blue-50 dark:bg-blue-900/10'; // Blue for exceptions
                                    } elseif ($isQtyMatch) {
                                        $rowClass = 'bg-green-50 dark:bg-green-900/10'; // Green for matches
                                    } else {
                                        $rowClass = 'bg-red-50 dark:bg-red-900/10'; // Red for mismatches
                                    }
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td class="px-2 py-1">
                                            <?php echo htmlspecialchars($item['subscription_reference'] ?? ''); ?>
                                            <br>
                                            <span class="text-gray-500 text-xs">
                                                <?php echo htmlspecialchars($item['stock_description'] ?? ''); ?>
                                            </span>
                                            <?php if ($hasException): ?>
                                                <br>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200 mt-1">
                                                    ℹ️ Exception:
                                                    <?php echo htmlspecialchars($item['exception_reason'] ?? 'No reason'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-2 py-1">
                                            <?php echo htmlspecialchars($item['matched_product_name'] ?? ''); ?>
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <span class="px-1 rounded text-xs bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($matchedVia); ?>
                                            </span>
                                        </td>
                                        <td class="px-2 py-1 text-center"><?php echo $pQty; ?></td>
                                        <td class="px-2 py-1 text-center"><?php echo $sQty; ?></td>
                                        <td class="px-2 py-1 text-center">
                                            <?php if ($hasException): ?>
                                                <span class="text-blue-600 font-bold" title="Exception Applied">⚠️</span>
                                            <?php elseif ($isQtyMatch): ?>
                                                <span class="text-green-600 font-bold">✓</span>
                                            <?php else: ?>
                                                <span class="text-red-600 font-bold">✗</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <?php if ($hasException): ?>
                                                <button
                                                    onclick="removeException(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($item['manufacturer_stock_code'], ENT_QUOTES); ?>')"
                                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs">
                                                    Remove Exception
                                                </button>
                                            <?php elseif ($item['product_qty'] != $item['sub_qty']): ?>
                                                <button
                                                    onclick="addException(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($item['manufacturer_stock_code'], ENT_QUOTES); ?>', <?php echo $item['product_qty']; ?>, <?php echo $item['sub_qty']; ?>, '<?php echo htmlspecialchars($client['companyname'] . ' - ' . $item['matched_product_name'], ENT_QUOTES); ?>', 'client', '', <?php echo isset($item['product_id']) && $item['product_id'] ? (int) $item['product_id'] : 'null'; ?>)"
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs">
                                                    Add Exception
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($client['unmatched_subscriptions'])): ?>
                    <strong class="text-gray-900 dark:text-white mt-4 block">Unmatched Dicker
                        Subscriptions:</strong>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs border border-gray-200 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-1 text-left">Dicker Subscription</th>
                                    <th class="px-2 py-1 text-left">Stock Code</th>
                                    <th class="px-2 py-1 text-center">Quantity</th>
                                    <th class="px-2 py-1 text-left">Reason</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                <?php if (empty($client['unmatched_subscriptions'])): ?>
                                    <tr>
                                        <td colspan="5" class="px-2 py-2 text-center text-gray-500">
                                            No unmatched subscriptions ✓
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($client['unmatched_subscriptions'] as $subscription): ?>
                                        <tr class="bg-yellow-50 dark:bg-yellow-900/10">
                                            <td class="px-2 py-1">
                                                <?php echo htmlspecialchars($subscription['subscription_reference'] ?? ''); ?>
                                                <br>
                                                <span class="text-gray-500 text-xs">
                                                    <?php echo htmlspecialchars($subscription['stock_description'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td class="px-2 py-1 font-mono text-xs">
                                                <?php echo htmlspecialchars($subscription['manufacturer_stock_code'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <?php echo (int) ($subscription['quantity'] ?? 0); ?>
                                            </td>
                                            <td class="px-2 py-1 text-xs text-gray-600">
                                                <?php echo htmlspecialchars($subscription['reason'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <button
                                                    onclick="addException(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($subscription['manufacturer_stock_code'] ?? '', ENT_QUOTES); ?>', 0, <?php echo (int) ($subscription['quantity'] ?? 0); ?>, '<?php echo htmlspecialchars($client['companyname'] . ' - ' . ($subscription['stock_description'] ?? 'Unmatched Subscription'), ENT_QUOTES); ?>', 'unmatched', '<?php echo htmlspecialchars($subscription['subscription_reference'] ?? '', ENT_QUOTES); ?>', null)"
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs">
                                                    Add Exception
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                // Show client exceptions if any exist
                if (isset($client['exceptions_applied']) && !empty($client['exceptions_applied'])): ?>
                    <div class="mt-4 text-sm">
                        <strong class="text-gray-900 dark:text-white">Active Exceptions for This Client:</strong>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-xs border border-gray-200 dark:border-gray-600">
                                <thead class="bg-blue-100 dark:bg-blue-900/30">
                                    <tr>
                                        <th class="px-2 py-1 text-left">MSC</th>
                                        <th class="px-2 py-1 text-center">Expected WHMCS</th>
                                        <th class="px-2 py-1 text-center">Expected Dicker</th>
                                        <th class="px-2 py-1 text-left">Reason</th>
                                        <th class="px-2 py-1 text-center">Created</th>
                                        <th class="px-2 py-1 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                    <?php foreach ($client['exceptions_applied'] as $exception): ?>
                                        <tr class="bg-blue-50 dark:bg-blue-900/10">
                                            <td class="px-2 py-1 font-mono text-xs">
                                                <?php echo htmlspecialchars($exception['manufacturer_stock_code'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <?php echo (int) ($exception['expected_whmcs_qty'] ?? 0); ?>
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <?php echo (int) ($exception['expected_dicker_qty'] ?? 0); ?>
                                            </td>
                                            <td class="px-2 py-1 text-xs">
                                                <?php echo htmlspecialchars($exception['reason'] ?? 'No reason provided'); ?>
                                            </td>
                                            <td class="px-2 py-1 text-center text-xs">
                                                <?php echo htmlspecialchars($exception['created_at'] ?? 'N/A'); ?>
                                                <?php if (!empty($exception['created_by'])): ?>
                                                    <br><span class="text-gray-500">by
                                                        <?php echo htmlspecialchars($exception['created_by']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-2 py-1 text-center">
                                                <button
                                                    onclick="removeException(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($exception['manufacturer_stock_code'], ENT_QUOTES); ?>')"
                                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs">
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                if (isset($_GET['debug'])) {
                    echo monacoEditor($client, 'Client Data', '500px');
                }
                ?>
            </div>
    </td>
</tr>