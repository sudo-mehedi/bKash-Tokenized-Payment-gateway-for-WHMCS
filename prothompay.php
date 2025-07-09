<?php
if (!defined("WHMCS")) die("This file cannot be accessed directly");

require_once __DIR__ . '/lib/ProthomPayLogger.php';

function prothompay_MetaData() {
    return [
        'DisplayName' => 'Prothom Pay - bKash Gateway',
        'APIVersion' => '1.1',
    ];
}

function prothompay_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Prothom Pay (bKash)',
        ],
        'apiUsername' => [
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'From bKash merchant dashboard',
        ],
        'apiPassword' => [
            'FriendlyName' => 'API Password',
            'Type' => 'password',
            'Size' => '50',
        ],
        'appKey' => [
            'FriendlyName' => 'App Key',
            'Type' => 'text',
            'Size' => '50',
        ],
        'appSecret' => [
            'FriendlyName' => 'App Secret',
            'Type' => 'password',
            'Size' => '50',
        ],
        'isSandbox' => [
            'FriendlyName' => 'Sandbox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable for testing',
        ],
        'enableLogging' => [
            'FriendlyName' => 'Enable Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Log all transactions',
        ],
    ];
}

function prothompay_link($params) {
    $logger = new ProthomPayLogger($params['enableLogging'] == 'on');
    
    // Validate credentials
    if (empty($params['apiUsername']) || empty($params['apiPassword']) || 
        empty($params['appKey']) || empty($params['appSecret'])) {
        return '<div class="alert alert-danger">Prothom Pay is not properly configured</div>';
    }

    $baseUrl = $params['isSandbox'] == 'on' 
        ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized' 
        : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized';

    $callbackUrl = rtrim($params['systemurl'], '/') . '/modules/gateways/callback/prothompay.php';

    try {
        require_once __DIR__ . '/lib/ProthomPayClient.php';
        $prothomPay = new ProthomPayClient(
            trim($params['apiUsername']),
            trim($params['apiPassword']),
            trim($params['appKey']),
            trim($params['appSecret']),
            $baseUrl,
            $logger
        );

        // Test authentication first
        if (!$prothomPay->authenticate()) {
            throw new Exception("Could not authenticate with bKash");
        }

        $paymentData = $prothomPay->createPayment(
            $params['invoiceid'],
            $params['amount'],
            $callbackUrl
        );

        if ($paymentData['statusCode'] === '0000') {
            return '<a href="' . $paymentData['bkashURL'] . '" class="btn btn-success">Pay with bKash</a>';
        } else {
            throw new Exception($paymentData['statusMessage'] ?? 'Payment initiation failed');
        }
    } catch (Exception $e) {
        $logger->log('Payment Error: ' . $e->getMessage());
        return '<div class="alert alert-danger">Payment error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>