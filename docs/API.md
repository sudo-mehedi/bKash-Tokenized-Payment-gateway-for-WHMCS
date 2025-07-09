# API Documentation - Prothom Pay bKash Gateway

This document provides comprehensive API documentation for the Prothom Pay bKash Gateway integration with WHMCS.

## Overview

The Prothom Pay bKash Gateway provides a seamless integration between WHMCS and bKash's Tokenized Checkout API. This documentation covers all API endpoints, request/response formats, and implementation details.

## Base URLs

### Production
```
https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized
```

### Sandbox
```
https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized
```

## Authentication

### Token Grant

Obtain access token for API authentication.

**Endpoint**: `POST /checkout/token/grant`

**Headers**:
```http
Content-Type: application/json
username: {merchant_username}
password: {merchant_password}
```

**Request Body**:
```json
{
    "app_key": "your_app_key",
    "app_secret": "your_app_secret"
}
```

**Response**:
```json
{
    "statusCode": "0000",
    "statusMessage": "Successful",
    "id_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "refresh_token_here"
}
```

**Error Response**:
```json
{
    "statusCode": "9999",
    "statusMessage": "Invalid username and password combination"
}
```

### Authentication Headers

For all subsequent API calls, include:
```http
Authorization: Bearer {id_token}
X-APP-KEY: {app_key}
Content-Type: application/json
```

## Payment APIs

### Create Payment

Create a new payment request.

**Endpoint**: `POST /checkout/create`

**Headers**:
```http
Authorization: Bearer {id_token}
X-APP-KEY: {app_key}
Content-Type: application/json
```

**Request Body**:
```json
{
    "mode": "0011",
    "payerReference": "INV123456",
    "callbackURL": "https://yourdomain.com/modules/gateways/callback/prothompay.php",
    "amount": "100.00",
    "currency": "BDT",
    "intent": "sale",
    "merchantInvoiceNumber": "123456"
}
```

**Request Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `mode` | String | Yes | Payment mode (0011 for checkout) |
| `payerReference` | String | Yes | Reference for payer (invoice ID) |
| `callbackURL` | String | Yes | Callback URL for payment notification |
| `amount` | String | Yes | Payment amount (formatted as decimal) |
| `currency` | String | Yes | Currency code (BDT) |
| `intent` | String | Yes | Payment intent (sale/authorization) |
| `merchantInvoiceNumber` | String | Yes | Merchant invoice number |

**Success Response**:
```json
{
    "statusCode": "0000",
    "statusMessage": "Successful",
    "paymentID": "TR0011O1234567890123456789012345",
    "bkashURL": "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/payment/TR0011O1234567890123456789012345",
    "callbackURL": "https://yourdomain.com/modules/gateways/callback/prothompay.php",
    "successCallbackURL": "https://yourdomain.com/modules/gateways/callback/prothompay.php",
    "failureCallbackURL": "https://yourdomain.com/modules/gateways/callback/prothompay.php",
    "cancelledCallbackURL": "https://yourdomain.com/modules/gateways/callback/prothompay.php",
    "amount": "100.00",
    "intent": "sale",
    "currency": "BDT",
    "paymentCreateTime": "2024-01-01T10:00:00:000 GMT+0600",
    "transactionStatus": "Initiated",
    "merchantInvoiceNumber": "123456"
}
```

**Error Response**:
```json
{
    "statusCode": "2001",
    "statusMessage": "Invalid App Key"
}
```

### Execute Payment

Execute a payment after user authorization.

**Endpoint**: `POST /checkout/execute`

**Headers**:
```http
Authorization: Bearer {id_token}
X-APP-KEY: {app_key}
Content-Type: application/json
```

**Request Body**:
```json
{
    "paymentID": "TR0011O1234567890123456789012345"
}
```

**Success Response**:
```json
{
    "statusCode": "0000",
    "statusMessage": "Successful",
    "paymentID": "TR0011O1234567890123456789012345",
    "trxID": "8FK9ABCDEFG",
    "transactionStatus": "Completed",
    "amount": "100.00",
    "currency": "BDT",
    "intent": "sale",
    "paymentExecuteTime": "2024-01-01T10:05:00:000 GMT+0600",
    "merchantInvoiceNumber": "123456",
    "payerReference": "INV123456"
}
```

### Query Payment

Query payment status by payment ID.

**Endpoint**: `GET /checkout/payment/status`

