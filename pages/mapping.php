<!-- Mapping editor -->
<div class="mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-4 flex items-center text-gray-900 dark:text-white">
            <svg class="w-6 h-6 mr-2 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20l9-5-9-5-9 5 9 5z">
                </path>
            </svg>
            Dicker to WHMCS Product Mapping
        </h2>

        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">Drag <strong>Dicker packages</strong> from the
            left into <strong>WHMCS product boxes</strong>. Each Dicker package can only belong to one WHMCS
            product. Click <strong>Save Mapping</strong> to persist changes.</p>

        <?php
        // Prepare mapping data for drag-and-drop UI
        $mapData = json_decode($mappingContent, true);
        $mapData['d'] = $mapData['d'] ?? [];
        $mapData['whmcs'] = $mapData['whmcs'] ?? ['packages' => []];
        $mapData['dicker'] = $mapData['dicker'] ?? ['packages' => []];

        // Build WHMCS boxes: combine existing mapped + unmatched WHMCS packages
        $whmcsBoxes = [];
        foreach ($mapData['d'] as $mapping) {
            $wid = isset($mapping['whmcs_id']) ? (string) $mapping['whmcs_id'] : '';
            if (!$wid)
                continue;
            $whmcsBoxes[$wid] = [
                'whmcs_id' => $wid,
                'whmcs_product_name' => $mapping['whmcs_product_name'] ?? '',
                'dicker' => $mapping['dicker'] ?? []
            ];
        }

        // Add unmatched WHMCS packages as empty boxes
        foreach ($mapData['whmcs']['packages'] as $pkg) {
            $id = isset($pkg['id']) ? (string) $pkg['id'] : '';
            if (!$id || isset($whmcsBoxes[$id]))
                continue;
            $whmcsBoxes[$id] = [
                'whmcs_id' => $id,
                'whmcs_product_name' => $pkg['name'] ?? $pkg['ProductName'] ?? '',
                'dicker' => []
            ];
        }

        // Build unassigned Dicker pool
        $dickerPool = [];
        $assignedMSCs = [];

        // Collect all assigned manufacturer_stock_codes
        foreach ($whmcsBoxes as $box) {
            foreach ($box['dicker'] as $d) {
                $msc = strtolower(trim($d['manufacturer_stock_code'] ?? ''));
                if ($msc)
                    $assignedMSCs[$msc] = true;
            }
        }

        // Add unassigned dicker packages to pool
        foreach ($mapData['dicker']['packages'] as $pkg) {
            $msc = strtolower(trim($pkg['ManufacturerStockCode'] ?? $pkg['manufacturer_stock_code'] ?? ''));
            if (!$msc || isset($assignedMSCs[$msc]))
                continue;
            $dickerPool[] = $pkg;
        }
        ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Left: Unassigned Dicker Pool -->
            <div class="lg:col-span-1">
                <h3 class="text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">📦 Unassigned Dicker
                    Packages</h3>
                <div id="dickerPool"
                    class="p-3 bg-gray-100 dark:bg-gray-900 rounded border border-gray-300 dark:border-gray-600 min-h-[400px] max-h-[600px] overflow-auto">
                    <ul id="dickerList" class="space-y-2">
                        <?php foreach ($dickerPool as $pkg):
                            $msc = $pkg['ManufacturerStockCode'] ?? $pkg['manufacturer_stock_code'] ?? '';
                            $sub = $pkg['SubscriptionReference'] ?? $pkg['subscription_reference'] ?? '';
                            $desc = $pkg['StockDescription'] ?? $pkg['stock_description'] ?? '';
                            // Normalize to snake_case for consistency
                            $normalizedPkg = [
                                'subscription_reference' => $sub,
                                'stock_description' => $desc,
                                'manufacturer_stock_code' => $msc
                            ];
                            $jsonData = htmlspecialchars(json_encode($normalizedPkg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                            ?>
                            <li class="dicker-item bg-white dark:bg-gray-800 p-3 rounded shadow-sm cursor-grab hover:shadow-md transition-shadow"
                                data-msc="<?php echo htmlspecialchars($msc); ?>" data-json="<?php echo $jsonData; ?>"
                                draggable="true">
                                <div class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    <?php echo htmlspecialchars($sub ?: 'Unknown'); ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($desc); ?>
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 font-mono">
                                    <?php echo htmlspecialchars($msc); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Right: WHMCS Product Boxes -->
            <div class="lg:col-span-2">
                <h3 class="text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">🎯 WHMCS Products (drop
                    Dicker packages here)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[600px] overflow-auto">
                    <?php foreach ($whmcsBoxes as $box): ?>
                        <div class="whmcs-box-container p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600"
                            data-whmcs-id="<?php echo htmlspecialchars($box['whmcs_id']); ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    <?php echo htmlspecialchars($box['whmcs_product_name'] ?: 'WHMCS #' . $box['whmcs_id']); ?>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">ID:
                                <?php echo htmlspecialchars($box['whmcs_id']); ?>
                            </div>
                            <ul class="whmcs-dropzone min-h-[80px] p-2 bg-white dark:bg-gray-900 rounded border-2 border-dashed border-gray-300 dark:border-gray-600 space-y-1"
                                data-box-id="box-<?php echo htmlspecialchars($box['whmcs_id']); ?>">
                                <?php foreach ($box['dicker'] as $d):
                                    $msc = $d['manufacturer_stock_code'] ?? '';
                                    $sub = $d['subscription_reference'] ?? '';
                                    $desc = $d['stock_description'] ?? '';
                                    $jsonData = htmlspecialchars(json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                                    ?>
                                    <li class="dicker-item bg-blue-50 dark:bg-blue-900/20 p-2 rounded shadow-sm cursor-grab hover:shadow-md transition-shadow border border-blue-200 dark:border-blue-700"
                                        data-msc="<?php echo htmlspecialchars($msc); ?>" data-json="<?php echo $jsonData; ?>"
                                        draggable="true">
                                        <div class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                            <?php echo htmlspecialchars($sub ?: 'Unknown'); ?>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            <?php echo htmlspecialchars($desc); ?>
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 font-mono">
                                            <?php echo htmlspecialchars($msc); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center space-x-2">
            <button id="saveMappingBtn2"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">💾 Save
                Mapping</button>
            <button id="rematchBtn2"
                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">🔄
                Rematch (Reload)</button>
            <span id="mappingStatus2" class="ml-4 text-sm text-gray-600 dark:text-gray-300"></span>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

        <?php
        if ($_GET['debug']) {
            monacoEditor(json_decode($mappingContent, true), 'mappingContent');
        }
        ?>
    </div>
</div>