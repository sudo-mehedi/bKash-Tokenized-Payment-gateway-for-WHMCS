<?php
class ProthomPayClient {
    private $credentials;
    private $baseUrl;
    private $accessToken;
    private $logger;
    private $lastAuth;
    private $authRetries = 0;
    private const MAX_AUTH_RETRIES = 3;
    private const TOKEN_EXPIRY = 3500; // 58 minutes
    private const RETRY_DELAY = 2; // seconds

    public function __construct($username, $password, $appKey, $appSecret, $baseUrl, $logger) {
        $this->credentials = [
            'username' => $this->sanitizeInput($username),
            'password' => $this->sanitizeInput($password),
            'app_key' => trim($appKey),
            'app_secret' => trim($appSecret)
        ];
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->logger = $logger;
        $this->lastAuth = 0;
    }

    private function sanitizeInput($value) {
        $value = trim($value);
        // Handle special characters and encoding
        if (preg_match('/%[0-9A-Fa-f]{2}/', $value)) {
            $value = urldecode($value);
        }
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function createPayment($invoiceId, $amount, $callbackUrl) {
        return $this->makeAuthenticatedRequest('/checkout/create', [
            'mode' => '0011',
            'payerReference' => 'INV' . $invoiceId,
            'callbackURL' => $callbackUrl,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceId
        ]);
    }

public function queryPayment($paymentID) {
        $response = $this->makeAuthenticatedRequest('/checkout/payment/status', [
            'paymentID' => $paymentID
        ], true);

        // Enhanced response validation
        if (!isset($response['transactionStatus'])) {
            throw new Exception('Invalid response - missing transaction status');
        }

        // Require trxID for completed payments - no fallback generation
        if ($response['transactionStatus'] === 'Completed' && empty($response['trxID'])) {
            throw new Exception('Completed payment missing transaction ID - cannot process');
        }
        
        // For non-completed payments, trxID might legitimately be empty
        if (empty($response['trxID']) && $response['transactionStatus'] !== 'Completed') {
            $this->logger->log('No trxID for non-completed payment', [
                'paymentID' => $paymentID,
                'status' => $response['transactionStatus']
            ]);
        }

        return $response;
    }

    private function makeAuthenticatedRequest($endpoint, $data, $isCritical = false) {
        $attempt = 0;
        $maxAttempts = $isCritical ? 2 : 1;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                if ($this->needsFreshAuth()) {
                    $this->authenticate();
                }

                return $this->makeApiCall($endpoint, $data);
            } catch (Exception $e) {
                $lastError = $e;
                $this->logger->log(sprintf(
                    'Attempt %d/%d failed: %s',
                    $attempt,
                    $maxAttempts,
                    $e->getMessage()
                ));

                // Force reauthentication on 401 errors
                if (strpos($e->getMessage(), '401') !== false) {
                    $this->accessToken = null;
                }

                if ($attempt < $maxAttempts) {
                    sleep(self::RETRY_DELAY);
                }
            }
        }

        throw new Exception(sprintf(
            'Failed after %d attempts. Last error: %s',
            $maxAttempts,
            $lastError ? $lastError->getMessage() : 'Unknown error'
        ));
    }

    private function needsFreshAuth() {
        return empty($this->accessToken) || 
              (time() - $this->lastAuth) > self::TOKEN_EXPIRY;
    }

    public function authenticate() {
        if ($this->authRetries >= self::MAX_AUTH_RETRIES) {
            throw new Exception('Maximum authentication attempts reached');
        }

        $this->authRetries++;
        $this->logger->log('Authentication attempt ' . $this->authRetries, [
            'username' => substr($this->credentials['username'], 0, 3) . '...'
        ]);

        try {
            $response = $this->makeApiCall('/checkout/token/grant', [
                'app_key' => $this->credentials['app_key'],
                'app_secret' => $this->credentials['app_secret']
            ], [
                'username: ' . $this->credentials['username'],
                'password: ' . $this->credentials['password']
            ]);

            if (empty($response['id_token'])) {
                throw new Exception('No access token received');
            }

            $this->accessToken = $response['id_token'];
            $this->lastAuth = time();
            $this->authRetries = 0;
            
            $this->logger->log('Authentication successful', [
                'expires_at' => date('Y-m-d H:i:s', time() + 3600)
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->log('Authentication failed', [
                'error' => $e->getMessage(),
                'attempt' => $this->authRetries
            ]);

            if ($this->authRetries < self::MAX_AUTH_RETRIES) {
                sleep(self::RETRY_DELAY);
                return $this->authenticate();
            }
            
            throw new Exception('Could not authenticate with bKash. Please verify your credentials.');
        }
    }

    private function makeApiCall($endpoint, $data, $customHeaders = []) {
        $url = $this->baseUrl . $endpoint;
        $headers = array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($this->accessToken ?? ''),
            'x-app-key: ' . $this->credentials['app_key']
        ], $customHeaders);

        $this->logger->log('API Request', [
            'endpoint' => $endpoint,
            'data' => $endpoint === '/checkout/token/grant' ? '[protected]' : $data
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Network error: " . $error);
        }

        $result = json_decode($response, true) ?: [];
        
        $this->logger->log('API Response', [
            'http_code' => $httpCode,
            'response' => $endpoint === '/checkout/token/grant' ? '[protected]' : $result
        ]);

        if ($httpCode === 401) {
            throw new Exception("Authentication required (HTTP 401)");
        }

        if ($httpCode !== 200) {
            throw new Exception("API returned HTTP $httpCode");
        }

        if (isset($result['statusCode']) && $result['statusCode'] !== '0000') {
            throw new Exception($result['statusMessage'] ?? 'Payment processing failed');
        }

        return $result;
    }
    
}
?>