<?php
class WHMCSAPI
{
    private $url;
    private $identifier;
    private $secret;
    private $accessKey;

    public function __construct($url, $identifier, $secret, $accessKey)
    {
        $this->url = $url;
        $this->identifier = $identifier;
        $this->secret = $secret;
        $this->accessKey = $accessKey;
    }
    public function apiRequest($action, $postData = [])
    {
        $url = $this->url;

        $postData = array_merge($postData, [
            'action' => $action,
            'identifier' => $this->identifier,
            'secret' => $this->secret,
            'accesskey' => $this->accessKey,
            'responsetype' => 'json',
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);


        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($curlError) {
            $this->error("cURL Error: " . $curlError);
            return ['result' => 'error', 'message' => 'cURL Error: ' . $curlError];
        }

        // Check for HTTP errors
        if ($httpCode !== 200) {
            $this->error("HTTP Error: " . $httpCode);
            return ['result' => 'error', 'message' => 'HTTP Error: ' . $httpCode];
        }

        // Check if response is empty
        if (empty($response)) {
            $this->error("Empty response from API");
            return ['result' => 'error', 'message' => 'Empty response from API'];
        }

        $decodedResponse = json_decode($response, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON Decode Error: " . json_last_error_msg());
            $this->error("Raw Response: " . $response);
            return ['result' => 'error', 'message' => 'JSON Decode Error: ' . json_last_error_msg()];
        }

        return $decodedResponse;
    }

    public function error($data)
    {
        echo '<p>' . $data . '</p>';
    }

    public function getActiveClients()
    {
        return $this->apiRequest('GetClients', ['status' => 'Active', 'limitnum' => 1000]);
    }
    public function getClientDetails($clientId)
    {
        return $this->apiRequest('GetClientsDetails', ['clientid' => $clientId]);
    }

    public function getClientProducts()
    {
        $activeClients = $this->getActiveClients();
        // foreach ($activeClients['clients']['client'] as $client) {
        //     $clientId = $client['id'];
        //     $details = $this->getClientDetails($clientId);
        //     // updated the $client with $details as needed
        //     $client = array_merge($client, $details['client']);
        // }
        return $activeClients;
    }
}
