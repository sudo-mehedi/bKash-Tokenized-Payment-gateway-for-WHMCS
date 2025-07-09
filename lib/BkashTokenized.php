<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

class bKashCheckout
{
    private static $instance;
    protected $gatewayModuleName;
    protected $gatewayParams;
    public $isSandbox;
    public $isActive;
    protected $customerCurrency;
    protected $gatewayCurrency;
    protected $clientCurrency;
    protected $convoRate;
    protected $invoice;
    protected $due;
    protected $fee;
    public $total;
    protected $baseUrl;
    public $request;
    private $credential;

    public function __construct()
    {
        $this->setRequest();
        $this->setGateway();
        $this->setInvoice();
    }

    public static function init()
    {
        if (self::$instance == null) {
            self::$instance = new bKashCheckout;
        }
        return self::$instance;
    }

    private function setGateway()
    {
        $this->gatewayModuleName = basename(__FILE__, '.php');
        $this->gatewayParams = getGatewayVariables($this->gatewayModuleName);
        $this->isSandbox = !empty($this->gatewayParams['sandbox']);
        $this->isActive = !empty($this->gatewayParams['type']);

        $this->credential = [
            'username' => $this->gatewayParams['username'],
            'password' => $this->gatewayParams['password'],
            'appKey' => $this->gatewayParams['appKey'],
            'appSecret' => $this->gatewayParams['appSecret'],
        ];

        $this->baseUrl = $this->isSandbox ? 
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/' : 
            'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/';
    }

    private function setRequest()
    {
        $this->request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    }

    private function setInvoice()
    {
        $invoiceId = $this->request->get('id');
        if (empty($invoiceId)) {
            throw new \Exception('Invoice ID is required');
        }

        $this->invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);

        if (isset($this->invoice['result']) && $this->invoice['result'] === 'error') {
            throw new \Exception('Invalid invoice ID or not found');
        }

