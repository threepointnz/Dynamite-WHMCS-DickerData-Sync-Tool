<?php
/**
 * Class to intereact with mail() and send HTML email notifications
 * Must work with php 7.4
 */
class Mail
{

    public function send($to, $subject, $message)
    {
        // Prepare headers (use env override if present)
        $from = $_ENV['MAIL_FROM'] ?? 'mail@domain.co.nz';
        $headers = [];
        $headers[] = 'From: ' . $from;
        $headers[] = 'Reply-To: ' . $from;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headersStr = implode("\r\n", $headers);

        // Use the mail() function to send the email with headers
        $result = mail($to, $subject, $message, $headersStr);

        // return an object ready to respond to an api caller
        return (object) [
            'success' => (bool) $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email',
            'headers' => $headers // helpful for debugging
        ];
    }

    /**
     * Generate comprehensive daily report email
     * 
     * @param array $problematicClients Clients with missing tenant ID or expiry (already filtered)
     * @param array $discrepancies Quantity mismatches (already filtered)
     * @param array $unmatchedSubscriptions Unmatched subscriptions (already filtered)
     * @param array $quantityExceptions Applied quantity/subscription exceptions
     * @param array $clientExceptions Applied client-level exceptions
     * @return string Formatted email content
     */
    public function generateDailyReport($problematicClients, $discrepancies, $unmatchedSubscriptions, $quantityExceptions = [], $clientExceptions = [])
    {
        // Defensive checks - ensure all params are arrays
        $problematicClients = is_array($problematicClients) ? $problematicClients : [];
        $discrepancies = is_array($discrepancies) ? $discrepancies : [];
        $unmatchedSubscriptions = is_array($unmatchedSubscriptions) ? $unmatchedSubscriptions : [];
        $quantityExceptions = is_array($quantityExceptions) ? $quantityExceptions : [];
        $clientExceptions = is_array($clientExceptions) ? $clientExceptions : [];

        // Calculate statistics
        $totalProblematic = count($problematicClients);
        $totalDiscrepancies = count($discrepancies);
        $totalUnmatched = count($unmatchedSubscriptions);
        $totalIssues = $totalProblematic + $totalDiscrepancies + $totalUnmatched;

        // Separate over/undercharging
        $overcharging = array_filter($discrepancies, function ($item) {
            return ($item['product_qty'] ?? 0) > ($item['sub_qty'] ?? 0);
        });
        $undercharging = array_filter($discrepancies, function ($item) {
            return ($item['product_qty'] ?? 0) < ($item['sub_qty'] ?? 0);
        });

        // Count exceptions
        $totalExceptions = count($quantityExceptions) + count($clientExceptions);
        $includeExceptions = filter_var($_ENV['MAIL_INCLUDE_EXCEPTIONS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        // Build email
        $divider = str_repeat('=', 70);
        $minorDivider = str_repeat('-', 70);

        $email = $divider . "\n";
        $email .= "O365 SYNC DASHBOARD - DAILY REPORT\n";
        $email .= $divider . "\n\n";

        // SUMMARY SECTION
        $email .= "📊 SUMMARY\n";
        $email .= $minorDivider . "\n";
        $email .= "Total Issues Found: " . $totalIssues . "\n";

        if ($totalIssues > 0) {
            if ($totalProblematic > 0) {
                $email .= "  • Problematic Clients: " . $totalProblematic . " (missing tenant ID/expiry)\n";
            }
            if ($totalDiscrepancies > 0) {
                $email .= "  • Quantity Mismatches: " . $totalDiscrepancies . " (";
                $parts = [];
                if (count($overcharging) > 0)
                    $parts[] = count($overcharging) . " overcharging";
                if (count($undercharging) > 0)
                    $parts[] = count($undercharging) . " undercharging";
                $email .= implode(', ', $parts) . ")\n";
            }
            if ($totalUnmatched > 0) {
                $email .= "  • Unmatched Subscriptions: " . $totalUnmatched . " (no WHMCS product mapped)\n";
            }
        } else {
            $email .= "✅ No issues found - all clients are properly configured!\n";
        }

        if ($totalExceptions > 0) {
            $email .= "\nExceptions Applied: " . $totalExceptions . " (filtered from this report)\n";
        }

        $email .= "\n";

        // Return early if no issues
        if ($totalIssues === 0) {
            $email .= "Report generated: " . date('Y-m-d H:i:s') . "\n";
            return $email;
        }

        // CRITICAL ISSUES SECTION
        $email .= "🔴 CRITICAL ISSUES\n";
        $email .= $divider . "\n\n";

        // 1. PROBLEMATIC CLIENTS
        if ($totalProblematic > 0) {
            $email .= "1. PROBLEMATIC CLIENTS (" . $totalProblematic . ")\n";
            $email .= $minorDivider . "\n";

            foreach ($problematicClients as $client) {
                $company = $client['companyname'] ?? 'Unknown';
                $clientId = $client['id'] ?? $client['client_id'] ?? 'N/A';
                $hasTenant = !empty($client['tenantId']) && $client['tenantId'] != 0;
                $hasExpiry = !empty($client['expiry']) && $client['expiry'] != 0;

                $issues = [];
                if (!$hasTenant)
                    $issues[] = 'Missing Tenant ID';
                if (!$hasExpiry)
                    $issues[] = 'Missing Expiry Date';

                $email .= "  • " . $company . " (ID: " . $clientId . ") - " . implode(', ', $issues) . "\n";
            }
            $email .= "\n";
        }

        // 2. QUANTITY MISMATCHES
        if ($totalDiscrepancies > 0) {
            $email .= "2. QUANTITY MISMATCHES (" . $totalDiscrepancies . ")\n";
            $email .= $minorDivider . "\n";

            if (count($overcharging) > 0) {
                $email .= "⚠️ Overcharging (" . count($overcharging) . "):\n";
                foreach ($overcharging as $item) {
                    $company = $item['companyname'] ?? 'Unknown';
                    $product = $item['product_name'] ?? 'Unknown Product';
                    $whmcs = $item['product_qty'] ?? 0;
                    $dicker = $item['sub_qty'] ?? 0;
                    $diff = $whmcs - $dicker;
                    $email .= "  • " . $company . " - " . $product . "\n";
                    $email .= "    WHMCS: " . $whmcs . ", Dicker: " . $dicker . " (overcharging by " . $diff . ")\n";
                }
                $email .= "\n";
            }

            if (count($undercharging) > 0) {
                $email .= "⚠️ Undercharging (" . count($undercharging) . "):\n";
                foreach ($undercharging as $item) {
                    $company = $item['companyname'] ?? 'Unknown';
                    $product = $item['product_name'] ?? 'Unknown Product';
                    $whmcs = $item['product_qty'] ?? 0;
                    $dicker = $item['sub_qty'] ?? 0;
                    $diff = $dicker - $whmcs;
                    $email .= "  • " . $company . " - " . $product . "\n";
                    $email .= "    WHMCS: " . $whmcs . ", Dicker: " . $dicker . " (undercharging by " . $diff . ")\n";
                }
                $email .= "\n";
            }
        }

        // 3. UNMATCHED SUBSCRIPTIONS
        if ($totalUnmatched > 0) {
            $email .= "3. UNMATCHED SUBSCRIPTIONS (" . $totalUnmatched . ")\n";
            $email .= $minorDivider . "\n";

            foreach ($unmatchedSubscriptions as $item) {
                $company = $item['companyname'] ?? 'Unknown';
                $desc = $item['stock_description'] ?? 'Unknown Product';
                $qty = $item['quantity'] ?? 0;
                $msc = $item['manufacturer_stock_code'] ?? 'N/A';

                $email .= "  • " . $company . " - " . $desc . " (Qty: " . $qty . ")\n";
                $email .= "    MSC: " . $msc . " - No mapping found in WHMCS\n";
            }
            $email .= "\n";
        }

        // EXCEPTIONS SECTION (optional)
        if ($includeExceptions && $totalExceptions > 0) {
            $email .= "ℹ️ EXCEPTIONS APPLIED (" . $totalExceptions . ")\n";
            $email .= $divider . "\n";
            $email .= "These items were filtered out of the report above:\n\n";

            // Client-level exceptions
            foreach ($clientExceptions as $exc) {
                $company = $exc['companyname'] ?? 'Unknown';
                $type = $exc['type'] ?? 'unknown';
                $reason = $exc['reason'] ?? 'No reason provided';
                $typeLabel = $type === 'missing_tenant_id' ? 'Missing Tenant ID' : 'Missing Expiry';

                $email .= "  • " . $company . " - " . $typeLabel . "\n";
                $email .= "    Reason: " . $reason . "\n";
            }

            // Quantity/subscription exceptions
            foreach ($quantityExceptions as $exc) {
                $company = $exc['companyname'] ?? 'Unknown';
                $product = $exc['product_name'] ?? $exc['stock_description'] ?? 'Unknown';
                $whmcs = $exc['product_qty'] ?? $exc['expected_whmcs_qty'] ?? 0;
                $dicker = $exc['sub_qty'] ?? $exc['quantity'] ?? $exc['expected_dicker_qty'] ?? 0;
                $reason = $exc['exception_reason'] ?? $exc['reason'] ?? 'No reason provided';

                $email .= "  • " . $company . " - " . $product . ": " . $whmcs . "/" . $dicker . "\n";
                $email .= "    Reason: " . $reason . "\n";
            }

            $email .= "\n";
        }

        // ACTIONS REQUIRED
        // $email .= "📎 ACTIONS REQUIRED\n";
        // $email .= $divider . "\n";

        // if ($totalProblematic > 0) {
        //     $email .= "1. Review problematic clients - add missing tenant IDs/expiry dates or create exceptions\n";
        // }
        // if ($totalDiscrepancies > 0) {
        //     $email .= "2. Check quantity mismatches for billing accuracy - adjust WHMCS or create exceptions\n";
        // }
        // if ($totalUnmatched > 0) {
        //     $email .= "3. Map unmatched subscriptions in the dashboard mapping section\n";
        // }

        $email .= $minorDivider . "\n";
        $dashboardUrl = $_ENV['DASHBOARD_URL'] ?? 'http://localhost/msdd/';
        $email .= "\nView full dashboard: " . $dashboardUrl . "\n\n";

        $email .= "Report generated: " . date('Y-m-d H:i:s') . "\n";

        return $email;
    }

    public function arrayToTemplate($array, $template, $title = '')
    {
        if (count($array) === 0) {
            return '';
        }
        $output = "\n\n";
        if (!empty($title)) {
            $output .= $title . "\n";
        }
        $output .= "Rows: " . count($array) . "\n\n";

        foreach ($array as $row) {
            $item = $template;
            foreach ($row as $key => $value) {
                $item = str_replace('{' . $key . '}', $value, $item);
            }
            $output .= $item . "\n";
        }
        return $output;
    }

    public function arrayToTable($array)
    {
        $content = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse; width:100%;">
          <thead>';
        foreach ($array as $row) {
            // Build table headers from array keys since we may not know them ahead of time
            $content .= '<tr>';
            foreach (array_keys($row) as $header) {
                $content .= '<th align="left" style="padding:8px 12px; background:#f0f0f0; color:#111; font-weight:600; font-size:13px; border-bottom:1px solid #e6e6e6;">' . htmlspecialchars(str_replace("_", " ", $header)) . '</th>';
            }
            $content .= '</tr></thead><tbody>';
            $content .= '<tr>';
            // Build table rows
            foreach ($row as $cell) {
                $content .= '<td align="left" style="padding:8px 12px; border-bottom:1px solid #e6e6e6;">' . htmlspecialchars($cell) . '</td>';
            }
            $content .= '</tr>';
        }
        $content .= "</tbody></table>";
        return $content;
    }

    public function arrayToPlainTextTable($array)
    {
        $content = "";
        if (count($array) === 0) {
            return $content;
        }

        // Array is [undercharging][... items], [overcharging][... items]
        // Get states, undercharging and overcharging
        $states = ['undercharging', 'overcharging'];

        // Collect headers from available rows (avoid undefined offset)
        $headers = ['State'];
        foreach ($states as $state) {
            foreach ($array[$state] ?? [] as $row) {
                foreach (array_keys($row) as $header) {
                    if (!in_array($header, $headers, true)) {
                        $headers[] = $header;
                    }
                }
            }
        }

        if (count($headers) === 0) {
            return $content;
        }

        // Header display name overrides (internal key => display name)
        $displayMap = [
            'product_qty' => 'WHMCS Qty',
            'sub_qty' => 'Dicker Qty',
            'client_id' => 'WHMCS ID'
        ];

        // Configuration
        $maxRowWidth = 255;
        $colSep = ' | ';
        $colSepForRule = '-+-'; // visual separator for rule line

        // Collect rows in header order, flattening the two states
        $rows = [];
        foreach ($states as $state) {
            if (!isset($array[$state]) || !is_array($array[$state])) {
                continue;
            }
            foreach ($array[$state] as $row) {
                $row['State'] = $state; // Add state information to each row
                $outRow = [];
                foreach ($headers as $h) {
                    $val = $row[$h] ?? '';
                    // collapse newlines to spaces for terminal-like single-line cells
                    $val = preg_replace("/\r\n|\r|\n/u", ' ', (string) $val);
                    $outRow[] = $val;
                }
                $rows[] = $outRow;
            }
        }

        // Compute natural column widths (max of header and cells)
        $colCount = count($headers);
        $natural = array_fill(0, $colCount, 0);
        for ($i = 0; $i < $colCount; $i++) {
            // use display name length for header width
            $displayHeader = $displayMap[$headers[$i]] ?? $headers[$i];
            $displayHeader = str_replace('_', ' ', $displayHeader);
            $natural[$i] = mb_strlen($displayHeader, 'UTF-8');
        }
        foreach ($rows as $r) {
            for ($i = 0; $i < $colCount; $i++) {
                $len = mb_strlen($r[$i] ?? '', 'UTF-8');
                if ($len > $natural[$i]) {
                    $natural[$i] = $len;
                }
            }
        }

        // Determine available width for columns after accounting for separators
        $sepLen = mb_strlen($colSep, '8bit'); // typically 3
        $availableForCols = $maxRowWidth - max(0, $colCount - 1) * $sepLen;
        if ($availableForCols < $colCount) {
            // ensure at least 1 char per column
            $availableForCols = $colCount;
        }

        $sumNatural = array_sum($natural);

        // Decide final column widths (shrink proportionally if needed)
        if ($sumNatural <= $availableForCols) {
            $widths = $natural;
        } else {
            // proportional shrink, floor then distribute remainder
            $widths = [];
            foreach ($natural as $w) {
                $widths[] = max(1, (int) floor($w * $availableForCols / $sumNatural));
            }
            // adjust to match availableForCols exactly
            $currentSum = array_sum($widths);
            $i = 0;
            // if we have leftover space, add to columns left-to-right
            while ($currentSum < $availableForCols) {
                $widths[$i % $colCount]++;
                $currentSum++;
                $i++;
            }
            // if we are over, reduce from widest columns
            while ($currentSum > $availableForCols) {
                // find index of widest column with width > 1
                $maxIndex = null;
                $maxVal = 0;
                for ($j = 0; $j < $colCount; $j++) {
                    if ($widths[$j] > $maxVal && $widths[$j] > 1) {
                        $maxVal = $widths[$j];
                        $maxIndex = $j;
                    }
                }
                if ($maxIndex === null) {
                    break; // cannot reduce further
                }
                $widths[$maxIndex]--;
                $currentSum--;
            }
        }

        // Helper to truncate and pad a cell to target width (UTF-8 aware)
        $formatCell = function ($text, $width) {
            $text = (string) $text;
            $len = mb_strlen($text, 'UTF-8');
            if ($len > $width) {
                if ($width > 3) {
                    $text = mb_substr($text, 0, $width - 3, 'UTF-8') . '...';
                } else {
                    $text = mb_substr($text, 0, $width, 'UTF-8');
                }
                $len = mb_strlen($text, 'UTF-8');
            }
            return $text . str_repeat(' ', $width - $len);
        };

        // Build header line (use displayMap to rename certain headers)
        $headerCells = [];
        for ($i = 0; $i < $colCount; $i++) {
            $display = $displayMap[$headers[$i]] ?? $headers[$i];
            $display = str_replace('_', ' ', $display);
            $headerCells[] = $formatCell($display, $widths[$i]);
        }
        $content .= implode($colSep, $headerCells) . "\n";

        // Build separator rule line (dashes) using a visual separator
        $ruleParts = [];
        for ($i = 0; $i < $colCount; $i++) {
            $ruleParts[] = str_repeat('-', $widths[$i]);
        }
        $content .= implode($colSepForRule, $ruleParts) . "\n";

        // Build rows
        foreach ($rows as $r) {
            $cells = [];
            for ($i = 0; $i < $colCount; $i++) {
                $cells[] = $formatCell($r[$i] ?? '', $widths[$i]);
            }
            $content .= implode($colSep, $cells) . "\n";
        }

        return $content;
    }
}