<?php
/**
 * Class to intereact with mail() and send HTML email notifications
 * Must work with php 7.4
 */
class Mail
{

    public function send($to, $subject, $message)
    {
        // Use the mail() function to send the email
        $result = mail($to, $subject, $message);
        // return and object ready to respond to an api caller
        return (object) [
            'success' => (bool) $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email'
        ];
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