        $this->setCurrency();
        $this->setDue();
        $this->setFee();
        $this->setTotal();
    }

    private function setCurrency()
    {
        $this->gatewayCurrency = (int) $this->gatewayParams['convertto'];
        $this->customerCurrency = (int) \WHMCS\Database\Capsule::table('tblclients')
            ->where('id', '=', $this->invoice['userid'])
            ->value('currency');

        $this->convoRate = 1;
        if (!empty($this->gatewayCurrency) {
            $this->convoRate = \WHMCS\Database\Capsule::table('tblcurrencies')
                ->where('id', '=', $this->gatewayCurrency)
                ->value('rate');
        }
    }

    private function setDue()
    {
        $this->due = $this->invoice['balance'];
    }

    private function setFee()
    {
        $this->fee = empty($this->gatewayParams['fee']) ? 0 : (($this->gatewayParams['fee'] / 100) * $this->due);
    }

    private function setTotal()
    {
        $this->total = ceil(($this->due + $this->fee) * $this->convoRate);
    }

    private function getToken()
    {
        $url = $this->baseUrl . 'token/grant';
        $fields = json_encode([
            'app_key' => $this->credential['appKey'],
            'app_secret' => $this->credential['appSecret']
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'username: ' . $this->credential['username'],
            'password: ' . $this->credential['password'],
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }
        
        curl_close($ch);
        
        $token = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from bKash');
        }
        
        if (isset($token['msg']) && $token['msg'] === 'Invalid username and password combination') {
            throw new \Exception('bKash authentication failed: Invalid credentials');
        }
        
        if (!isset($token['id_token'])) {
            $errorMsg = $token['statusMessage'] ?? $token['message'] ?? print_r($token, true);
            throw new \Exception('Failed to get token from bKash: ' . $errorMsg);
        }
        
        return $token['id_token'];
    }

    public function createPayment()
    {
        $systemUrl = \WHMCS\Config\Setting::getValue('SystemURL');
        $callbackURL = $systemUrl . '/modules/gateways/callback/' . $this->gatewayModuleName . '.php?id=' . $this->invoice['invoiceid'] . '&action=verify';
        
        $fields = [
            'mode' => '0011',
            'amount' => $this->total,
            'currency' => 'BDT',
            'intent' => 'sale',
            'payerReference' => $this->invoice['invoiceid'],
            'callbackURL' => $callbackURL,
            'merchantInvoiceNumber' => $this->invoice['invoiceid'] . '-' . time(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . 'create');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->getToken(),
            'X-APP-KEY: ' . $this->credential['appKey'],
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Payment creation failed: ' . $error);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new \Exception('Invalid response from bKash API');
        }
        
        return $data;
    }

    private function executePayment()
    {
        $paymentId = $this->request->get('paymentID');
        if (empty($paymentId)) {
            throw new \Exception('Payment ID is required');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . 'execute');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['paymentID' => $paymentId]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->getToken(),
            'X-APP-KEY: ' . $this->credential['appKey'],
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Payment execution failed: ' . $error);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new \Exception('Invalid response from bKash API');
        }
        
        return $data;
    }

    private function queryPayment()
    {
        $paymentId = $this->request->get('paymentID');
        if (empty($paymentId)) {
            throw new \Exception('Payment ID is required');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . 'payment/status?paymentID=' . urlencode($paymentId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->getToken(),
            'X-APP-KEY: ' . $this->credential['appKey'],
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Payment query failed: ' . $error);
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new \Exception('Invalid response from bKash API');
        }
        
        return $data;
    }

    private function checkTransaction($trxId)
    {
        $result = localAPI('GetTransactions', ['transid' => $trxId]);
        
        if ($result['result'] === 'success' && $result['totalresults'] > 0) {
            foreach ($result['transactions']['transaction'] as $transaction) {
                if ($transaction['invoiceid'] == $this->invoice['invoiceid']) {
                    return $transaction;
                }
            }
        }
        
        return false;
    }

    private function logTransaction($payload)
    {
        return logTransaction(
            $this->gatewayParams['name'],
            [
                $this->gatewayModuleName => $payload,
                'request_data' => $this->request->request->all(),
            ],
            $payload['transactionStatus'] ?? 'Failed'
        );
    }

    private function addTransaction($trxId)
    {
        $fields = [
            'invoiceid' => $this->invoice['invoiceid'],
            'transid' => $trxId,
            'gateway' => $this->gatewayModuleName,
            'date' => \Carbon\Carbon::now()->toDateTimeString(),
            'amount' => $this->due,
            'fees' => $this->fee,
        ];
        return localAPI('AddInvoicePayment', $fields);
    }

    public function makeTransaction()
    {
        $executePayment = $this->executePayment();

        if (!isset($executePayment['transactionStatus']) {
            $executePayment = $this->queryPayment();
        }

        if (isset($executePayment['transactionStatus']) && $executePayment['transactionStatus'] === 'Completed') {
            if ($this->checkTransaction($executePayment['trxID'])) {
                throw new \Exception('Transaction already exists for this invoice');
            }

            if ($executePayment['amount'] < $this->total) {
                throw new \Exception('Paid amount is less than required');
            }

            $this->logTransaction($executePayment);
            $trxAddResult = $this->addTransaction($executePayment['trxID']);

            if ($trxAddResult['result'] !== 'success') {
                throw new \Exception('Failed to record payment: ' . ($trxAddResult['message'] ?? 'Unknown error'));
            }

            return ['status' => 'success'];
        }

        throw new \Exception('Payment not completed: ' . ($executePayment['statusMessage'] ?? 'Unknown error'));
    }
}

if (!(new \WHMCS\ClientArea)->isLoggedIn()) {
    die("You must be logged in to proceed with payment.");
}

try {
    $bKashCheckout = bKashCheckout::init();
    if (!$bKashCheckout->isActive) {
        die("The payment gateway is currently unavailable.");
    }

    $action = $bKashCheckout->request->get('action');
    $invoiceId = $bKashCheckout->request->get('id');
    $status = $bKashCheckout->request->get('status');

    if ($action === 'init') {
        $response = $bKashCheckout->createPayment();
        if (isset($response['bkashURL'])) {
            header('Location: ' . $response['bkashURL']);
            exit;
        }
        throw new \Exception('Failed to create payment: ' . ($response['statusMessage'] ?? 'Unknown error'));
    }

    if ($action === 'verify') {
        if ($status === 'success') {
            $result = $bKashCheckout->makeTransaction();
            redirSystemURL("id=$invoiceId&paymentsuccess=true", "viewinvoice.php");
            exit;
        }
        throw new \Exception('Payment status: ' . ($status ?? 'unknown'));
    }

    throw new \Exception('Invalid action');

} catch (\Exception $e) {
    $invoiceId = $invoiceId ?? '';
    $errorMessage = urlencode($e->getMessage());
    $errorCode = 'error';
    
    if (strpos($e->getMessage(), 'Invalid credentials') !== false) {
        $errorCode = 'auth_error';
        logActivity("bKash Auth Failed: " . $e->getMessage());
    }
    
    redirSystemURL("id=$invoiceId&paymentfailed=true&errorCode=$errorCode&message=$errorMessage", "viewinvoice.php");
    exit;
}