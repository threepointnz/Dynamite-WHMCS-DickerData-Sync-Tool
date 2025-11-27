<?php
// import the mapping.json file.
$mappingsPath = __DIR__ . '/../mappings.json';
$mappings = [];

if (file_exists($mappingsPath) && is_readable($mappingsPath)) {
    $content = @file_get_contents($mappingsPath);
    if ($content !== false) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $mappings = $decoded;
        }
    }
}

/**
 * What do we need to report back?
 * 
 * whmcs client missing tenantid (WHMCS::getProblematicClients())
 * whmcs client missing expiry (WHMCS::getProblematicClients())
 * overcharging (whmcs_qty > dicker_qty)
 * undercharging (whmcs_qty < dicker_qty)
 * unmatched whmcs products
 * unmatched dicker products
 * missing name mappings
 */

if (!class_exists('WHMCS') && file_exists(__DIR__ . '/WHMCS.php')) {
    require_once __DIR__ . '/WHMCS.php';
}

class Report
{
    private $whmcs_data;
    private $dicker_data;

    public function __construct($whmcs_data, $dicker_data)
    {
        $this->whmcs_data = $whmcs_data;
        $this->dicker_data = $dicker_data;
    }

    public function generate()
    {
        $data = [
            'whmcs' => $this->whmcs_data,
            'dicker' => $this->dicker_data,
            'matched' => $this->matchSubscriptions(),
            'unmatched' => [],
            'missing' => [],
            'undercharging' => [],
            'overcharging' => [],
        ];
        return $data;
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

        return $this->buildSubscriptionNameMatrix($results);
    }


