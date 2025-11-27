<?php
/**
 * API Request Handlers
 * Handles POST requests for mapping and exceptions
 */

// Handle mapping save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mapping') {
    header('Content-Type: application/json');
    $raw = $_POST['mapping_json'] ?? '';
    if ($raw === '') {
        echo json_encode(['ok' => false, 'message' => 'Empty payload']);
        exit;
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['ok' => false, 'message' => 'JSON error: ' . json_last_error_msg()]);
        exit;
    }

    $mappingPath = __DIR__ . '/../mapping.json';
    if (file_exists($mappingPath)) {
        @copy($mappingPath, $mappingPath . '.bak.' . time());
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $written = @file_put_contents($mappingPath, $pretty);
    if ($written === false) {
        echo json_encode(['ok' => false, 'message' => 'Failed to write mapping.json']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

// Handle exception management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_exception') {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $exceptions = [];

        if (file_exists($exceptionsPath)) {
            $content = file_get_contents($exceptionsPath);
            $exceptions = json_decode($content, true) ?: [];
        }
        $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int) $_POST['client_id'] : 0;
        $productId = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int) $_POST['product_id'] : null;
        $manufacturerStockCode = trim($_POST['manufacturer_stock_code'] ?? '');
        $expectedWhmcsQty = (int) ($_POST['expected_whmcs_qty'] ?? 0);
        $expectedDickerQty = (int) ($_POST['expected_dicker_qty'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $applyTo = trim($_POST['apply_to'] ?? 'client');
        $subscriptionId = trim($_POST['subscription_id'] ?? '');

        // Debug: log what we received
        error_log('Exception POST data - product_id raw: ' . var_export($_POST['product_id'] ?? 'NOT_SET', true));
        error_log('Exception POST data - product_id parsed: ' . var_export($productId, true));

        $newException = [
            'client_id' => $clientId,
            'product_id' => $productId,
            'manufacturer_stock_code' => $manufacturerStockCode,
            'expected_whmcs_qty' => $expectedWhmcsQty,
            'expected_dicker_qty' => $expectedDickerQty,
            'reason' => $reason,
            'apply_to' => $applyTo,
            'subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['adminid'] ?? 'system'
        ];

        // Check if exception already exists
        $exists = false;
        foreach ($exceptions as $exc) {
            $excClient = $exc['client_id'] ?? 0;
            $excMsc = strtolower($exc['manufacturer_stock_code'] ?? '');
            $excApplyTo = $exc['apply_to'] ?? 'client';
            $excSubId = $exc['subscription_id'] ?? null;

            if (strtolower($manufacturerStockCode) === $excMsc) {
                if ($applyTo === 'unmatched' || $excApplyTo === 'unmatched') {
                    if (!empty($subscriptionId) && !empty($excSubId)) {
                        if ($subscriptionId === $excSubId) {
                            $exists = true;
                            break;
                        }
                    } else {
                        if ($clientId === (int) $excClient) {
                            $exists = true;
                            break;
                        }
                        if ($excClient === 0 || $clientId === 0) {
                            $exists = true;
                            break;
                        }
                    }
                } else {
                    if ($clientId === (int) $excClient) {
                        $exists = true;
                        break;
                    }
                }
            }
        }

        if (!$exists) {
            $exceptions[] = $newException;
            file_put_contents($exceptionsPath, json_encode($exceptions, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Exception already exists']);
        }
        exit;
    }

    if ($_POST['action'] === 'remove_exception') {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $exceptions = [];

        if (file_exists($exceptionsPath)) {
            $content = file_get_contents($exceptionsPath);
            $exceptions = json_decode($content, true) ?: [];
        }

        $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int) $_POST['client_id'] : null;
        $msc = strtolower(trim($_POST['manufacturer_stock_code'] ?? ''));
        $subscriptionId = trim($_POST['subscription_id'] ?? '');

        $exceptions = array_filter($exceptions, function ($exc) use ($clientId, $msc, $subscriptionId) {
            $excClient = $exc['client_id'] ?? 0;
            $excMsc = strtolower($exc['manufacturer_stock_code'] ?? '');
            $excSubId = $exc['subscription_id'] ?? null;

            if ($subscriptionId !== '') {
                if ($excSubId === $subscriptionId && $excMsc === $msc) {
                    return false;
                }
                return true;
            }

            if ($clientId !== null) {
                if ($excClient === $clientId && $excMsc === $msc) {
                    return false;
                }
                return true;
            }

            if ($excMsc === $msc) {
                return false;
            }

            return true;
        });

        $exceptions = array_values($exceptions);
        file_put_contents($exceptionsPath, json_encode($exceptions, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle client-level exceptions (missing tenant ID / expiry)
    if ($_POST['action'] === 'add_client_exception') {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $exceptions = [];

        if (file_exists($exceptionsPath)) {
            $content = file_get_contents($exceptionsPath);
            $exceptions = json_decode($content, true) ?: [];
        }

        $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int) $_POST['client_id'] : 0;
        $exceptionType = trim($_POST['exception_type'] ?? ''); // 'missing_tenant_id' or 'missing_expiry'
        $reason = trim($_POST['reason'] ?? '');

        if ($clientId === 0 || !in_array($exceptionType, ['missing_tenant_id', 'missing_expiry'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        // Check if exception already exists
        $exists = false;
        foreach ($exceptions as $exc) {
            $excType = $exc['type'] ?? 'quantity_mismatch';
            $excClient = (int) ($exc['client_id'] ?? 0);
            
            if ($excType === $exceptionType && $excClient === $clientId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newException = [
                'type' => $exceptionType,
                'client_id' => $clientId,
                'product_id' => null,
                'manufacturer_stock_code' => null,
                'expected_whmcs_qty' => null,
                'expected_dicker_qty' => null,
                'reason' => $reason,
                'apply_to' => 'client',
                'subscription_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['adminid'] ?? 'system'
            ];

            $exceptions[] = $newException;
            file_put_contents($exceptionsPath, json_encode($exceptions, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Exception already exists']);
        }
        exit;
    }

    if ($_POST['action'] === 'remove_client_exception') {
        $exceptionsPath = __DIR__ . '/../exceptions.json';
        $exceptions = [];

        if (file_exists($exceptionsPath)) {
            $content = file_get_contents($exceptionsPath);
            $exceptions = json_decode($content, true) ?: [];
        }

        $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int) $_POST['client_id'] : 0;
        $exceptionType = trim($_POST['exception_type'] ?? '');

        $exceptions = array_filter($exceptions, function ($exc) use ($clientId, $exceptionType) {
            $excType = $exc['type'] ?? 'quantity_mismatch';
            $excClient = (int) ($exc['client_id'] ?? 0);
            
            // Remove if both type and client_id match
            if ($excType === $exceptionType && $excClient === $clientId) {
                return false;
            }
            return true;
        });

        $exceptions = array_values($exceptions);
        file_put_contents($exceptionsPath, json_encode($exceptions, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }
}
