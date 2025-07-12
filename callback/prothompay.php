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

// 4. Check URL status parameter first (bKash sends explicit status)
$urlStatus = strtolower($input['status'] ?? '');
if (!empty($urlStatus)) {
    $logger->log('URL status parameter received', ['url_status' => $urlStatus]);
    
    if (in_array($urlStatus, ['failure', 'failed', 'cancel', 'cancelled', 'error'])) {
        $logger->log('Payment failed according to URL status', $input);
        
        // Try to extract invoice ID for proper redirection
        $invoiceId = null;
        if (!empty($paymentID)) {
            // Try to get invoice from payment reference if available
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
                
                $paymentStatus = $prothomPay->queryPayment($paymentID);
                $payerReference = $paymentStatus['payerReference'] ?? '';
                $invoiceNumber = str_replace('INV', '', $payerReference);
                $invoiceNumber = preg_replace('/[^0-9]/', '', $invoiceNumber);
                
                if (!empty($invoiceNumber)) {
                    $invoiceId = checkCbInvoiceID($invoiceNumber, $gatewayParams['name']);
                    if (!$invoiceId) {
                        $result = localAPI('GetInvoiceID', ['invoicenum' => $invoiceNumber]);
                        if ($result['result'] == 'success' && !empty($result['invoiceid'])) {
                            $invoiceId = $result['invoiceid'];
                        }
                    }
                }
            } catch (Exception $e) {
                $logger->log('Could not retrieve invoice ID for failed payment', ['error' => $e->getMessage()]);
            }
        }
        
        // Log the failed transaction
        logTransaction($gatewayParams['name'], [
            'error' => 'Payment failed according to URL status: ' . $urlStatus,
            'payment_id' => $paymentID,
            'url_status' => $urlStatus
        ], 'Failed');
        
        // Redirect back to invoice with failure message
        if (!empty($invoiceId)) {
            $errorMsg = urlencode('Payment failed: ' . ucfirst($urlStatus));
            $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=true&message=' . $errorMsg;
            header('Location: ' . $returnUrl);
            exit;
        } else {
            // If we can't find invoice ID, show generic error
            header('HTTP/1.1 400 Bad Request');
            die('Payment failed: ' . ucfirst($urlStatus));
        }
    }
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

    // 5. Verify payment status via API
    $paymentStatus = $prothomPay->queryPayment($paymentID);
    $logger->log('Payment status verified', $paymentStatus);

    // 6. Extract invoice ID early for error handling
    $payerReference = $paymentStatus['payerReference'] ?? '';
    $invoiceNumber = str_replace('INV', '', $payerReference);
    $invoiceNumber = preg_replace('/[^0-9]/', '', $invoiceNumber);
    
    $invoiceId = null;
    if (!empty($invoiceNumber)) {
        $invoiceId = checkCbInvoiceID($invoiceNumber, $gatewayParams['name']);
        if (!$invoiceId) {
            $result = localAPI('GetInvoiceID', ['invoicenum' => $invoiceNumber]);
            if ($result['result'] == 'success' && !empty($result['invoiceid'])) {
                $invoiceId = $result['invoiceid'];
            }
        }
    }

    $transactionStatus = $paymentStatus['transactionStatus'] ?? '';
    $statusMessage = $paymentStatus['statusMessage'] ?? '';
    
    // Check for successful payment first (primary success indicator)
    if ($statusMessage === 'Successful') {
        // Payment is successful, continue processing
        $logger->log('Payment verified as successful', $paymentStatus);
    } 
    // Handle specific failed/cancelled statuses
    elseif (in_array($transactionStatus, ['Cancelled']) || 
            in_array($statusMessage, ['Cancelled', 'Cancel'])) {
        $logger->log('Payment was cancelled by user', $paymentStatus);
        logTransaction($gatewayParams['name'], $paymentStatus, 'Cancelled');
        
        // Redirect back to invoice with cancellation message
        if (!empty($invoiceId)) {
            $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId . '&paymentcancelled=true';
            header('Location: ' . $returnUrl);
            exit;
        }
        header('HTTP/1.1 400 Bad Request');
        die('Payment was cancelled. Please try again.');
    }
    elseif (in_array($transactionStatus, ['Failed', 'Reversed']) || 
            in_array($statusMessage, ['Failed', 'Reversed', 'Failure'])) {
        $logger->log('Payment failed or reversed', $paymentStatus);
        logTransaction($gatewayParams['name'], $paymentStatus, 'Failed');
        
        // Redirect back to invoice with failure message
        if (!empty($invoiceId)) {
            $errorMsg = urlencode('Payment failed: ' . $statusMessage);
            $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=true&message=' . $errorMsg;
            header('Location: ' . $returnUrl);
            exit;
        }
        header('HTTP/1.1 400 Bad Request');
        die('Payment failed: ' . $statusMessage);
    }
    elseif (in_array($transactionStatus, ['Initiated', 'Authorized', 'Pending']) || 
            in_array($statusMessage, ['Initiated', 'Authorized', 'Pending', 'In Progress'])) {
        $logger->log('Payment still pending', $paymentStatus);
        
        // For pending payments, redirect back to invoice
        if (!empty($invoiceId)) {
            $returnUrl = rtrim($gatewayParams['systemurl'], '/') . '/viewinvoice.php?id=' . $invoiceId . '&paymentpending=true';
            header('Location: ' . $returnUrl);
            exit;
        }
        header('HTTP/1.1 202 Accepted');
        die('Payment is still being processed. Please wait.');
    }
    else {
        // Unknown status - log and throw error
        $logger->log('Unknown payment status received', [
            'transactionStatus' => $transactionStatus,
            'statusMessage' => $statusMessage,
            'full_response' => $paymentStatus
        ]);
        throw new Exception("Payment status unclear: " . $statusMessage . " (Status: " . $transactionStatus . ")");
    }

    // 7. Validate invoice ID (now that we know payment is successful)
    if (empty($invoiceNumber)) {
        throw new Exception("Could not determine invoice number from payerReference");
    }
    
    if (!$invoiceId) {
        throw new Exception("Invoice not found for number: $invoiceNumber");
    }

    // 8. Get invoice data from WHMCS
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

    // 9. Validate amount with 2% tolerance
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

    // 10. Record payment in WHMCS
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

    // 11. Update invoice status
    $updateResult = localAPI('UpdateInvoice', [
        'invoiceid' => $invoiceId,
        'status' => 'Paid',
        'paymentmethod' => $gatewayModule
    ]);

    // 12. Verify update
    $updatedInvoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
    if ($updatedInvoice['status'] !== 'Paid') {
        $logger->log('Invoice status not updated properly', $updatedInvoice);
        throw new Exception("Invoice status update failed");
    }

    // 13. Log success
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

    // 14. Redirect to invoice with success message
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