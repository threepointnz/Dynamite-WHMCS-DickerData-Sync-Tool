<?php
class DickerAPI
{
    private $url;
    private $accessToken;

    public function __construct($url, $access_token)
    {
        $this->url = $url;
        $this->accessToken = $access_token;
    }

    public function initialize(bool $force = false, bool $captureOutput = false)
    {
        if (!$force && empty($_ENV['DICKER_process'])) {
            return true; // nothing to do
        }
        if ($captureOutput)
            ob_start();
        $token = $this->getBearerToken();
        $debug = $captureOutput ? ob_get_contents() : null;
        if ($captureOutput)
            ob_clean();
        if (!$token) {
            // return false or throw new Exception('Failed to get bearer token');
            return ['ok' => false, 'debug' => $debug];
        }
        return ['ok' => true, 'debug' => $debug, 'token' => $token];
    }

    /**
     * Generate a unique transaction ID for Dicker Data API requests
     */
    private function generateTransactionId()
    {
        // Generate UUID format: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    public function getBearerToken()
    {
        if (isset($_SESSION['bearerToken']) && !empty($_SESSION['bearerToken'])) {
            return $_SESSION['bearerToken'];
        }
        // perform a curl request to retrieve or validate the access key if needed
        $url = $this->url . "AccessKeyRequest";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
            [
                'AccessToken' => $this->accessToken,
                'TransactionID' => $this->generateTransactionId()
            ]
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($curlError) {
            return false;
        }

        // Check for HTTP errors
        if ($httpCode !== 200) {
            return false;
        }

        $decodedResponse = json_decode($response, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($decodedResponse['AccessKey'])) {
            $_SESSION['bearerToken'] = $decodedResponse['AccessKey'];
        } else {
            return false;
        }

        if ($decodedResponse['Status'] === 'GRANTED') {
            $_SESSION['bearerToken'] = $decodedResponse['AccessKey'];
        }

        return $_SESSION['bearerToken'];
    }
    public function apiRequest($endpoint, $postData = [], $method = 'POST')
    {
        // Ensure we have an access key first
        if (empty($_SESSION['bearerToken'])) {
            $bearerToken = $this->getBearerToken();
            if (!$bearerToken) {
                return ['result' => 'error', 'message' => 'Failed to obtain bearer token'];
            }
        }        // Correct base URL based on the documentation
        $url = $this->url . $endpoint;

        // Generate unique transaction ID for this request
        $transactionId = $this->generateTransactionId();

        $headers = [
            'Authorization: Bearer ' . $_SESSION['bearerToken'], // Use access key, not token
            'Content-Type: application/json',
            'Accept: application/json',
            'DD-TransactionID: ' . $transactionId,
            'DD-ApiVersion: 1.0'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set HTTP method and data based on request type
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else {
            // GET request - append data as query parameters if provided
            if (!empty($postData)) {
                $url .= '?' . http_build_query($postData);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($curlError) {
            return ['result' => 'error', 'message' => 'cURL Error: ' . $curlError];
        }

        // Check for HTTP errors
        if ($httpCode !== 200) {
            return ['result' => 'error', 'message' => 'HTTP Error: ' . $httpCode, 'response' => $response];
        }

        // Check if response is empty
        if (empty($response)) {
            return ['result' => 'error', 'message' => 'Empty response from API'];
        }

        $decodedResponse = json_decode($response, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['result' => 'error', 'message' => 'JSON Decode Error: ' . json_last_error_msg()];
        }

        return $decodedResponse;
    }
    /**
     * Retrieve a list of active tenants (contracts) from Dicker Data.
     * The API accepts an optional TenantID and/or SubscriptionType as request parameters.
     * - If $tenantId is provided the API will return that specific tenant.
     * - If $subscriptionType is provided the API will return tenants for that subscription type.
     * - If neither is provided the API will return all tenants (subject to the service default).
     *
     * @param string|null $tenantId Optional TenantID to filter results
     * @param string|null $subscriptionType Optional subscription type ('CSP', 'ADY', 'ADM')
     * @return array API response
     */
    public function getTenant($tenantId = null, $subscriptionType = null)
    {

        $data = [
            'In' => [
                'TenantID' => $tenantId ?: $this->accessToken,
                'SubscriptionType' => $subscriptionType ?: 'CSP',
            ],
        ];
        return $this->apiRequest('Subscriptions/GetTenant', $data, 'POST');
    }
    /**
     * Get all CSP tenants (Microsoft Cloud Solution Provider)
     * 
     * @return array API response
     */
    public function getCSPTenants()
    {
        return $this->getTenant(null, 'CSP');
    }

    /**
     * Get specific CSP tenant by TenantID
     * 
     * @param string $tenantId The TenantID to retrieve
     * @return array API response
     */
    public function getCSPTenant($tenantId)
    {
        $data = [
            'In' => [
                'TenantID' => $tenantId,
                'SubscriptionType' => 'CSP'
            ]
        ];
        return $this->apiRequest('Subscriptions/GetTenant', $data, 'POST');
    }

    /**
     * Get all tenants by subscription type (without specifying TenantID)
     * 
     * @return array API response
     */
    public function getAllTenants($subscriptionType = 'CSP')
    {
        $data = [
            'In' => [
                'SubscriptionType' => $subscriptionType
            ]
        ];
        return $this->apiRequest('Subscriptions/GetTenant', $data, 'POST');
    }

    /**
     * Get subscription details with optional filters.
     *
     * Accepts any combination of the following optional filters:
     * - StockCode (string) : Product code (response filtered if provided)
     * - SubscriptionReference (string) : Unique customer's subscription reference
     * - TenantID (string) : Existing tenant (contract) ID
     * - SubscriptionId (string) : Subscription ID
     * - SubscriptionType (string) : Subscription program type (CSP, ADY, ADM)
     *
     * @param string|null $stockCode
     * @param string|null $subscriptionReference
     * @param string|null $tenantId
     * @param string|null $subscriptionId
     * @param string $subscriptionType
     * @return array API response
     */
    public function getSubscriptionDetails($stockCode = null, $subscriptionReference = null, $tenantId = null, $subscriptionId = null, $subscriptionType = 'CSP', $organise = true)
    {
        $in = [];

        if ($stockCode !== null && $stockCode !== '') {
            $in['StockCode'] = $stockCode;
        }

        if ($subscriptionReference !== null && $subscriptionReference !== '') {
            $in['SubscriptionReference'] = $subscriptionReference;
        }

        if ($tenantId !== null && $tenantId !== '') {
            $in['TenantID'] = $tenantId;
        }

        if ($subscriptionId !== null && $subscriptionId !== '') {
            $in['SubscriptionId'] = $subscriptionId;
        }

        // Always include SubscriptionType (use provided or default)
        if ($subscriptionType !== null && $subscriptionType !== '') {
            $in['SubscriptionType'] = $subscriptionType;
        }

        $data = [
            'In' => $in
        ];
        $result = $this->apiRequest('Subscriptions/GetSubscriptionDetails', $data, 'POST');
        if (!$organise) {
            return $result;
        }
        return $this->organiseSubscriptionsByTenant($result);
    }


    public function GetSubscriptionRenewalDetail($tenantID, $subscriptionId = null, $orderId = null)
    {
        $data = [
            'In' => [
                'TenantID' => $tenantID
            ]
        ];

        if ($subscriptionId !== null && $subscriptionId !== '') {
            $data['In']['SubscriptionId'] = $subscriptionId;
        }

        if ($orderId !== null && $orderId !== '') {
            $data['In']['OrderId'] = $orderId;
        }

        return $this->apiRequest('Microsoft/GetSubscriptionRenewalDetail', $data, 'POST');
    }

    public function organiseSubscriptionsByTenant($subscriptionDetails)
    {
        $organisedSubscriptions = [];
        if (is_array($subscriptionDetails) || is_object($subscriptionDetails)) {
            foreach ($subscriptionDetails["Out"] as $subscription) {
                // group by TenantId, under the TenantId we want SubscriptionReference and ConfirmedQuantity
                $tenantId = $subscription['TenantId'];
                if (!isset($organisedSubscriptions[$tenantId])) {
                    $organisedSubscriptions[$tenantId] = [];
                }
                if ($subscription['Status'] !== 'ACTIVE') {
                    continue; // skip non-active subscriptions
                }
                $organisedSubscriptions[$tenantId][] = [
                    'Trial' => strpos(strtolower($subscription['StockDescription']), 'trial') !== false ? true : false,
                    "AccountCode" => $subscription['AccountCode'],
                    "TenantId" => $subscription['TenantId'],
                    "SubscriptionReference" => $subscription['SubscriptionReference'],
                    "CompanyName" => $subscription['CompanyName'],
                    "TenantDescription" => $subscription['TenantDescription'],
                    "StockCode" => $subscription['StockCode'],
                    "ParentSubscriptionId" => $subscription['ParentSubscriptionId'],
                    "SubscriptionId" => $subscription['SubscriptionId'],
                    "Status" => $subscription['Status'],
                    "BillingFrequency" => $subscription['BillingFrequency'],
                    "LastBillingDate" => $subscription['LastBillingDate'],
                    "NextBillingDate" => $subscription['NextBillingDate'],
                    "ConfirmedQuantity" => $subscription['ConfirmedQuantity'],
                    "Serials" => $subscription['Serials'],
                    "ExpiryDate" => $subscription['ExpiryDate'],
                    "StockDescription" => $subscription['StockDescription'],
                    "ManufacturerStockCode" => $subscription['ManufacturerStockCode']
                ];
            }
        } else {
            echo htmlspecialchars((string) $subscriptionDetails);
        }
        return $organisedSubscriptions;
    }

    /**
     * getDistinctPackages
     * @return array
     */
    public function getDistinctPackages()
    {
        $allSubscriptions = $this->getSubscriptionDetails(null, null, null, null, 'CSP', false);
        $packages = [];
        foreach ($allSubscriptions["Out"] as $subscription) {
            if (!empty($subscription['ManufacturerStockCode'])) {
                $safeMSC = preg_replace('/[^a-zA-Z0-9-_]/', '-', $subscription['ManufacturerStockCode']);
                if (!isset($packages[$safeMSC]) && $subscription['Status'] === 'ACTIVE') {
                    $packages[$safeMSC] = [
                        'SubscriptionReference' => $subscription['SubscriptionReference'],
                        'StockDescription' => $subscription['StockDescription'],
                        'ManufacturerStockCode' => $subscription['ManufacturerStockCode']
                    ];
                }
            }
        }
        return array_values($packages);
    }

}