**Headers**:
```http
Authorization: Bearer {id_token}
X-APP-KEY: {app_key}
Content-Type: application/json
```

**Query Parameters**:
```
paymentID={payment_id}
```

**Success Response**:
```json
{
    "statusCode": "0000",
    "statusMessage": "Successful",
    "paymentID": "TR0011O1234567890123456789012345",
    "trxID": "8FK9ABCDEFG",
    "transactionStatus": "Completed",
    "amount": "100.00",
    "currency": "BDT",
    "intent": "sale",
    "paymentCreateTime": "2024-01-01T10:00:00:000 GMT+0600",
    "paymentExecuteTime": "2024-01-01T10:05:00:000 GMT+0600",
    "merchantInvoiceNumber": "123456",
    "payerReference": "INV123456"
}
```

## ProthomPayClient Class

### Constructor

Initialize the ProthomPayClient with credentials and configuration.

```php
public function __construct(
    $username,      // bKash merchant username
    $password,      // bKash merchant password
    $appKey,        // bKash app key
    $appSecret,     // bKash app secret
    $baseUrl,       // API base URL
    $logger         // Logger instance
)
```

### Methods

#### authenticate()

Authenticate with bKash API and obtain access token.

```php
public function authenticate(): bool
```

**Returns**: `bool` - True on successful authentication

**Throws**: `Exception` - On authentication failure

**Example**:
```php
$client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);
$authenticated = $client->authenticate();
```

#### createPayment()

Create a new payment request.

```php
public function createPayment(
    string $invoiceId,
    float $amount,
    string $callbackUrl
): array
```

**Parameters**:
- `$invoiceId`: WHMCS invoice ID
- `$amount`: Payment amount
- `$callbackUrl`: Callback URL for payment notification

**Returns**: `array` - Payment creation response

**Throws**: `Exception` - On payment creation failure

**Example**:
```php
$payment = $client->createPayment(
    '123456',
    100.00,
    'https://yourdomain.com/callback'
);
```

#### queryPayment()

Query payment status by payment ID.

```php
public function queryPayment(string $paymentID): array
```

**Parameters**:
- `$paymentID`: Payment ID from bKash

**Returns**: `array` - Payment status response

**Throws**: `Exception` - On query failure

**Example**:
```php
$status = $client->queryPayment('TR0011O1234567890123456789012345');
```

## ProthomPayLogger Class

### Constructor

Initialize the logger with configuration.

```php
public function __construct(bool $enabled = true)
```

### Methods

#### log()

Log a message with optional context.

```php
public function log(string $message, array $context = []): void
```

**Parameters**:
- `$message`: Log message
- `$context`: Additional context data

**Example**:
```php
$logger = new ProthomPayLogger(true);
$logger->log('Payment created', ['invoice_id' => 123456]);
```

#### getLogs()

Retrieve recent log entries.

```php
public static function getLogs(int $lines = 100): array
```

**Parameters**:
- `$lines`: Number of recent lines to retrieve

**Returns**: `array` - Array of log entries

## Status Codes

### Success Codes

| Code | Message | Description |
|------|---------|-------------|
| 0000 | Successful | Operation completed successfully |

### Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 2001 | Invalid App Key | App key is invalid or expired |
| 2002 | Invalid App Secret | App secret is invalid |
| 2003 | App Key and Secret mismatch | App key and secret don't match |
| 2004 | Invalid username and password | Authentication credentials invalid |
| 2005 | Invalid Token | Access token is invalid or expired |
| 2006 | Insufficient balance | Merchant account has insufficient balance |
| 2007 | Payment already completed | Payment has already been processed |
| 2008 | Invalid payment ID | Payment ID is invalid or not found |
| 2009 | Transaction failed | Payment processing failed |
| 2010 | Invalid amount | Payment amount is invalid |
| 2011 | Invalid currency | Currency code is not supported |
| 2012 | Transaction cancelled | Payment was cancelled by user |
| 2013 | Transaction expired | Payment session has expired |
| 2014 | Invalid callback URL | Callback URL is invalid or unreachable |
| 9999 | System error | Internal system error |

## Transaction Status

### Status Values

| Status | Description |
|--------|-------------|
| `Initiated` | Payment request created |
| `Authorized` | Payment authorized by user |
| `Completed` | Payment successfully completed |
| `Cancelled` | Payment cancelled by user |
| `Failed` | Payment processing failed |
| `Expired` | Payment session expired |

## Callback Handling

### Callback Request

bKash sends callback notifications to your specified callback URL.

