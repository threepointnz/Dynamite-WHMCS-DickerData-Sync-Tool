<?php
/**
 * Report Class - Uses mapping.json for product matching
 * 
 * What do we report back?
 * - whmcs client missing tenantid (WHMCS::getProblematicClients())
 * - whmcs client missing expiry (WHMCS::getProblematicClients())
 * - overcharging (whmcs_qty > dicker_qty)
 * - undercharging (whmcs_qty < dicker_qty)
 * - unmatched whmcs products
 * - unmatched dicker subscriptions
 */

if (!class_exists('WHMCS') && file_exists(__DIR__ . '/WHMCS.php')) {
    require_once __DIR__ . '/WHMCS.php';
}

class Report
{
    private $whmcs_data;
    private $dicker_data;
    private $mappings;
    private $exceptions;

    public function __construct($whmcs_data, $dicker_data)
    {
        $this->whmcs_data = $whmcs_data;
        $this->dicker_data = $dicker_data;
        $this->loadMappings();
        $this->loadExceptions();
    }

    /**
     * Load mapping.json file
     */
    private function loadMappings()
    {
        $mappingsPath = __DIR__ . '/../mapping.json';
        $this->mappings = ['d' => []];

        if (file_exists($mappingsPath) && is_readable($mappingsPath)) {
            $content = @file_get_contents($mappingsPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['d'])) {
                    $this->mappings = $decoded;
                }
            }
        }
    }

    /**
     * Load exceptions.json file
     * Format: [
     *   {
     *     "client_id": 123,
     *     "expected_whmcs_qty": 8,
     *     "expected_dicker_qty": 4,
     *     "reason": "Client has special discount arrangement",
     *     "manufacturer_stock_code": "p1y:cfq7ttc0lh16:0001:1:",
     *     "product_id": 456,
     *     "apply_to": "client", // or "unmatched"
     *     "created_at": "2025-11-27",
     *   }
     * ]
     */
    private function loadExceptions()
    {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $this->exceptions = [
            'client' => [],        // client_id => msc => exception (per-client)
            'global' => [],        // msc => exception (apply to any client)
            'unmatched_by_sub' => [] // subscription_reference => exception
        ];

        if (file_exists($exceptionsPath) && is_readable($exceptionsPath)) {
            $content = @file_get_contents($exceptionsPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    // decoded may be an associative array keyed by numeric index or a plain list
                    foreach ($decoded as $exception) {
                        if (!is_array($exception))
                            continue;

                        $clientId = (int) ($exception['client_id'] ?? 0);
                        $msc = strtolower(trim($exception['manufacturer_stock_code'] ?? ''));
                        $applyTo = $exception['apply_to'] ?? 'client';
                        $subId = $exception['subscription_id'] ?? null;

                        if ($msc === '')
                            continue;

                        if ($applyTo === 'unmatched') {
                            // If we have a subscription id/reference, index by that too
                            if (!empty($subId)) {
                                $this->exceptions['unmatched_by_sub'][(string) $subId] = $exception;
                            }

                            // Also register as a global MSC-level unmatched exception
                            $this->exceptions['global'][$msc] = $exception;
                        } else {
                            // client-scoped
                            if ($clientId > 0) {
                                if (!isset($this->exceptions['client'][$clientId])) {
                                    $this->exceptions['client'][$clientId] = [];
                                }
                                $this->exceptions['client'][$clientId][$msc] = $exception;
                            } else {
                                // client_id == 0 treat as global exception too
                                $this->exceptions['global'][$msc] = $exception;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Check if a quantity mismatch matches an exception rule
     * 
     * @param int $clientId
     * @param string $msc Manufacturer stock code
     * @param int $whmcsQty
     * @param int $dickerQty
     * @return array|false Returns exception data if matched, false otherwise
     */
    private function checkException($clientId, $msc, $whmcsQty, $dickerQty)
    {
        $msc = strtolower(trim($msc));

        // Temporary debug storage
        static $debugInfo = [];

        // 1) check client-scoped exception
        if (isset($this->exceptions['client'][$clientId][$msc])) {
            $exception = $this->exceptions['client'][$clientId][$msc];
            $expectedWhmcs = (int) ($exception['expected_whmcs_qty'] ?? 0);
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);

            if ($whmcsQty === $expectedWhmcs && $dickerQty === $expectedDicker) {
                return $exception;
            } else {
                // Store debug info for non-matching exceptions
                $debugInfo[] = [
                    'client_id' => $clientId,
                    'msc' => $msc,
                    'actual_whmcs' => $whmcsQty,
                    'actual_dicker' => $dickerQty,
                    'expected_whmcs' => $expectedWhmcs,
                    'expected_dicker' => $expectedDicker,
                    'reason' => $exception['reason'] ?? ''
                ];
                // Store in a global so we can access it later
                $GLOBALS['exception_debug_info'] = $debugInfo;
            }
        }

        // 2) check global MSC-scoped exception
        if (isset($this->exceptions['global'][$msc])) {
            $exception = $this->exceptions['global'][$msc];
            $expectedWhmcs = (int) ($exception['expected_whmcs_qty'] ?? 0);
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);

            if ($whmcsQty === $expectedWhmcs && $dickerQty === $expectedDicker) {
                return $exception;
            }
        }

        return false;
    }

    /**
     * Check if an unmatched subscription has an applicable exception
     */
    private function checkUnmatchedException($clientId, $subscriptionReference, $msc, $quantity)
    {
        $msc = strtolower(trim($msc));

        // 1) direct subscription id match
        if (!empty($subscriptionReference) && isset($this->exceptions['unmatched_by_sub'][(string) $subscriptionReference])) {
            $exception = $this->exceptions['unmatched_by_sub'][(string) $subscriptionReference];
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);
            if ($quantity === $expectedDicker) {
                return $exception;
            }
        }

        // 2) global MSC-level unmatched exception
        if (isset($this->exceptions['global'][$msc])) {
            $exception = $this->exceptions['global'][$msc];
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);
            // if expected_dicker_qty matches subscription quantity, treat as exception
            if ($expectedDicker === $quantity) {
                return $exception;
            }
        }

        // 3) client-scoped exception could also apply if client has an explicit exception for this MSC
        if (isset($this->exceptions['client'][$clientId][$msc])) {
            $exception = $this->exceptions['client'][$clientId][$msc];
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);
            if ($expectedDicker === $quantity) {
                return $exception;
            }
        }

        return false;
    }
    public function generate()
    {
        $matched = $this->matchSubscriptions();

        $data = [
            'whmcs' => $this->whmcs_data,
            'dicker' => $this->dicker_data,
            'matched' => $matched,
            'exceptions_applied' => $matched['exceptions_applied'] ?? [],
            'unmatched' => [],
            'missing' => [],
            'undercharging' => [],
            'overcharging' => [],
        ];
        return $data;
    }

    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * matchSubscriptions
     * 
     * @description Match WHMCS clients and products to Dicker subscriptions using intelligent matching
     */
    public function matchSubscriptions()
    {
        $results = [];

        foreach ($this->whmcs_data as $row) {
            $cid = (int) $row['client_id'];
            $pid = (int) $row['product_id'];
            $estimated_365_count = 0;

            if (!isset($results[$cid])) {
                $results[$cid] = [
                    'id' => $cid,
                    'estimated_365_count' => $estimated_365_count,
                    'companyname' => empty($row['companyname']) ? $row['firstname'] . ' ' . $row['lastname'] : $row['companyname'],
                    'expiry' => $row['field7_value'],
                    'tenantID' => $row['field16_value'],
                    'products' => [],
                    'subscriptions' => $this->findMatchingSubscriptions($row['field16_value'], $this->dicker_data),
                ];
            }

            // gets removed now we are doing matching via mapping.json
            if (strpos($row['product_name'], '365') !== false || strpos($row['product_name'], 'Exchange') !== false) {
                $results[$cid]['estimated_365_count'] = (int) $results[$cid]['estimated_365_count'] + (int) $row['product_qty'];
            }

            if (!isset($results[$cid]['products'][$pid])) {
                $results[$cid]['products'][$pid] = [
                    'product_id' => $pid,
                    'product_name' => $row['product_name'],
                    'qty' => (int) $row['product_qty'],
                    'likely_365' => (strpos($row['product_name'], '365') !== false || strpos($row['product_name'], 'Exchange') !== false) ? true : false,
                ];
            } else {
                $results[$cid]['products'][$pid]['qty'] += (int) $row['product_qty'];
            }
        }
        //return $results;
        return $this->buildSubscriptionNameMatrix($results);
    }
    /**
     * Build a lookup table from mapping.json for fast product->dicker matching
     * Returns: ['whmcs_id' => ['msc1' => true, 'msc2' => true, ...]]
     */
    private function buildMappingLookup()
    {
        $lookup = [];

        foreach ($this->mappings['d'] as $mapping) {
            $whmcsId = (int) $mapping['whmcs_id'];
            $lookup[$whmcsId] = [];

            if (isset($mapping['dicker']) && is_array($mapping['dicker'])) {
                foreach ($mapping['dicker'] as $dickerItem) {
                    $msc = strtolower(trim($dickerItem['manufacturer_stock_code'] ?? ''));
                    if ($msc) {
                        $lookup[$whmcsId][$msc] = [
                            'subscription_reference' => $dickerItem['subscription_reference'] ?? '',
                            'stock_description' => $dickerItem['stock_description'] ?? ''
                        ];
                    }
                }
            }
        }

        return $lookup;
    }

    /**
     * Build subscription matrix using mapping.json instead of similarity scoring
     */
    public function buildSubscriptionNameMatrix($results)
    {
        $mappingLookup = $this->buildMappingLookup();
        foreach ($results as $cid => $client) {
            $results[$cid]['matrix'] = [];
            $results[$cid]['unmatched_subscriptions'] = [];
            $processedSubscriptions = []; // Track which subscription indices we've processed

            // For each WHMCS product the client has
            foreach ($client['products'] as $product) {
                $productId = (int) $product['product_id'];
                $productQty = (int) $product['qty'];

                // Check if we have a mapping for this product
                if (!isset($mappingLookup[$productId]) || empty($mappingLookup[$productId])) {
                    // No mapping defined for this product - skip it (it's not an O365 product we track)
                    continue;
                }

                $expectedMSCs = $mappingLookup[$productId];
                $matchedSubsByMSC = []; // Group subscriptions by MSC to avoid duplicates

                // Find matching subscriptions for this product and group by MSC
                foreach ($client['subscriptions'] as $subIndex => $subscription) {
                    $msc = strtolower(trim($subscription['ManufacturerStockCode'] ?? ''));

                    // Check if this MSC is expected for this product and subscription hasn't been processed
                    if ($msc && isset($expectedMSCs[$msc]) && !isset($processedSubscriptions[$subIndex])) {
                        // Group by MSC - sum quantities if multiple subscriptions have same MSC
                        if (!isset($matchedSubsByMSC[$msc])) {
                            $matchedSubsByMSC[$msc] = [
                                'subscription' => $subscription, // Use first subscription for reference data
                                'sub_qty' => 0,
                                'indices' => []
                            ];
                        }
                        $matchedSubsByMSC[$msc]['sub_qty'] += (int) $subscription['ConfirmedQuantity'];
                        $matchedSubsByMSC[$msc]['indices'][] = $subIndex;
                        $processedSubscriptions[$subIndex] = true; // Mark this subscription as processed
                    }
                }

                // If we found matching subscriptions, create matrix entries
                if (!empty($matchedSubsByMSC)) {
                    $totalSubQty = array_sum(array_column($matchedSubsByMSC, 'sub_qty'));

                    foreach ($matchedSubsByMSC as $msc => $subData) {
                        // Calculate proportional product quantity for this subscription
                        if ($totalSubQty > 0) {
                            $proportionalProductQty = round(($subData['sub_qty'] / $totalSubQty) * $productQty);
                        } else {
                            $proportionalProductQty = 0;
                        }

                        $results[$cid]['matrix'][] = [
                            'subscription_reference' => $subData['subscription']['SubscriptionReference'] ?? '',
                            'stock_description' => $subData['subscription']['StockDescription'] ?? '',
                            'manufacturer_stock_code' => $subData['subscription']['ManufacturerStockCode'] ?? '',
                            'matched_product_name' => $product['product_name'],
                            'matched_via' => 'mapping.json',
                            'product_qty' => $proportionalProductQty,
                            'sub_qty' => $subData['sub_qty'],
                        ];
                    }
                } else {
                    // Product has no matching subscriptions in Dicker
                    // This could mean undercharging or the product isn't active
                    // We'll report this in discrepancy report
                }
            }

            // Find unmatched subscriptions (subscriptions without a mapped product)
            foreach ($client['subscriptions'] as $subIndex => $subscription) {
                // If this subscription wasn't processed, it's unmatched
                if (!isset($processedSubscriptions[$subIndex])) {
                    $results[$cid]['unmatched_subscriptions'][] = [
                        'subscription_reference' => $subscription['SubscriptionReference'] ?? '',
                        'stock_description' => $subscription['StockDescription'] ?? '',
                        'manufacturer_stock_code' => $subscription['ManufacturerStockCode'] ?? '',
                        'quantity' => (int) ($subscription['ConfirmedQuantity'] ?? 0),
                        'reason' => 'No mapping found in mapping.json'
                    ];
                }
            }
        }

        return $this->generateDiscrepancyReport($results);
    }
    public function generateDiscrepancyReport($results)
    {
        $report = [];
        $unmatchedReport = [];
        $exceptionsApplied = [];

        // Loop through matrix and generate discrepancy report based on the product_qty and sub_qty for each entry
        foreach ($results as $clientIndex => $client) {
            // Check for quantity mismatches in matched subscriptions
            if (isset($client['matrix'])) {
                foreach ($client['matrix'] as $entry) {
                    if ($entry['product_qty'] != $entry['sub_qty']) {
                        $msc = $entry['manufacturer_stock_code'] ?? '';
                        $clientId = $client['id'];                        // Check if this mismatch has an exception
                        $exception = $this->checkException(
                            $clientId,
                            $msc,
                            $entry['product_qty'],
                            $entry['sub_qty']
                        );

                        if ($exception !== false) {
                            // Exception matched - record it but don't report as issue
                            $exceptionsApplied[] = [
                                'client_id' => $clientId,
                                'companyname' => $client['companyname'],
                                'subscription_reference' => $entry['subscription_reference'],
                                'stock_description' => $entry['stock_description'],
                                'manufacturer_stock_code' => $msc,
                                'product_name' => $entry['matched_product_name'],
                                'product_qty' => $entry['product_qty'],
                                'sub_qty' => $entry['sub_qty'],
                                'difference' => $entry['sub_qty'] - $entry['product_qty'],
                                'exception_reason' => $exception['reason'] ?? 'No reason provided',
                                'exception_created_at' => $exception['created_at'] ?? '',
                                'exception_created_by' => $exception['created_by'] ?? ''
                            ];
                            continue; // Skip adding to discrepancy report
                        }

                        // No exception - add to discrepancy report
                        $report[] = [
                            'client_id' => $clientId,
                            'companyname' => $client['companyname'],
                            'subscription_reference' => $entry['subscription_reference'],
                            'stock_description' => $entry['stock_description'],
                            'manufacturer_stock_code' => $msc,
                            'product_name' => $entry['matched_product_name'],
                            'product_qty' => $entry['product_qty'],
                            'sub_qty' => $entry['sub_qty'],
                            'difference' => $entry['sub_qty'] - $entry['product_qty'],
                            'state' => ($entry['product_qty'] > $entry['sub_qty']) ? 'overcharging' : 'undercharging'
                        ];
                    }
                }
            }

            // Add unmatched subscriptions to report
            if (isset($client['unmatched_subscriptions']) && !empty($client['unmatched_subscriptions'])) {
                $filteredUnmatched = [];
                foreach ($client['unmatched_subscriptions'] as $unmatched) {
                    $msc = $unmatched['manufacturer_stock_code'] ?? '';
                    $subscriptionRef = $unmatched['subscription_reference'] ?? '';
                    $quantity = (int) ($unmatched['quantity'] ?? 0);

                    // Check for exception applying to this unmatched subscription
                    $unmatchedException = $this->checkUnmatchedException($client['id'], $subscriptionRef, $msc, $quantity);

                    if ($unmatchedException !== false) {
                        // record applied exception but do not include in unmatched report
                        $exceptionsApplied[] = [
                            'client_id' => $client['id'],
                            'companyname' => $client['companyname'],
                            'subscription_reference' => $subscriptionRef,
                            'stock_description' => $unmatched['stock_description'] ?? '',
                            'manufacturer_stock_code' => $msc,
                            'quantity' => $quantity,
                            'reason' => $unmatchedException['reason'] ?? 'No reason provided',
                            'exception_created_at' => $unmatchedException['created_at'] ?? '',
                            'exception_created_by' => $unmatchedException['created_by'] ?? ''
                        ];
                        // skip adding to filtered list
                        continue;
                    }

                    // keep in filtered unmatched list and also add to global unmatched report
                    $filteredUnmatched[] = $unmatched;
                    $unmatchedReport[] = [
                        'client_id' => $client['id'],
                        'companyname' => $client['companyname'],
                        'subscription_reference' => $unmatched['subscription_reference'],
                        'stock_description' => $unmatched['stock_description'],
                        'manufacturer_stock_code' => $unmatched['manufacturer_stock_code'] ?? '',
                        'quantity' => $unmatched['quantity'],
                        'reason' => $unmatched['reason'],
                        'state' => 'unmatched'
                    ];
                }

                // replace client's unmatched_subscriptions with filtered list so UI doesn't show exceptions
                $results[$clientIndex]['unmatched_subscriptions'] = $filteredUnmatched;
            }
        }

        $results['discrepancy_report'] = $report;
        $results['unmatched_subscriptions_report'] = $unmatchedReport;
        $results['exceptions_applied'] = $exceptionsApplied;

        return $results;
    }

    /**
     * Find matching Dicker Data subscriptions for given tenant IDs
     * 
     * @param array $tenantIDs Array of tenant ID strings (may contain empty/whitespace)
     * @param array $organisedSubscriptions Associative array of subscriptions keyed by tenant ID
     * @return array Array of matching subscription records
     */
    private function findMatchingSubscriptions($tenantIDs, $organisedSubscriptions)
    {
        $matches = [];

        foreach ($tenantIDs as $tid) {
            $tid = trim((string) $tid);

            // Skip empty tenant IDs
            if ($tid === '') {
                continue;
            }

            // Check if we have subscriptions for this tenant ID (support arrays and objects)
            $subscriptionData = null;
            if (is_array($organisedSubscriptions) && array_key_exists($tid, $organisedSubscriptions)) {
                $subscriptionData = $organisedSubscriptions[$tid];
            } elseif (is_object($organisedSubscriptions) && (isset($organisedSubscriptions->$tid) || property_exists($organisedSubscriptions, $tid))) {
                $subscriptionData = $organisedSubscriptions->$tid;
            }

            if ($subscriptionData === null) {
                continue;
            }

            // Handle both single subscription and array of subscriptions
            if (is_array($subscriptionData)) {
                // Use array unpacking (PHP 7.4+) to merge arrays
                $matches = [...$matches, ...$subscriptionData];
            } else {
                $matches[] = $subscriptionData;
            }
        }

        return $matches;
    }
}