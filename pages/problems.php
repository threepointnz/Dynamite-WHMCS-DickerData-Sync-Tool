<!-- WHMCS Missing Custom Fields -->
<div class="mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-4 flex items-center text-gray-900 dark:text-white">
            <svg class="w-6 h-6 mr-2 text-yellow-500 dark:text-yellow-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z">
                </path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01">
                </path>
            </svg>
            WHMCS Missing Custom Fields
        </h2>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Client ID</th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Company</th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Expiry</th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Tenant</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                <?php foreach ($problems as $problem):
                    // normalise keys and values
                    $clientId = $problem['client_id'] ?? $problem['id'] ?? 'N/A';
                    $company = $problem['companyname'] ?? $problem['company'] ?? 'N/A';

                    // accept both tenantID and tenantId (and tenant)
                    $tenantRaw = $problem['tenantID'] ?? $problem['tenantId'] ?? $problem['tenant'] ?? null;
                    $expiryRaw = $problem['expiry'] ?? null;

                    // treat empty, null or '0' as missing / failed
                    $expiryOk = !empty($expiryRaw) && $expiryRaw != 0;
                    $tenantOk = !empty($tenantRaw) && $tenantRaw != 0;

                    // "Passed" / "Failed" templates (consistent badge styling used elsewhere)
                    $passedBadge = '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm font-medium">Passed</span>';
                    $failedBadge = '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm font-medium">Failed</span>';

                    $expiryBadge = $expiryOk ? $passedBadge : $failedBadge;
                    $tenantBadge = $tenantOk ? $passedBadge : $failedBadge;
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo htmlspecialchars($clientId); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo htmlspecialchars($company); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo $expiryBadge; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo $tenantBadge; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>