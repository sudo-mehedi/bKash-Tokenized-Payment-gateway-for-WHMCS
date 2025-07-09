# Installation Guide - Prothom Pay bKash Gateway

This comprehensive guide will walk you through the installation and configuration of the Prothom Pay bKash Gateway for WHMCS.

## Pre-Installation Requirements

### System Requirements
- **WHMCS**: Version 7.0 or higher
- **PHP**: Version 7.4 or higher
- **Web Server**: Apache/Nginx with SSL support
- **Database**: MySQL 5.6+ or MariaDB 10.0+
- **PHP Extensions**:
  - cURL
  - JSON
  - OpenSSL
  - PDO

### bKash Requirements
- Active bKash merchant account
- Approved merchant credentials
- Sandbox credentials (for testing)

## Step 1: Download and Extract Files

1. **Download** the Prothom Pay module files
2. **Extract** the files to a temporary directory
3. **Verify** the following file structure:
   ```
   prothompay/
   ├── prothompay.php
   ├── lib/
   │   ├── ProthomPayClient.php
   │   ├── ProthomPayLogger.php
   │   └── WowGatewayLogger.php
   └── callback/
       └── prothompay.php
   ```

## Step 2: Upload Files to WHMCS

### File Locations
Upload the files to your WHMCS installation directory:

```bash
# Main gateway file
/modules/gateways/prothompay.php

# Library files
/modules/gateways/lib/ProthomPayClient.php
/modules/gateways/lib/ProthomPayLogger.php
/modules/gateways/lib/WowGatewayLogger.php

# Callback handler
/modules/gateways/callback/prothompay.php
```

### Using FTP/SFTP
```bash
# Example using SCP
scp prothompay.php user@yourserver.com:/path/to/whmcs/modules/gateways/
scp lib/* user@yourserver.com:/path/to/whmcs/modules/gateways/lib/
scp callback/prothompay.php user@yourserver.com:/path/to/whmcs/modules/gateways/callback/
```

### Using File Manager
1. Access your hosting control panel
2. Navigate to WHMCS installation directory
3. Upload files to respective directories
4. Ensure proper file structure

## Step 3: Set File Permissions

Set appropriate permissions for security:

```bash
# Gateway files
chmod 644 /modules/gateways/prothompay.php
chmod 644 /modules/gateways/lib/ProthomPayClient.php
chmod 644 /modules/gateways/lib/ProthomPayLogger.php
chmod 644 /modules/gateways/lib/WowGatewayLogger.php

# Callback file
chmod 644 /modules/gateways/callback/prothompay.php

# Ensure directories are accessible
chmod 755 /modules/gateways/
chmod 755 /modules/gateways/lib/
chmod 755 /modules/gateways/callback/
```

## Step 4: Create Log Directory

Create the logging directory:

```bash
# Create logs directory
mkdir -p /storage/logs

# Set permissions
chmod 755 /storage/logs
chown www-data:www-data /storage/logs  # Adjust user/group as needed
```

## Step 5: Configure bKash Merchant Account

### Production Setup
1. **Login** to bKash Merchant Portal
2. **Navigate** to API Management
3. **Generate** API credentials:
   - Username
   - Password
   - App Key
   - App Secret
4. **Configure** callback URL: `https://yourdomain.com/modules/gateways/callback/prothompay.php`

### Sandbox Setup
1. **Contact** bKash for sandbox credentials
2. **Use** sandbox endpoint for testing
3. **Configure** test callback URL

## Step 6: WHMCS Gateway Configuration

### Access Gateway Settings
1. **Login** to WHMCS Admin Area
2. **Navigate** to `Setup > Payments > Payment Gateways`
3. **Click** "Manage Existing Gateways"
4. **Find** "Prothom Pay (bKash)" in the list

### Gateway Configuration
Configure the following settings:

| Setting | Value | Description |
|---------|--------|-------------|
| **Display Name** | Prothom Pay (bKash) | Name shown to customers |
| **API Username** | `[Your bKash Username]` | From merchant dashboard |
| **API Password** | `[Your bKash Password]` | From merchant dashboard |
| **App Key** | `[Your App Key]` | From merchant dashboard |
| **App Secret** | `[Your App Secret]` | From merchant dashboard |
| **Sandbox Mode** | `Yes/No` | Enable for testing |
| **Enable Debug Logging** | `Yes/No` | Enable for troubleshooting |