    public function buildSubscriptionNameMatrix($results)
    {
        foreach ($results as $cid => $client) {
            $results[$cid]['matrix'] = [];
            $results[$cid]['unmatched_subscriptions'] = []; // Track unmatched subscriptions

            // First, group subscriptions by their best matching product to handle allocation
            $productGroups = [];

            foreach ($client['subscriptions'] as $subscription) {
                $stockDescription = isset($subscription['StockDescription']) ? $subscription['StockDescription'] : null;
                $bestMatch = $this->findBestProductMatch($subscription['SubscriptionReference'], $client['products'], $stockDescription);

                if ($bestMatch !== null) {
                    $productId = $bestMatch['product_id'];

                    if (!isset($productGroups[$productId])) {
                        $productGroups[$productId] = [
                            'product_name' => $bestMatch['product_name'],
                            'total_product_qty' => $bestMatch['qty'],
                            'subscriptions' => []
                        ];
                    }

                    $productGroups[$productId]['subscriptions'][] = [
                        'subscription' => $subscription,
                        'stock_description' => $stockDescription,
                        'similarity' => $bestMatch['similarity'],
                        'sub_qty' => (int) $subscription['ConfirmedQuantity']
                    ];
                } else {
                    // No match found - add to unmatched list
                    $results[$cid]['unmatched_subscriptions'][] = [
                        'subscription_reference' => $subscription['SubscriptionReference'],
                        'stock_description' => $stockDescription,
                        'quantity' => (int) $subscription['ConfirmedQuantity'],
                        'reason' => 'No matching product found (score below threshold or tier mismatch)'
                    ];
                }
            }

            // Now create matrix entries with proper quantity allocation
            foreach ($productGroups as $group) {
                $totalSubQty = array_sum(array_column($group['subscriptions'], 'sub_qty'));
                $productQty = $group['total_product_qty'];

                foreach ($group['subscriptions'] as $subData) {
                    // Calculate proportional product quantity for this subscription
                    if ($totalSubQty > 0) {
                        $proportionalProductQty = round(($subData['sub_qty'] / $totalSubQty) * $productQty);
                    } else {
                        $proportionalProductQty = 0;
                    }

                    $results[$cid]['matrix'][] = [
                        'subscription_reference' => $subData['subscription']['SubscriptionReference'],
                        'stock_description' => $subData['stock_description'],
                        'matched_product_name' => $group['product_name'],
                        'similarity_percent' => $subData['similarity'],
                        'product_qty' => $proportionalProductQty,
                        'sub_qty' => $subData['sub_qty'],
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

        // loop through matrix and generate discrepancy report based on the product_qty and sub_qty for each entry
        foreach ($results as $client) {
            // Check for quantity mismatches in matched subscriptions
            if (isset($client['matrix'])) {
                foreach ($client['matrix'] as $entry) {
                    if ($entry['product_qty'] != $entry['sub_qty']) {
                        $report[] = [
                            'client_id' => $client['id'],
                            'companyname' => $client['companyname'],
                            'subscription_reference' => $entry['subscription_reference'],
                            'stock_description' => $entry['stock_description'],
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
                foreach ($client['unmatched_subscriptions'] as $unmatched) {
                    $unmatchedReport[] = [
                        'client_id' => $client['id'],
                        'companyname' => $client['companyname'],
                        'subscription_reference' => $unmatched['subscription_reference'],
                        'stock_description' => $unmatched['stock_description'],
                        'quantity' => $unmatched['quantity'],
                        'reason' => $unmatched['reason'],
                        'state' => 'unmatched'
                    ];
                }
            }
        }

        $results['discrepancy_report'] = $report;
        $results['unmatched_subscriptions_report'] = $unmatchedReport;

        return $results;
    }

    /**
     * Find the best matching product for a subscription using intelligent matching
     */
    private function findBestProductMatch($subscriptionRef, $products, $stockDescription = null)
    {
        $bestMatch = null;
        $highestScore = 0;

        foreach ($products as $product) {
            $score = $this->calculateMatchScore($subscriptionRef, $product['product_name'], $stockDescription);

            if ($score > $highestScore && $score >= 40) {
                $highestScore = $score;
                $bestMatch = [
                    'product_name' => $product['product_name'],
                    'similarity' => round($score, 1),
                    'product_id' => $product['product_id'],
                    'qty' => $product['qty']
                ];
            }
        }

        return $bestMatch;
    }


    /**
     * Calculate intelligent match score between subscription and product names
     */
    private function calculateMatchScore($subscription, $product, $stockDescription = null)
    {
        // Normalize strings for comparison
        $sub = strtolower(trim($subscription));
        $prod = strtolower(trim($product));
        $stock = $stockDescription ? strtolower(trim($stockDescription)) : '';

        // Extract plan numbers if present
        $subPlan = $this->extractPlanNumber($sub);
        $prodPlan = $this->extractPlanNumber($prod);

        // Base similarity using similar_text
        similar_text($sub, $prod, $baseSimilarity);

        // Apply intelligent scoring adjustments
        $score = $baseSimilarity;

        // Plan number matching logic
        if ($subPlan !== null && $prodPlan !== null) {
            if ($subPlan === $prodPlan) {
                $score += 20; // Big bonus for exact plan match
            } else {
                $score -= 30; // Heavy penalty for plan mismatch
            }
        } elseif ($subPlan !== null && $prodPlan === null) {
            if ($subPlan === '1') {
                $score += 5; // Small bonus for Plan 1 when product has no plan
            } else {
                $score -= 15; // Penalty for Plan 2+ when product has no plan
            }
        }

        // Billing period matching
        if ($stockDescription) {
            $isMonthlyStock = strpos($stock, '1mth') !== false || strpos($stock, 'month') !== false;
            $isYearlyStock = strpos($stock, '1yr') !== false || strpos($stock, 'year') !== false;

            $isMonthlyProduct = strpos($prod, 'month') !== false;
            $isYearlyProduct = strpos($prod, 'year') !== false;

            // Strong bonus for matching billing periods
            if ($isMonthlyStock && $isMonthlyProduct) {
                $score += 25;
            } elseif ($isYearlyStock && $isYearlyProduct) {
                $score += 25;
            } elseif ($isMonthlyStock && $isYearlyProduct) {
                $score -= 40;
            } elseif ($isYearlyStock && $isMonthlyProduct) {
                $score -= 40;
            }
        }

        // Product type bonuses
        if (strpos($sub, 'apps') !== false && strpos($prod, 'apps') !== false) {
            $score += 10;
        }
        if (strpos($sub, 'exchange') !== false && strpos($prod, 'exchange') !== false) {
            $score += 10;
        }
        if (strpos($sub, 'business') !== false && strpos($prod, 'business') !== false) {
            $score += 10;
        }

        // Additional StockDescription matching bonuses
        if ($stockDescription) {
            // Match M365 vs Office 365
            if (
                (strpos($stock, 'm365') !== false || strpos($stock, 'microsoft 365') !== false)
                && strpos($prod, '365') !== false
            ) {
                $score += 15;
            }

            // Match Exchange Online
            if (strpos($stock, 'exchange online') !== false && strpos($prod, 'exchange') !== false) {
                $score += 15;
            }

            // Match Power BI
            if (strpos($stock, 'power bi') !== false && strpos($prod, 'power bi') !== false) {
                $score += 15;
            }
        }

        // CRITICAL: Check Basic vs Standard mismatch LAST - this is an absolute disqualifier
        $subIsBasic = strpos($sub, 'basic') !== false || strpos($stock, 'basic') !== false;
        $subIsStandard = false;

        // Only check for Standard if NOT Basic
        if (!$subIsBasic) {
            $subIsStandard = strpos($sub, 'standard') !== false || strpos($sub, 'standar') !== false;

            // Check for STD abbreviation ONLY if subscription name has "standard" or "standar"
            if (strpos($stock, 'std') !== false && (strpos($sub, 'standard') !== false || strpos($sub, 'standar') !== false)) {
                $subIsStandard = true;
            }
        }

        // For product detection
        $prodIsBasic = strpos($prod, 'basic') !== false;
        $prodIsStandard = strpos($prod, 'standard') !== false;

        // ABSOLUTE DISQUALIFICATION for tier mismatch
        if (($subIsBasic && $prodIsStandard) || ($subIsStandard && $prodIsBasic)) {
            return 0; // Complete mismatch - do not match
        }

        // Strong bonus for correct tier match
        if (($subIsBasic && $prodIsBasic) || ($subIsStandard && $prodIsStandard)) {
            $score += 35;
        }

        return min(100, max(0, $score)); // Clamp between 0-100
    }

    /**
     * Extract plan number from a product/subscription name
     */
    private function extractPlanNumber($name)
    {
        // Look for "Plan X" or "(Plan X)" patterns
        if (preg_match('/\(plan\s+(\d+)\)/i', $name, $matches)) {
            return $matches[1];
        }
        if (preg_match('/plan\s+(\d+)/i', $name, $matches)) {
            return $matches[1];
        }
        return null;
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