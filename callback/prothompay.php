<?php
define("WHMCS", true);
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../lib/ProthomPayLogger.php';

$gatewayModule = 'prothompay';
$gatewayParams = getGatewayVariables($gatewayModule);
$logger = new ProthomPayLogger($gatewayParams['enableLogging'] == 'on');

// 1. Collect and sanitize all input data
$input = array_merge(
    array_map('trim', $_GET),
    array_map('trim', $_POST),
    json_decode(file_get_contents('php://input'), true) ?: []
);

$logger->log('Callback received', [
    'input' => $input,
    'server' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR']
    ]
]);

// 2. Validate module activation
if (!$gatewayParams['type']) {
    $logger->log('Module not activated');
    header('HTTP/1.1 403 Forbidden');
    die('Module Not Activated');
}

// 3. Extract and validate payment ID
$paymentID = $input['paymentID'] ?? null;
if (empty($paymentID)) {
    $logger->log('Missing payment ID');
    header('HTTP/1.1 400 Bad Request');
    die('Missing payment ID');
}

try {
    require_once __DIR__ . '/../lib/ProthomPayClient.php';
    $prothomPay = new ProthomPayClient(
        $gatewayParams['apiUsername'],
        $gatewayParams['apiPassword'],
        $gatewayParams['appKey'],
        $gatewayParams['appSecret'],
        $gatewayParams['isSandbox'] == 'on' 
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized' 
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized',
        $logger
    );

    // 4. Verify payment status
    $paymentStatus = $prothomPay->queryPayment($paymentID);
    $logger->log('Payment status verified', $paymentStatus);

    if ($paymentStatus['statusMessage'] !== 'Successful') {
        throw new Exception($paymentStatus['statusMessage'] ?? 'Payment not completed');
    }

    // 5. Get WHMCS invoice ID from payerReference
    $payerReference = $paymentStatus['payerReference'] ?? '';
    $invoiceNumber = str_replace('INV', '', $payerReference);
    $invoiceNumber = preg_replace('/[^0-9]/', '', $invoiceNumber);
    
    if (empty($invoiceNumber)) {
        throw new Exception("Could not determine invoice number from payerReference");
    }

    // 6. Get invoice data from WHMCS
    $invoiceId = checkCbInvoiceID($invoiceNumber, $gatewayParams['name']);
    
    if (!$invoiceId) {
        $result = localAPI('GetInvoiceID', ['invoicenum' => $invoiceNumber]);
        if ($result['result'] == 'success' && !empty($result['invoiceid'])) {
            $invoiceId = $result['invoiceid'];
        } else {
            throw new Exception("Invoice not found for number: $invoiceNumber");
        }
    }

    // Check if invoice is already paid
    $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
    if ($invoiceData['result'] !== 'success') {
        throw new Exception("Failed to fetch invoice data");
    }

    if ($invoiceData['status'] == 'Paid') {
        // Generate return URL to invoice
        $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId;
        header('Location: ' . $returnUrl);
        exit;
    }

    // 7. Validate amount with 2% tolerance
    $amountPaid = (float)$paymentStatus['amount'];
    $amountDue = (float)$invoiceData['total'];
    $tolerance = $amountDue * 0.02;

    if (abs($amountPaid - $amountDue) > $tolerance) {
        throw new Exception(sprintf(
            'Amount mismatch: Paid %s, Due %s (Invoice %s)',
            $amountPaid,
            $amountDue,
            $invoiceNumber
        ));
    }

    // 8. Record payment in WHMCS
    $transactionId = addInvoicePayment(
        $invoiceId,
        $paymentStatus['trxID'],
        $amountPaid,
        0, // Payment fee
        $gatewayModule
    );

    if (!$transactionId) {
        throw new Exception("Failed to record payment in WHMCS");
    }

    // 9. Update invoice status
    $updateResult = localAPI('UpdateInvoice', [
        'invoiceid' => $invoiceId,
        'status' => 'Paid',
        'paymentmethod' => $gatewayModule
    ]);

    // 10. Verify update
    $updatedInvoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
    if ($updatedInvoice['status'] !== 'Paid') {
        $logger->log('Invoice status not updated properly', $updatedInvoice);
        throw new Exception("Invoice status update failed");
    }

    // 11. Log success
    logTransaction($gatewayParams['name'], $paymentStatus, 'Success');
    $logger->log('Payment completed successfully', [
        'invoice_id' => $invoiceId,
        'invoice_number' => $invoiceNumber,
        'transaction_id' => $transactionId,
        'amount_paid' => $amountPaid,
        'trx_id' => $paymentStatus['trxID'],
        'payment_id' => $paymentID,
        'invoice_status' => $updatedInvoice['status']
    ]);

    // 12. Redirect to invoice with success message
    $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId;
    header('Location: ' . $returnUrl);
    exit;

} catch (Exception $e) {
    $logger->log('Payment processing failed', [
        'error' => $e->getMessage(),
        'payment_id' => $paymentID,
        'trace' => $e->getTraceAsString(),
        'input_data' => $input
    ]);
    
    logTransaction($gatewayParams['name'], [
        'error' => $e->getMessage(),
        'payment_id' => $paymentID,
    ], 'Failed');
    
    header('HTTP/1.1 500 Internal Server Error');
    die('Payment processing failed. Please contact support with reference: ' . $e->getMessage());
}