**Method**: `POST`

**Headers**:
```http
Content-Type: application/json
User-Agent: bKash-API-Client
```

**Request Body**:
```json
{
    "paymentID": "TR0011O1234567890123456789012345",
    "status": "success",
    "trxID": "8FK9ABCDEFG",
    "amount": "100.00",
    "currency": "BDT",
    "payerReference": "INV123456"
}
```

### Callback Response

Your callback handler should respond with:

**Success Response**:
```http
HTTP/1.1 200 OK
Content-Type: application/json

{
    "status": "success",
    "message": "Payment processed successfully"
}
```

**Error Response**:
```http
HTTP/1.1 400 Bad Request
Content-Type: application/json

{
    "status": "error",
    "message": "Payment processing failed"
}
```

## Rate Limits

### API Limits

- **Authentication**: 10 requests per minute
- **Payment Creation**: 100 requests per minute
- **Payment Query**: 500 requests per minute

### Best Practices

1. **Cache tokens** until expiration
2. **Implement retry logic** with exponential backoff
3. **Use connection pooling** for multiple requests
4. **Monitor rate limit headers**

## Security

### Data Protection

- All API communications use HTTPS/TLS
- Sensitive data is encrypted in transit
- Access tokens have limited lifespan
- Implement proper input validation

### Authentication Security

- Store credentials securely
- Use environment variables for sensitive data
- Implement token refresh logic
- Log authentication attempts

## Error Handling

### Exception Handling

```php
try {
    $client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);
    $payment = $client->createPayment($invoiceId, $amount, $callbackUrl);
} catch (Exception $e) {
    $logger->log('Payment creation failed: ' . $e->getMessage());
    // Handle error appropriately
}
```

### Retry Logic

```php
$maxRetries = 3;
$retryDelay = 2; // seconds

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $result = $client->queryPayment($paymentID);
        break; // Success
    } catch (Exception $e) {
        if ($attempt === $maxRetries) {
            throw $e; // Final attempt failed
        }
        sleep($retryDelay * $attempt); // Exponential backoff
    }
}
```

## Testing

### Sandbox Environment

Use sandbox credentials for testing:

```php
$client = new ProthomPayClient(
    'sandbox_username',
    'sandbox_password',
    'sandbox_app_key',
    'sandbox_app_secret',
    'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized',
    $logger
);
```

### Test Scenarios

1. **Successful Payment**
   - Create payment
   - Complete payment flow
   - Verify callback processing

2. **Failed Payment**
   - Invalid credentials
   - Network timeouts
   - Insufficient balance

3. **Edge Cases**
   - Expired tokens
   - Invalid payment IDs
   - Callback failures

## Performance Optimization

### Connection Management

```php
// Reuse cURL handles
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
```

### Caching Strategy

```php
// Cache authentication tokens
$cacheKey = 'bkash_token_' . md5($username . $appKey);
$token = cache_get($cacheKey);
if (!$token) {
    $token = $client->authenticate();
    cache_set($cacheKey, $token, 3500); // Cache for 58 minutes
}
```

## Integration Examples

### Complete Payment Flow

```php
// Initialize client
$client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);

// Create payment
$payment = $client->createPayment($invoiceId, $amount, $callbackUrl);

// Redirect user to bKash
header('Location: ' . $payment['bkashURL']);
exit;

// In callback handler
$paymentStatus = $client->queryPayment($_POST['paymentID']);
if ($paymentStatus['transactionStatus'] === 'Completed') {
    // Process successful payment
    $this->recordPayment($paymentStatus);
}
```

### Error Handling Example

```php
try {
    $client = new ProthomPayClient($username, $password, $appKey, $appSecret, $baseUrl, $logger);
    $payment = $client->createPayment($invoiceId, $amount, $callbackUrl);
    
    return $payment;
} catch (Exception $e) {
    $logger->log('Payment creation failed', [
        'error' => $e->getMessage(),
        'invoice_id' => $invoiceId,
        'amount' => $amount
    ]);
    
    throw new Exception('Payment initialization failed: ' . $e->getMessage());
}
```

## Developer Information

**Developer**: Mehedi Hasan  
**Email**: mehedihasan2002.myc@gmail.com  
**Phone**: +8801601300220  

For technical support and API-related questions, please contact the developer with:
- Detailed error descriptions
- Relevant log entries
- API request/response samples
- Environment information

---

*This documentation is maintained by the developer and updated regularly to reflect API changes and improvements.*