### Currency Configuration
1. **Navigate** to `Setup > Payments > Currencies`
2. **Ensure** BDT (Bangladeshi Taka) is configured
3. **Set** appropriate exchange rates if needed

## Step 7: Test Installation

### Sandbox Testing
1. **Enable** sandbox mode in gateway settings
2. **Use** sandbox credentials
3. **Create** a test invoice
4. **Attempt** payment with bKash sandbox

### Test Checklist
- [ ] Gateway appears in payment methods
- [ ] Payment button displays correctly
- [ ] Redirects to bKash payment page
- [ ] Callback processing works
- [ ] Invoice status updates correctly
- [ ] Logs are generated (if enabled)

## Step 8: SSL Certificate Verification

### Importance
SSL certificate is **required** for:
- Secure API communications
- Callback URL validation
- Customer data protection
- PCI compliance

### Verification
```bash
# Test SSL certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# Check certificate expiry
openssl x509 -in certificate.crt -text -noout | grep -A 2 "Validity"
```

## Step 9: Firewall and Security

### Whitelist IP Addresses
Add bKash IP addresses to your firewall whitelist:
```bash
# Example firewall rules (adjust as needed)
ufw allow from [bKash-IP-Range] to any port 443
ufw allow from [bKash-IP-Range] to any port 80
```

### Security Headers
Add security headers to your web server:
```apache
# Apache .htaccess
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
```

## Step 10: Production Deployment

### Pre-Production Checklist
- [ ] All files uploaded correctly
- [ ] Permissions set properly
- [ ] SSL certificate valid
- [ ] Production credentials configured
- [ ] Sandbox mode disabled
- [ ] Logging configured appropriately
- [ ] Firewall rules applied
- [ ] Backup created

### Go Live Process
1. **Disable** sandbox mode
2. **Update** credentials to production
3. **Test** with small amount
4. **Monitor** logs for issues
5. **Verify** payment processing

## Common Installation Issues

### Issue 1: Files Not Found
```
Error: Class 'ProthomPayClient' not found
Solution: Check file paths and permissions
```

### Issue 2: Permission Denied
```
Error: Permission denied to write log files
Solution: Set correct permissions on log directory
```

### Issue 3: SSL Certificate Issues
```
Error: SSL certificate problem
Solution: Verify SSL certificate is valid and trusted
```

### Issue 4: Callback URL Not Accessible
```
Error: Callback URL returns 404
Solution: Check file exists and web server configuration
```

## Post-Installation Steps

### Monitoring Setup
1. **Configure** log rotation
2. **Set up** monitoring alerts
3. **Create** backup schedule
4. **Document** configuration

### Maintenance
1. **Regular** log review
2. **Update** credentials as needed
3. **Monitor** API changes
4. **Test** functionality periodically

## Backup and Recovery

### Backup Files
```bash
# Backup gateway files
tar -czf prothompay-backup.tar.gz \
  /modules/gateways/prothompay.php \
  /modules/gateways/lib/ProthomPay*.php \
  /modules/gateways/callback/prothompay.php
```

### Database Backup
```bash
# Backup gateway configuration
mysqldump -u user -p whmcs_db tblpaymentgateways > gateway_config_backup.sql
```

## Support and Troubleshooting

### Log Files
Check these locations for troubleshooting:
- `/storage/logs/prothompay.log` - Module logs
- `/storage/logs/gateway.log` - WHMCS gateway logs
- Web server error logs

### Getting Help
**Developer**: Mehedi Hasan  
**Email**: mehedihasan2002.myc@gmail.com  
**Phone**: +8801601300220  

### Reporting Issues
When reporting issues, include:
1. WHMCS version
2. PHP version
3. Error messages
4. Log excerpts
5. Steps to reproduce

## Conclusion

Following this guide should result in a successful installation of the Prothom Pay bKash Gateway. Always test thoroughly in sandbox mode before going live with production credentials.

Remember to keep your credentials secure and regularly update the module as new versions become available.