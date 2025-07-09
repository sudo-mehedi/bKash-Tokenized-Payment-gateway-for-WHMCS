# Troubleshooting Guide - Prothom Pay bKash Gateway

This comprehensive troubleshooting guide helps you diagnose and resolve common issues with the Prothom Pay bKash Gateway for WHMCS.

## Table of Contents

1. [Quick Diagnostic Steps](#quick-diagnostic-steps)
2. [Common Issues](#common-issues)
3. [Authentication Problems](#authentication-problems)
4. [Payment Processing Issues](#payment-processing-issues)
5. [Callback Problems](#callback-problems)
6. [SSL and Security Issues](#ssl-and-security-issues)
7. [Configuration Issues](#configuration-issues)
8. [Performance Issues](#performance-issues)
9. [Logging and Debugging](#logging-and-debugging)
10. [Error Codes Reference](#error-codes-reference)
11. [Support Information](#support-information)

## Quick Diagnostic Steps

### Step 1: Check Gateway Status
```bash
# Check if gateway is active
mysql -u username -p -e "SELECT * FROM tblpaymentgateways WHERE gateway='prothompay';"
```

### Step 2: Verify Files
```bash
# Check if all files exist
ls -la /modules/gateways/prothompay.php
ls -la /modules/gateways/lib/ProthomPayClient.php
ls -la /modules/gateways/lib/ProthomPayLogger.php
ls -la /modules/gateways/callback/prothompay.php
```

### Step 3: Test Basic Connectivity
```bash
# Test bKash API connectivity
curl -I https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant
```

### Step 4: Check Logs
```bash
# View recent logs
tail -f /storage/logs/prothompay.log
```

### Step 5: Verify SSL Certificate
```bash
# Check SSL certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
```

## Common Issues

### Issue 1: Gateway Not Appearing in Payment Methods

**Symptoms:**
- Gateway not visible during checkout
- Payment method missing from invoice

**Causes:**
- Gateway not activated
- Configuration incomplete
- File permissions incorrect

**Solutions:**

1. **Check Gateway Activation:**
   ```sql
   -- Check if gateway is enabled
   SELECT * FROM tblpaymentgateways WHERE gateway='prothompay';
   ```

2. **Verify Configuration:**
   - Navigate to `Setup > Payments > Payment Gateways`
   - Find "Prothom Pay (bKash)" and ensure it's activated
   - Check all required fields are filled

3. **Check File Permissions:**
   ```bash
   chmod 644 /modules/gateways/prothompay.php
   chown www-data:www-data /modules/gateways/prothompay.php
   ```

### Issue 2: Payment Button Not Working

**Symptoms:**
- Button appears but doesn't redirect
- JavaScript errors
- Page refresh without action

**Causes:**
- JavaScript conflicts
- Invalid payment creation
- Missing credentials

**Solutions:**

1. **Check Browser Console:**
   - Open developer tools
   - Look for JavaScript errors
   - Check network requests

2. **Verify Credentials:**
   ```php
   // Test credentials
   $client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);
   try {
       $client->authenticate();
       echo "Credentials valid";
   } catch (Exception $e) {
       echo "Error: " . $e->getMessage();
   }
   ```

3. **Enable Debug Logging:**
   - Set "Enable Debug Logging" to "Yes"
   - Check logs for payment creation errors

### Issue 3: Callback Not Processing

**Symptoms:**
- Payment completed but invoice not updated
- Callback URL returns 404
- No callback received

**Causes:**
- Callback URL incorrect
- File permissions issue
- SSL certificate problems

**Solutions:**

1. **Verify Callback URL:**
   ```bash
   # Test callback URL accessibility
   curl -I https://yourdomain.com/modules/gateways/callback/prothompay.php
   ```

2. **Check File Permissions:**
   ```bash
   chmod 644 /modules/gateways/callback/prothompay.php
   chown www-data:www-data /modules/gateways/callback/prothompay.php
   ```

3. **Test Callback Manually:**
   ```php
   // Test callback processing
   $_POST['paymentID'] = 'test_payment_id';
   include '/modules/gateways/callback/prothompay.php';
   ```

## Authentication Problems

### Error: "Authentication failed"

**Symptoms:**
```
Error: Authentication failed
Status Code: 2004
Message: Invalid username and password combination
```

**Causes:**
- Incorrect credentials
- Expired credentials
- Wrong environment (sandbox vs production)

**Solutions:**

1. **Verify Credentials:**
   - Check username and password in bKash merchant dashboard
   - Ensure credentials match environment (sandbox/production)
   - Verify app key and app secret

2. **Check Environment:**
   ```php
   // Verify environment settings
   $isSandbox = $gatewayParams['isSandbox'] == 'on';
   $baseUrl = $isSandbox ? 
       'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized' : 
       'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized';
   ```

3. **Test Authentication:**
   ```php
   // Manual authentication test
   $client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);
   $result = $client->authenticate();
   ```

### Error: "Invalid App Key"

**Symptoms:**
```
Error: Invalid App Key
Status Code: 2001
```

**Solutions:**

1. **Verify App Key:**
   - Check app key in bKash merchant dashboard
   - Ensure no extra spaces or characters
   - Verify environment (sandbox vs production)

2. **Check Configuration:**
   ```php
   // Verify app key configuration
   $appKey = trim($gatewayParams['appKey']);
   if (empty($appKey)) {
       throw new Exception('App Key not configured');
   }
   ```

### Error: "Invalid Token"

**Symptoms:**
```
Error: Invalid Token
Status Code: 2005
```

**Solutions:**

1. **Token Refresh:**
   ```php
   // Force token refresh
   $this->accessToken = null;
   $this->authenticate();
   ```

2. **Check Token Expiry:**
   ```php
   // Implement token expiry check
   if ((time() - $this->lastAuth) > self::TOKEN_EXPIRY) {
       $this->authenticate();
   }
   ```

## Payment Processing Issues

### Error: "Payment creation failed"

**Symptoms:**
- Payment button appears but doesn't work
- Error during payment initialization
- No redirect to bKash

**Causes:**
- Invalid amount
- Network connectivity issues
- API rate limits exceeded

**Solutions:**

1. **Check Amount Format:**
   ```php
   // Ensure proper amount formatting
   $amount = number_format($amount, 2, '.', '');
   ```

2. **Verify Network Connectivity:**
   ```bash
   # Test API endpoint
   curl -v https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create
   ```

3. **Check Rate Limits:**
   - Review API documentation for rate limits
   - Implement retry logic with delays
   - Monitor API usage

### Error: "Amount mismatch"

**Symptoms:**
```
Error: Amount mismatch: Paid 100.00, Due 99.50 (Invoice 123456)
```

**Solutions:**

1. **Implement Amount Tolerance:**
   ```php
   // Allow 2% tolerance for amount differences
   $tolerance = $amountDue * 0.02;
   if (abs($amountPaid - $amountDue) > $tolerance) {
       throw new Exception('Amount mismatch');
   }
   ```

2. **Check Currency Conversion:**
   ```php
   // Verify currency conversion
   $convertedAmount = $originalAmount * $conversionRate;
   ```

### Error: "Transaction already exists"

**Symptoms:**
- Payment completed but error shown
- Duplicate transaction warnings

**Solutions:**

1. **Check Transaction ID:**
   ```php
   // Verify transaction doesn't already exist
   $existingTransaction = $this->checkTransaction($trxId);
   if ($existingTransaction) {
       throw new Exception('Transaction already exists');
   }
   ```

2. **Implement Idempotency:**
   ```php
   // Use unique transaction identifiers
   $uniqueId = $invoiceId . '-' . time() . '-' . uniqid();
   ```

## Callback Problems

### Error: "Callback URL not accessible"

**Symptoms:**
- bKash cannot reach callback URL
- Payments complete but invoices not updated
- HTTP 404 errors in logs

**Solutions:**

1. **Test Callback URL:**
   ```bash
   # Test from external location
   curl -X POST https://yourdomain.com/modules/gateways/callback/prothompay.php \
        -H "Content-Type: application/json" \
        -d '{"paymentID":"test"}'
   ```

2. **Check Firewall Rules:**
   ```bash
   # Allow bKash IP ranges
   ufw allow from [bKash-IP-Range] to any port 443
   ```

3. **Verify SSL Certificate:**
   ```bash
   # Check SSL certificate validity
   openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
   ```

### Error: "Missing payment ID"

**Symptoms:**
```
Error: Missing payment ID
HTTP Status: 400
```

**Solutions:**

1. **Check Request Data:**
   ```php
   // Enhanced input handling
   $input = array_merge(
       array_map('trim', $_GET),
       array_map('trim', $_POST),
       json_decode(file_get_contents('php://input'), true) ?: []
   );
   ```

2. **Validate Input:**
   ```php
   // Validate payment ID
   $paymentID = $input['paymentID'] ?? null;
   if (empty($paymentID)) {
       $logger->log('Missing payment ID', $input);
       header('HTTP/1.1 400 Bad Request');
       die('Missing payment ID');
   }
   ```

## SSL and Security Issues

### Error: "SSL certificate problem"

**Symptoms:**
```
Error: SSL certificate problem: unable to get local issuer certificate
```

**Solutions:**

1. **Update CA Bundle:**
   ```bash
   # Update CA certificates
   sudo apt-get update
   sudo apt-get install ca-certificates
   ```

2. **Configure cURL:**
   ```php
   // Set CA bundle path
   curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
   ```

3. **Verify Certificate:**
   ```bash
   # Check certificate chain
   openssl s_client -connect yourdomain.com:443 -showcerts
   ```

### Error: "Certificate verification failed"

**Solutions:**

1. **Check Certificate Validity:**
   ```bash
   # Verify certificate not expired
   openssl x509 -in certificate.crt -text -noout | grep -A 2 "Validity"
   ```

2. **Verify Domain Match:**
   ```bash
   # Check if certificate matches domain
   openssl x509 -in certificate.crt -text -noout | grep -A 1 "Subject:"
   ```

## Configuration Issues

### Error: "Module not activated"

**Symptoms:**
```
Error: Module Not Activated
HTTP Status: 403
```

**Solutions:**

1. **Check Gateway Status:**
   ```sql
   -- Verify gateway is active
   SELECT * FROM tblpaymentgateways WHERE gateway='prothompay' AND setting='type';
   ```

2. **Activate Gateway:**
   - Navigate to WHMCS Admin > Setup > Payments > Payment Gateways
   - Find "Prothom Pay (bKash)" and click "Activate"

### Error: "Invalid configuration"

**Solutions:**

1. **Validate Configuration:**
   ```php
   // Check all required fields
   $requiredFields = ['apiUsername', 'apiPassword', 'appKey', 'appSecret'];
   foreach ($requiredFields as $field) {
       if (empty($gatewayParams[$field])) {
           throw new Exception("Missing required field: $field");
       }
   }
   ```

2. **Check Field Values:**
   ```php
   // Sanitize and validate inputs
   $username = $this->sanitizeInput($gatewayParams['apiUsername']);
   $password = $this->sanitizeInput($gatewayParams['apiPassword']);
   ```

## Performance Issues

### Error: "Request timeout"

**Symptoms:**
- Long loading times
- Gateway timeouts
- Network errors

**Solutions:**

1. **Optimize Timeout Settings:**
   ```php
   // Set appropriate timeouts
   curl_setopt($ch, CURLOPT_TIMEOUT, 30);
   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
   ```

2. **Implement Connection Pooling:**
   ```php
   // Reuse cURL handles
   static $curlHandle = null;
   if ($curlHandle === null) {
       $curlHandle = curl_init();
       // Configure handle
   }
   ```

### Error: "Rate limit exceeded"

**Solutions:**

1. **Implement Rate Limiting:**
   ```php
   // Add delays between requests
   $lastRequest = time();
   $minInterval = 1; // seconds
   if ((time() - $lastRequest) < $minInterval) {
       sleep($minInterval - (time() - $lastRequest));
   }
   ```

2. **Use Exponential Backoff:**
   ```php
   // Retry with exponential backoff
   for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
       try {
           return $this->makeRequest();
       } catch (Exception $e) {
           if ($attempt < $maxAttempts) {
               sleep(pow(2, $attempt));
           }
       }
   }
   ```

## Logging and Debugging

### Enable Debug Mode

1. **Enable Debug Logging:**
   ```php
   // In gateway configuration
   'enableLogging' => 'on'
   ```

2. **Increase Log Level:**
   ```php
   // Enhanced debugging
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ini_set('log_errors', 1);
   ```

### Log Analysis

1. **View Recent Logs:**
   ```bash
   # Show last 100 lines
   tail -100 /storage/logs/prothompay.log
   ```

2. **Search for Errors:**
   ```bash
   # Find specific errors
   grep -i "error" /storage/logs/prothompay.log
   grep -i "authentication" /storage/logs/prothompay.log
   ```

3. **Filter by Date:**
   ```bash
   # Today's logs
   grep "$(date +%Y-%m-%d)" /storage/logs/prothompay.log
   ```

### Debug Information Collection

```php
// Collect debug information
$debugInfo = [
    'php_version' => phpversion(),
    'whmcs_version' => $GLOBALS['CONFIG']['Version'],
    'gateway_files' => [
        'main' => file_exists('/modules/gateways/prothompay.php'),
        'client' => file_exists('/modules/gateways/lib/ProthomPayClient.php'),
        'logger' => file_exists('/modules/gateways/lib/ProthomPayLogger.php'),
        'callback' => file_exists('/modules/gateways/callback/prothompay.php'),
    ],
    'ssl_info' => openssl_get_cert_locations(),
    'curl_version' => curl_version(),
    'server_info' => $_SERVER,
];
```

## Error Codes Reference

### bKash API Error Codes

| Code | Message | Description | Solution |
|------|---------|-------------|----------|
| 0000 | Successful | Operation completed successfully | No action needed |
| 2001 | Invalid App Key | App key is invalid | Verify app key |
| 2002 | Invalid App Secret | App secret is invalid | Verify app secret |
| 2003 | App Key and Secret mismatch | Credentials don't match | Check both credentials |
| 2004 | Invalid username and password | Authentication failed | Verify login credentials |
| 2005 | Invalid Token | Access token expired | Refresh token |
| 2006 | Insufficient balance | Merchant account low balance | Contact bKash |
| 2007 | Payment already completed | Duplicate payment | Check transaction status |
| 2008 | Invalid payment ID | Payment ID not found | Verify payment ID |
| 2009 | Transaction failed | Payment processing failed | Retry or contact support |
| 2010 | Invalid amount | Amount format incorrect | Check amount format |
| 2011 | Invalid currency | Currency not supported | Use BDT currency |
| 2012 | Transaction cancelled | User cancelled payment | Normal flow |
| 2013 | Transaction expired | Payment session expired | Retry payment |
| 2014 | Invalid callback URL | Callback URL unreachable | Fix callback URL |
| 9999 | System error | Internal system error | Contact bKash support |

### HTTP Error Codes

| Code | Description | Common Causes | Solutions |
|------|-------------|---------------|-----------|
| 400 | Bad Request | Invalid request data | Check request format |
| 401 | Unauthorized | Invalid credentials | Verify authentication |
| 403 | Forbidden | Access denied | Check permissions |
| 404 | Not Found | Resource not found | Verify URL/path |
| 405 | Method Not Allowed | Wrong HTTP method | Use correct method |
| 408 | Request Timeout | Request timed out | Increase timeout |
| 429 | Too Many Requests | Rate limit exceeded | Implement rate limiting |
| 500 | Internal Server Error | Server error | Check server logs |
| 502 | Bad Gateway | Gateway error | Check upstream service |
| 503 | Service Unavailable | Service down | Retry later |
| 504 | Gateway Timeout | Upstream timeout | Increase timeout |

## Diagnostic Tools

### Health Check Script

```php
<?php
// health_check.php
require_once __DIR__ . '/modules/gateways/lib/ProthomPayClient.php';
require_once __DIR__ . '/modules/gateways/lib/ProthomPayLogger.php';

function runHealthCheck() {
    $results = [];
    
    // Check files
    $results['files'] = [
        'gateway' => file_exists('/modules/gateways/prothompay.php'),
        'client' => file_exists('/modules/gateways/lib/ProthomPayClient.php'),
        'logger' => file_exists('/modules/gateways/lib/ProthomPayLogger.php'),
        'callback' => file_exists('/modules/gateways/callback/prothompay.php'),
    ];
    
    // Check configuration
    $gatewayParams = getGatewayVariables('prothompay');
    $results['configuration'] = [
        'username' => !empty($gatewayParams['apiUsername']),
        'password' => !empty($gatewayParams['apiPassword']),
        'app_key' => !empty($gatewayParams['appKey']),
        'app_secret' => !empty($gatewayParams['appSecret']),
    ];
    
    // Test authentication
    try {
        $client = new ProthomPayClient(
            $gatewayParams['apiUsername'],
            $gatewayParams['apiPassword'],
            $gatewayParams['appKey'],
            $gatewayParams['appSecret'],
            $gatewayParams['isSandbox'] ? 'sandbox_url' : 'production_url',
            new ProthomPayLogger()
        );
        $client->authenticate();
        $results['authentication'] = true;
    } catch (Exception $e) {
        $results['authentication'] = false;
        $results['auth_error'] = $e->getMessage();
    }
    
    return $results;
}

// Run health check
$health = runHealthCheck();
header('Content-Type: application/json');
echo json_encode($health, JSON_PRETTY_PRINT);
?>
```

### Log Analyzer Script

```php
<?php
// log_analyzer.php
function analyzeLogs($logFile = '/storage/logs/prothompay.log') {
    if (!file_exists($logFile)) {
        return ['error' => 'Log file not found'];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $stats = [
        'total_entries' => count($lines),
        'errors' => 0,
        'warnings' => 0,
        'successes' => 0,
        'recent_errors' => [],
        'common_errors' => [],
    ];
    
    $errorCounts = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'ERROR') !== false) {
            $stats['errors']++;
            // Extract error message
            $errorMsg = preg_replace('/^\[.*?\]\s*/', '', $line);
            $errorCounts[$errorMsg] = ($errorCounts[$errorMsg] ?? 0) + 1;
            
            // Keep recent errors
            if (count($stats['recent_errors']) < 10) {
                $stats['recent_errors'][] = $line;
            }
        } elseif (strpos($line, 'WARNING') !== false) {
            $stats['warnings']++;
        } elseif (strpos($line, 'SUCCESS') !== false) {
            $stats['successes']++;
        }
    }
    
    // Sort errors by frequency
    arsort($errorCounts);
    $stats['common_errors'] = array_slice($errorCounts, 0, 5, true);
    
    return $stats;
}

// Analyze logs
$analysis = analyzeLogs();
header('Content-Type: application/json');
echo json_encode($analysis, JSON_PRETTY_PRINT);
?>
```

## Support Information

### Developer Contact

**Name**: Mehedi Hasan  
**Email**: mehedihasan2002.myc@gmail.com  
**Phone**: +8801601300220  

### When to Contact Support

- **Authentication issues** that persist after credential verification
- **Configuration problems** not covered in this guide
- **Performance issues** affecting payment processing
- **Security concerns** or vulnerabilities
- **Integration questions** for custom implementations

### Information to Provide

When contacting support, include:

1. **Environment Information:**
   - WHMCS version
   - PHP version
   - Web server (Apache/Nginx)
   - Operating system

2. **Error Details:**
   - Exact error messages
   - When the error occurs
   - Steps to reproduce
   - Relevant log entries

3. **Configuration:**
   - Gateway settings (without sensitive data)
   - Environment (sandbox/production)
   - Any custom modifications

4. **Diagnostic Information:**
   - Health check results
   - Log analysis output
   - Network connectivity tests

### Support Response Time

- **Critical issues**: Within 24 hours
- **Standard issues**: Within 48 hours
- **General questions**: Within 72 hours

### Emergency Support

For critical production issues:
- Email with subject: "URGENT - Prothom Pay Issue"
- Include phone number for callback
- Provide detailed error information

---

**Note**: This troubleshooting guide is regularly updated. For the latest version and additional resources, contact the developer.