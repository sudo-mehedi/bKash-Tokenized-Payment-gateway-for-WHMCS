# Configuration Guide - Prothom Pay bKash Gateway

This guide provides detailed instructions for configuring the Prothom Pay bKash Gateway for WHMCS.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Gateway Configuration](#gateway-configuration)
3. [Currency Setup](#currency-setup)
4. [Security Configuration](#security-configuration)
5. [Testing Configuration](#testing-configuration)
6. [Production Configuration](#production-configuration)
7. [Advanced Settings](#advanced-settings)
8. [Troubleshooting](#troubleshooting)

## Prerequisites

Before configuring the gateway, ensure you have:

- ✅ WHMCS installed and running
- ✅ Valid bKash merchant account
- ✅ bKash API credentials (production and sandbox)
- ✅ SSL certificate installed
- ✅ Module files uploaded to WHMCS

## Gateway Configuration

### Step 1: Access Gateway Settings

1. **Login** to WHMCS Admin Area
2. **Navigate** to `Setup > Payments > Payment Gateways`
3. **Click** "All Payment Gateways"
4. **Find** "Prothom Pay (bKash)" and click "Activate"

### Step 2: Basic Configuration

Configure the following mandatory settings:

#### Display Settings
```
Display Name: Prothom Pay (bKash)
```
*This name will be shown to customers during checkout*

#### API Credentials
```
API Username: [Your bKash merchant username]
API Password: [Your bKash merchant password]
App Key: [Your bKash app key]
App Secret: [Your bKash app secret]
```

### Step 3: Environment Configuration

#### Sandbox Mode
```
Sandbox Mode: Yes (for testing) / No (for production)
```

**Sandbox Configuration:**
- Used for testing with bKash sandbox environment
- No real money transactions
- Separate credentials required
- Safe for development and testing

**Production Configuration:**
- Used for live transactions
- Real money processing
- Production credentials required
- Requires thorough testing first

### Step 4: Advanced Settings

#### Debug Logging
```
Enable Debug Logging: Yes / No
```

**When to Enable:**
- During initial setup
- When troubleshooting issues
- For monitoring transaction flow
- Performance debugging

**Log Location:**
```
/storage/logs/prothompay.log
```

## Currency Setup

### Step 1: Configure BDT Currency

1. **Navigate** to `Setup > Payments > Currencies`
2. **Add** or **Edit** BDT (Bangladeshi Taka)
3. **Configure** the following:

```
Currency Code: BDT
Currency Name: Bangladeshi Taka
Symbol: ৳
Decimal Places: 2
Exchange Rate: [Current rate from your base currency]
```

### Step 2: Currency Conversion

If your base currency is not BDT:

```
Base Currency: USD (example)
Gateway Currency: BDT
Conversion Rate: 110.00 (example rate)
```

**Rate Update Methods:**
- Manual updates
- Automated currency feeds
- API-based updates

### Step 3: Currency Display

Configure how currency is displayed:

```
Currency Position: Left (৳100.00)
Thousands Separator: ,
Decimal Separator: .
```

## Security Configuration

### Step 1: SSL Certificate

**Requirements:**
- Valid SSL certificate
- Proper certificate chain
- No mixed content warnings

**Verification:**
```bash
# Test SSL certificate
curl -I https://yourdomain.com/modules/gateways/callback/prothompay.php
```

### Step 2: Callback URL Security

**Callback URL Format:**
```
https://yourdomain.com/modules/gateways/callback/prothompay.php
```

**Security Measures:**
- HTTPS only
- Input validation
- Request verification
- Rate limiting

### Step 3: Credential Security

**Best Practices:**
- Never hardcode credentials
- Use environment variables
- Restrict file permissions
- Regular credential rotation

**File Permissions:**
```bash
chmod 600 /path/to/config/file
chown www-data:www-data /path/to/config/file
```

## Testing Configuration

### Step 1: Sandbox Setup

Configure sandbox environment:

```
Sandbox Mode: Yes
API Username: sandbox_username
API Password: sandbox_password
App Key: sandbox_app_key
App Secret: sandbox_app_secret
```

### Step 2: Test Credentials

**Obtaining Sandbox Credentials:**
1. Contact bKash support
2. Request sandbox access
3. Receive test credentials
4. Configure in WHMCS

### Step 3: Test Configuration

**Test Checklist:**
- [ ] Gateway activation successful
- [ ] Credentials validation working
- [ ] Payment button appears
- [ ] Redirect to bKash works
- [ ] Callback processing functional
- [ ] Invoice status updates
- [ ] Logging operational

### Step 4: Test Scenarios

**Successful Payment:**
```
Amount: 10.00 BDT
Status: Completed
Expected: Invoice marked as paid
```

**Failed Payment:**
```
Amount: 1.00 BDT
Action: Cancel payment
Expected: Invoice remains unpaid
```

## Production Configuration

### Step 1: Production Credentials

**Before Going Live:**
- Obtain production credentials
- Verify merchant account status
- Test with small amounts
- Backup current configuration

**Configuration:**
```
Sandbox Mode: No
API Username: production_username
API Password: production_password
App Key: production_app_key
App Secret: production_app_secret
```

### Step 2: Production Testing

**Pre-Launch Testing:**
```
Test Amount: 1.00 BDT
Test Invoice: Create test invoice
Test Payment: Complete payment flow
Verify: Payment processing and callback
```

### Step 3: Go-Live Checklist

- [ ] Production credentials configured
- [ ] Sandbox mode disabled
- [ ] SSL certificate valid
- [ ] Callback URL accessible
- [ ] Logging configured appropriately
- [ ] Backup created
- [ ] Team notified
- [ ] Monitoring enabled

## Advanced Settings

### Step 1: Custom Callback Handling

**Default Callback URL:**
```
https://yourdomain.com/modules/gateways/callback/prothompay.php
```

**Custom Callback (if needed):**
```php
// In gateway configuration
$callbackUrl = 'https://yourdomain.com/custom/callback.php';
```

### Step 2: Fee Configuration

**Transaction Fees:**
```php
// Configure in gateway settings
$feePercentage = 2.5; // 2.5% fee
$fixedFee = 5.00; // Fixed fee in BDT
```

### Step 3: Timeout Configuration

**API Timeouts:**
```php
// Connection timeout
$connectionTimeout = 10; // seconds

// Request timeout
$requestTimeout = 30; // seconds
```

### Step 4: Retry Configuration

**Retry Settings:**
```php
// Maximum retry attempts
$maxRetries = 3;

// Retry delay
$retryDelay = 2; // seconds

// Exponential backoff
$backoffMultiplier = 2;
```

## Configuration Files

### Gateway Configuration File

**Location:** `/modules/gateways/prothompay.php`

**Key Configuration Functions:**
```php
function prothompay_config() {
    return [
        'apiUsername' => [
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '50',
        ],
        // ... other settings
    ];
}
```

### Environment Configuration

**Create `.env` file (recommended):**
```env
BKASH_API_USERNAME=your_username
BKASH_API_PASSWORD=your_password
BKASH_APP_KEY=your_app_key
BKASH_APP_SECRET=your_app_secret
BKASH_SANDBOX=true
BKASH_LOGGING=true
```

## Logging Configuration

### Log Levels

```php
// Configure log levels
$logLevels = [
    'error' => true,
    'warning' => true,
    'info' => true,
    'debug' => false // Enable only for debugging
];
```

### Log Rotation

**Setup Log Rotation:**
```bash
# Create logrotate configuration
cat > /etc/logrotate.d/prothompay << EOF
/path/to/whmcs/storage/logs/prothompay.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

## Monitoring Configuration

### Health Checks

**Setup Health Check:**
```php
// Health check endpoint
function prothompay_health_check() {
    $client = new ProthomPayClient(...);
    try {
        $client->authenticate();
        return ['status' => 'healthy'];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', 'error' => $e->getMessage()];
    }
}
```

### Performance Monitoring

**Key Metrics:**
- Payment success rate
- Response times
- Error rates
- Authentication failures

## Troubleshooting

### Common Configuration Issues

#### Issue 1: Invalid Credentials
```
Error: Authentication failed
Solution: Verify credentials in bKash merchant dashboard
```

#### Issue 2: Callback URL Not Reachable
```
Error: Callback URL returns 404
Solution: Check file exists and permissions
```

#### Issue 3: SSL Certificate Issues
```
Error: SSL certificate problem
Solution: Verify certificate validity
```

#### Issue 4: Currency Configuration
```
Error: Invalid currency
Solution: Ensure BDT is properly configured
```

### Configuration Validation

**Validation Script:**
```php
// Test configuration
$config = prothompay_config();
$params = getGatewayVariables('prothompay');

// Validate credentials
if (empty($params['apiUsername'])) {
    throw new Exception('API Username not configured');
}

// Test connection
$client = new ProthomPayClient(...);
$client->authenticate();
```

### Debug Mode

**Enable Debug Mode:**
```php
// In configuration
$debugMode = true;

// Enhanced logging
if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
```

## Best Practices

### Security Best Practices

1. **Never** commit credentials to version control
2. **Use** environment variables for sensitive data
3. **Implement** proper input validation
4. **Enable** HTTPS only
5. **Regular** credential rotation

### Performance Best Practices

1. **Cache** authentication tokens
2. **Implement** connection pooling
3. **Use** async processing for callbacks
4. **Monitor** API response times
5. **Optimize** database queries

### Maintenance Best Practices

1. **Regular** log review
2. **Monitor** error rates
3. **Test** functionality periodically
4. **Update** exchange rates
5. **Backup** configuration regularly

## Configuration Templates

### Development Environment
```php
$config = [
    'apiUsername' => 'sandbox_user',
    'apiPassword' => 'sandbox_pass',
    'appKey' => 'sandbox_key',
    'appSecret' => 'sandbox_secret',
    'isSandbox' => true,
    'enableLogging' => true,
    'debugMode' => true
];
```

### Production Environment
```php
$config = [
    'apiUsername' => getenv('BKASH_USERNAME'),
    'apiPassword' => getenv('BKASH_PASSWORD'),
    'appKey' => getenv('BKASH_APP_KEY'),
    'appSecret' => getenv('BKASH_APP_SECRET'),
    'isSandbox' => false,
    'enableLogging' => false,
    'debugMode' => false
];
```

## Support

### Configuration Support

**Developer**: Mehedi Hasan  
**Email**: mehedihasan2002.myc@gmail.com  
**Phone**: +8801601300220  

### When to Contact Support

- Configuration issues
- Credential problems
- Integration questions
- Performance concerns
- Security questions

### Information to Provide

When contacting support, include:
- WHMCS version
- PHP version
- Configuration details (without sensitive data)
- Error messages
- Log entries
- Steps to reproduce issues

---

**Note**: Always test configuration changes in a staging environment before applying to production.