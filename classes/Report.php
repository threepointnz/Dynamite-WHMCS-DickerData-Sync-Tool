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
    }    /**
         * Load exceptions.json file
         * 
         * Simple flat array structure with product_id tracking:
         * [
         *   {
         *     "client_id": 123,           // null or 0 = global exception
         *     "product_id": 456,          // null = unmatched subscription
         *     "manufacturer_stock_code": "p1y:cfq7ttc0lh16:0001:1:",
         *     "expected_whmcs_qty": 8,
         *     "expected_dicker_qty": 4,
         *     "reason": "Client has special discount arrangement",
         *     "apply_to": "client",       // informational only
         *     "subscription_id": null,    // for unmatched subscriptions
         *     "created_at": "2025-11-27",
         *     "created_by": "system"
         *   }
         * ]
         */
    private function loadExceptions()
    {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $this->exceptions = []; // Simple flat array

        if (file_exists($exceptionsPath) && is_readable($exceptionsPath)) {
            $content = @file_get_contents($exceptionsPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->exceptions = $decoded;
                }
            }
        }
    }    /**
         * Check if a quantity mismatch matches an exception rule
         * 
         * Simplified linear search through flat exception array:
         * - Match MSC (manufacturer stock code)
         * - Match client_id if exception has one (null/0 = global)
         * - Match product_id if exception has one (for specificity)
         * - Match quantities exactly
         * 
         * @param int $clientId
         * @param int|null $productId
         * @param string $msc Manufacturer stock code
         * @param int $whmcsQty
         * @param int $dickerQty
         * @return array|false Returns exception data if matched, false otherwise
         */
    private function checkException($clientId, $productId, $msc, $whmcsQty, $dickerQty)
    {
        $msc = strtolower(trim($msc));

        foreach ($this->exceptions as $exception) {
            if (!is_array($exception)) {
                continue;
            }

            // Match MSC
            $exceptionMsc = strtolower(trim($exception['manufacturer_stock_code'] ?? ''));
            if ($exceptionMsc !== $msc) {
                continue;
            }

            // Check if exception is for a specific client
            $exceptionClientId = (int) ($exception['client_id'] ?? 0);
            if ($exceptionClientId > 0 && $exceptionClientId !== $clientId) {
                continue; // Exception is for a different client
            }

            // Check if exception is for a specific product (more specific matching)
            $exceptionProductId = isset($exception['product_id']) ? (int) $exception['product_id'] : null;
            if ($exceptionProductId !== null && $productId !== null && $exceptionProductId !== $productId) {
                continue; // Exception is for a different product
            }            // Check quantities - cast to int to handle float/int type mismatches from round()
            $expectedWhmcs = (int) ($exception['expected_whmcs_qty'] ?? 0);
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);
            $whmcsQty = (int) $whmcsQty;
            $dickerQty = (int) $dickerQty;

            if ($whmcsQty === $expectedWhmcs && $dickerQty === $expectedDicker) {
                return $exception;
            }
        }

        return false;
    }

    /**
     * Check if an unmatched subscription has an applicable exception
     *
     * Simplified linear search:
     * - Match MSC or subscription_id
     * - Match client_id if exception has one
     * - Match expected_dicker_qty
     * 
     * @param int $clientId
     * @param string $subscriptionReference
     * @param string $msc Manufacturer stock code
     * @param int $quantity
     * @return array|false Returns exception data if matched, false otherwise
     */
    private function checkUnmatchedException($clientId, $subscriptionReference, $msc, $quantity)
    {
        $msc = strtolower(trim($msc));

        foreach ($this->exceptions as $exception) {
            if (!is_array($exception)) {
                continue;
            }

            // For unmatched subscriptions, we look for:
            // 1. Exact subscription_id match (highest priority)
            // 2. MSC match with matching client_id (or global)

            $exceptionSubId = $exception['subscription_id'] ?? null;
            $exceptionMsc = strtolower(trim($exception['manufacturer_stock_code'] ?? ''));
            $exceptionClientId = (int) ($exception['client_id'] ?? 0);
            $expectedDicker = (int) ($exception['expected_dicker_qty'] ?? 0);

            // Check quantity first - if it doesn't match, skip
            if ($expectedDicker !== $quantity) {
                continue;
            }

            // Priority 1: Exact subscription reference match
            if (!empty($subscriptionReference) && !empty($exceptionSubId) && (string) $exceptionSubId === (string) $subscriptionReference) {
                // Client match check
                if ($exceptionClientId === 0 || $exceptionClientId === $clientId) {
                    return $exception;
                }
            }

            // Priority 2: MSC match
            if ($exceptionMsc === $msc) {
                // Client match check
                if ($exceptionClientId === 0 || $exceptionClientId === $clientId) {
                    return $exception;
                }
            }
        }
        return false;
    }

    /**
     * Check if a client has an exception for missing tenant ID or expiry date
     * 
     * @param int $clientId
     * @param string $exceptionType Either 'missing_tenant_id' or 'missing_expiry'
     * @return array|false Returns exception data if matched, false otherwise
     */
    private function checkClientException($clientId, $exceptionType)
    {
        foreach ($this->exceptions as $exception) {
            if (!is_array($exception)) {
                continue;
            }

            // Check if this is a client-level exception
            $type = $exception['type'] ?? 'quantity_mismatch'; // Default for backward compatibility
            if ($type !== $exceptionType) {
                continue;
            }

            // Match client ID
            $exceptionClientId = (int) ($exception['client_id'] ?? 0);
            if ($exceptionClientId === $clientId) {
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
            'discrepancy_report' => $matched['discrepancy_report'] ?? [],
            'unmatched_subscriptions_report' => $matched['unmatched_subscriptions_report'] ?? [],
            'exceptions_applied' => $matched['exceptions_applied'] ?? [],
            'unmatched' => [],
            'missing' => [],
            'undercharging' => [],
            'overcharging' => [],
        ];

        // process the 
        return $data;
    }
    public function getExceptions()
    {
        return $this->exceptions;
    }    /**
         * Filter problematic clients based on client-level exceptions
         * 
         * @param array $problematicClients Array of clients from WHMCS::getProblematicClients()
         * @return array Filtered array with exceptions applied
         */
    public function filterProblematicClients($problematicClients)
    {
        $filtered = [];
        $exceptionsApplied = [];

        foreach ($problematicClients as $client) {
            $clientId = (int) ($client['id'] ?? 0);
            $hasTenantId = !empty($client['tenantId']) && $client['tenantId'] != 0;
            $hasExpiry = !empty($client['expiry']) && $client['expiry'] != 0;

            // Check for missing tenant ID exception
            if (!$hasTenantId) {
                $tenantException = $this->checkClientException($clientId, 'missing_tenant_id');
                if ($tenantException !== false) {
                    $exceptionsApplied[] = [
                        'client_id' => $clientId,
                        'companyname' => $client['companyname'] ?? 'Unknown',
                        'type' => 'missing_tenant_id',
                        'reason' => $tenantException['reason'] ?? 'No reason provided',
                        'created_at' => $tenantException['created_at'] ?? '',
                        'created_by' => $tenantException['created_by'] ?? ''
                    ];
                    // Mark as having exception so we can skip it
                    $client['has_tenant_exception'] = true;
                    $client['tenant_exception_reason'] = $tenantException['reason'] ?? 'No reason provided';
                }
            }

            // Check for missing expiry exception
            if (!$hasExpiry) {
                $expiryException = $this->checkClientException($clientId, 'missing_expiry');
                if ($expiryException !== false) {
                    $exceptionsApplied[] = [
                        'client_id' => $clientId,
                        'companyname' => $client['companyname'] ?? 'Unknown',
                        'type' => 'missing_expiry',
                        'reason' => $expiryException['reason'] ?? 'No reason provided',
                        'created_at' => $expiryException['created_at'] ?? '',
                        'created_by' => $expiryException['created_by'] ?? ''
                    ];
                    // Mark as having exception
                    $client['has_expiry_exception'] = true;
                    $client['expiry_exception_reason'] = $expiryException['reason'] ?? 'No reason provided';
                }
            }

            // Only include client if they still have issues without exceptions
            if (
                (!$hasTenantId && empty($client['has_tenant_exception'])) ||
                (!$hasExpiry && empty($client['has_expiry_exception']))
            ) {
                $filtered[] = $client;
            }
        }

        return [
            'filtered' => $filtered,
            'exceptions_applied' => $exceptionsApplied
        ];
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
                    'exceptions_applied' => (function () use ($cid) {
                        $clientExceptions = [];
                        if (is_array($this->exceptions)) {
                            foreach ($this->exceptions as $ex) {
                                if (!is_array($ex)) {
                                    continue;
                                }
                                $exClient = (int) ($ex['client_id'] ?? 0);
                                if ($exClient === 0 || $exClient === $cid) {
                                    $clientExceptions[] = $ex;
                                }
                            }
                        }
                        return $clientExceptions;
                    })(),
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
                    'has_exceptions' => false, // Will be set later during matrix processing
                ];
            } else {
                $results[$cid]['products'][$pid]['qty'] += (int) $row['product_qty'];
            }
        }
        //return $results;
        return $this->buildSubscriptionNameMatrix($results);
    }

    public function findExceptionByKeyValue($array, $key, $value)
    {
        foreach ($array as $item) {
            if (is_array($item) && isset($item[$key]) && $item[$key] == $value) {
                return $item;
            }
        }
        return null;
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

                        // Check for exception RIGHT HERE when building the matrix
                        $exception = $this->checkException(
                            $client['id'],
                            $productId,
                            $subData['subscription']['ManufacturerStockCode'] ?? '',
                            $proportionalProductQty,
                            $subData['sub_qty']
                        );

                        $matrixEntry = [
                            'client_id' => $client['id'],
                            'product_id' => $productId,
                            'subscription_reference' => $subData['subscription']['SubscriptionReference'] ?? '',
                            'stock_description' => $subData['subscription']['StockDescription'] ?? '',
                            'manufacturer_stock_code' => $subData['subscription']['ManufacturerStockCode'] ?? '',
                            'matched_product_name' => $product['product_name'],
                            'matched_via' => 'mapping.json',
                            'product_qty' => $proportionalProductQty,
                            'sub_qty' => $subData['sub_qty'],
                            'has_exception' => ($exception !== false),
                        ];

                        // Add exception details if matched
                        if ($exception !== false) {
                            $matrixEntry['exception_reason'] = $exception['reason'] ?? 'No reason provided';
                            $matrixEntry['exception_created_at'] = $exception['created_at'] ?? '';
                            $matrixEntry['exception_created_by'] = $exception['created_by'] ?? '';

                            // Mark product as having active exceptions
                            if (isset($results[$cid]['products'][$productId])) {
                                $results[$cid]['products'][$productId]['has_exceptions'] = true;
                            }
                        }

                        $results[$cid]['matrix'][] = $matrixEntry;
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

        // Loop through matrix and generate discrepancy report
        foreach ($results as $clientIndex => $client) {
            // Check for quantity mismatches in matched subscriptions
            if (isset($client['matrix'])) {
                foreach ($client['matrix'] as $entry) {
                    $clientId = $client['id'];

                    // If has_exception is already set to true, record it
                    if (!empty($entry['has_exception'])) {
                        $exceptionsApplied[] = [
                            'client_id' => $clientId,
                            'companyname' => $client['companyname'],
                            'subscription_reference' => $entry['subscription_reference'],
                            'stock_description' => $entry['stock_description'],
                            'manufacturer_stock_code' => $entry['manufacturer_stock_code'] ?? '',
                            'product_name' => $entry['matched_product_name'],
                            'product_qty' => $entry['product_qty'],
                            'sub_qty' => $entry['sub_qty'],
                            'difference' => $entry['sub_qty'] - $entry['product_qty'],
                            'exception_reason' => $entry['exception_reason'] ?? 'No reason provided',
                            'exception_created_at' => $entry['exception_created_at'] ?? '',
                            'exception_created_by' => $entry['exception_created_by'] ?? ''
                        ];
                        continue; // Skip adding to discrepancy report
                    }

                    // If quantities don't match and no exception, add to discrepancy report
                    if ($entry['product_qty'] != $entry['sub_qty']) {
                        $report[] = [
                            'client_id' => $clientId,
                            'companyname' => $client['companyname'],
                            'subscription_reference' => $entry['subscription_reference'],
                            'stock_description' => $entry['stock_description'],
                            'manufacturer_stock_code' => $entry['manufacturer_stock_code'] ?? '',
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