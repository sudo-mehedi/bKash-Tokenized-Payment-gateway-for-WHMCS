# Contributing to Prothom Pay bKash Gateway

Thank you for your interest in contributing to the Prothom Pay bKash Gateway for WHMCS! This document provides guidelines and information for contributors.

## Table of Contents

1. [Getting Started](#getting-started)
2. [How to Contribute](#how-to-contribute)
3. [Development Setup](#development-setup)
4. [Code Guidelines](#code-guidelines)
5. [Testing](#testing)
6. [Submitting Changes](#submitting-changes)
7. [Reporting Issues](#reporting-issues)
8. [Feature Requests](#feature-requests)
9. [Code of Conduct](#code-of-conduct)
10. [Support](#support)

## Getting Started

### Prerequisites

Before contributing, ensure you have:
- WHMCS development environment (7.0+)
- PHP 7.4 or higher
- MySQL/MariaDB database
- bKash sandbox credentials for testing
- Basic knowledge of WHMCS module development
- Understanding of REST APIs and PHP

### Development Tools

Recommended tools for development:
- **IDE**: PHPStorm, VS Code, or similar
- **Version Control**: Git
- **Testing**: PHPUnit (optional)
- **Code Quality**: PHP_CodeSniffer, PHPStan
- **API Testing**: Postman or similar

## How to Contribute

### Types of Contributions

We welcome various types of contributions:

1. **Bug Reports**: Help us identify and fix issues
2. **Feature Requests**: Suggest new features or improvements
3. **Code Contributions**: Submit bug fixes or new features
4. **Documentation**: Improve or add documentation
5. **Testing**: Help test new features or bug fixes
6. **Translations**: Add support for new languages

### Getting Started with Code

1. **Fork the Repository**
   ```bash
   git clone https://github.com/yourusername/prothom-pay-bkash-gateway.git
   cd prothom-pay-bkash-gateway
   ```

2. **Create a Development Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b bugfix/issue-description
   ```

3. **Make Your Changes**
   - Follow the code guidelines
   - Add appropriate tests
   - Update documentation if needed

4. **Test Your Changes**
   - Test in sandbox environment
   - Verify all existing functionality works
   - Add new tests if applicable

## Development Setup

### Local Environment Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/prothom-pay-bkash-gateway.git
   ```

2. **Install Dependencies**
   ```bash
   composer install  # If using Composer
   ```

3. **Configure Development Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your development settings
   ```

4. **Set Up WHMCS**
   - Install WHMCS in your development environment
   - Copy gateway files to appropriate directories
   - Configure sandbox credentials

### Directory Structure

```
prothom-pay-bkash-gateway/
├── prothompay.php              # Main gateway file
├── lib/                        # Library files
│   ├── ProthomPayClient.php    # API client
│   ├── ProthomPayLogger.php    # Logging utility
│   └── WowGatewayLogger.php    # Additional logger
├── callback/                   # Callback handlers
│   └── prothompay.php          # Payment callback
├── docs/                       # Documentation
├── tests/                      # Test files (if any)
├── README.md                   # Main documentation
├── CHANGELOG.md               # Version history
├── LICENSE                    # License file
└── CONTRIBUTING.md            # This file
```

## Code Guidelines

### PHP Coding Standards

Follow PSR-12 coding standards:

```php
<?php
// Use proper namespacing
namespace ProthomPay\Gateway;

// Class naming: PascalCase
class ProthomPayClient
{
    // Properties: camelCase
    private $apiCredentials;
    
    // Methods: camelCase
    public function createPayment($amount, $invoiceId)
    {
        // Method implementation
    }
}
```

### Documentation Standards

- Use PHPDoc for all classes and methods
- Include parameter types and return types
- Add meaningful descriptions

```php
/**
 * Create a payment request with bKash API
 * 
 * @param float $amount Payment amount in BDT
 * @param string $invoiceId WHMCS invoice ID
 * @param string $callbackUrl Callback URL for payment notification
 * @return array Payment creation response
 * @throws Exception On payment creation failure
 */
public function createPayment($amount, $invoiceId, $callbackUrl)
{
    // Implementation
}
```

### Error Handling

- Use try-catch blocks for external API calls
- Provide meaningful error messages
- Log errors appropriately

```php
try {
    $response = $this->makeApiCall($endpoint, $data);
    return $response;
} catch (Exception $e) {
    $this->logger->log('API call failed: ' . $e->getMessage());
    throw new Exception('Payment processing failed: ' . $e->getMessage());
}
```

### Security Guidelines

- Sanitize all user inputs
- Use parameterized queries
- Validate API responses
- Never expose sensitive data in logs

```php
// Input sanitization
$paymentId = filter_var($input['paymentID'], FILTER_SANITIZE_STRING);

// Secure logging (don't log sensitive data)
$this->logger->log('Payment created', [
    'invoice_id' => $invoiceId,
    'amount' => $amount,
    // Don't log: credentials, tokens, personal info
]);
```

## Testing

### Testing Guidelines

1. **Unit Tests**: Test individual methods and functions
2. **Integration Tests**: Test API interactions
3. **End-to-End Tests**: Test complete payment flow
4. **Manual Testing**: Test in sandbox environment

### Test Structure

```php
<?php
use PHPUnit\Framework\TestCase;

class ProthomPayClientTest extends TestCase
{
    private $client;
    
    protected function setUp(): void
    {
        $this->client = new ProthomPayClient(
            'test_username',
            'test_password',
            'test_app_key',
            'test_app_secret',
            'https://sandbox.bkash.com/api',
            $this->createMock(ProthomPayLogger::class)
        );
    }
    
    public function testCreatePaymentSuccess()
    {
        // Test implementation
    }
}
```

### Running Tests

```bash
# Run all tests
phpunit tests/

# Run specific test file
phpunit tests/ProthomPayClientTest.php

# Run with coverage
phpunit --coverage-html coverage/
```

## Submitting Changes

### Pull Request Process

1. **Update Documentation**
   - Update README if needed
   - Add changelog entry
   - Update API documentation

2. **Create Pull Request**
   - Use descriptive title
   - Include detailed description
   - Reference related issues
   - Add screenshots if applicable

3. **Pull Request Template**
   ```markdown
   ## Description
   Brief description of changes
   
   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Documentation update
   - [ ] Performance improvement
   
   ## Testing
   - [ ] Tested in sandbox environment
   - [ ] All existing tests pass
   - [ ] New tests added (if applicable)
   
   ## Screenshots
   (If applicable)
   
   ## Related Issues
   Closes #123
   ```

### Code Review Process

1. **Automated Checks**
   - Code style validation
   - Basic functionality tests
   - Security scans

2. **Manual Review**
   - Code quality assessment
   - Security review
   - Documentation review

3. **Testing**
   - Functional testing
   - Integration testing
   - Performance testing

## Reporting Issues

### Bug Reports

When reporting bugs, include:

1. **Environment Information**
   - WHMCS version
   - PHP version
   - Web server details
   - Operating system

2. **Steps to Reproduce**
   - Detailed steps
   - Expected behavior
   - Actual behavior

3. **Additional Information**
   - Error messages
   - Log excerpts
   - Screenshots
   - Configuration details (without sensitive data)

### Issue Template

```markdown
## Bug Report

### Environment
- WHMCS Version: 8.5.0
- PHP Version: 7.4.28
- Web Server: Apache 2.4.41
- OS: Ubuntu 20.04

### Description
Brief description of the issue

### Steps to Reproduce
1. Step 1
2. Step 2
3. Step 3

### Expected Behavior
What should happen

### Actual Behavior
What actually happens

### Additional Information
- Error messages
- Log excerpts
- Screenshots
```

## Feature Requests

### Submitting Feature Requests

1. **Check Existing Requests**
   - Search for similar requests
   - Check planned features in roadmap

2. **Provide Detailed Description**
   - Use case description
   - Expected behavior
   - Benefits and impact

3. **Consider Implementation**
   - Technical feasibility
   - Compatibility concerns
   - Performance implications

### Feature Request Template

```markdown
## Feature Request

### Summary
Brief description of the feature

### Use Case
Why is this feature needed?

### Proposed Solution
How should this feature work?

### Alternative Solutions
Any alternative approaches considered?

### Additional Context
Any additional information
```

## Code of Conduct

### Our Standards

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive criticism
- Respect different viewpoints
- Maintain professional communication

### Unacceptable Behavior

- Harassment or discrimination
- Trolling or insulting comments
- Personal attacks
- Publishing private information
- Unprofessional conduct

### Enforcement

Violations of the code of conduct should be reported to:
- **Email**: mehedihasan2002.myc@gmail.com
- **Subject**: Code of Conduct Violation

## Development Guidelines

### Version Control

- Use meaningful commit messages
- Make small, focused commits
- Reference issues in commit messages
- Use conventional commit format

```bash
# Commit message format
type(scope): description

# Examples
feat(auth): add token refresh mechanism
fix(callback): resolve payment verification issue
docs(api): update authentication documentation
```

### Branch Naming

- `feature/feature-name` for new features
- `bugfix/issue-description` for bug fixes
- `hotfix/critical-issue` for urgent fixes
- `docs/documentation-update` for documentation

### Release Process

1. **Version Bump**
   - Update version numbers
   - Update changelog
   - Create release branch

2. **Testing**
   - Comprehensive testing
   - Performance testing
   - Security testing

3. **Release**
   - Create release tag
   - Publish release notes
   - Update documentation

## Support

### Getting Help

- **Developer Contact**: Mehedi Hasan
- **Email**: mehedihasan2002.myc@gmail.com
- **Phone**: +8801601300220

### Community Resources

- **Documentation**: Check the docs/ folder
- **Issues**: GitHub issues for bug reports
- **Discussions**: GitHub discussions for questions

### Response Times

- **Bug Reports**: 24-48 hours
- **Feature Requests**: 48-72 hours
- **Pull Requests**: 24-48 hours
- **General Questions**: 24-72 hours

## Recognition

### Contributors

We recognize and appreciate all contributors:
- Code contributors
- Documentation contributors
- Testers and bug reporters
- Community supporters

### Attribution

Contributors will be:
- Listed in release notes
- Mentioned in documentation
- Credited in the project

## Legal

### License

By contributing, you agree that your contributions will be licensed under the MIT License.

### Copyright

- You retain copyright of your contributions
- You grant permission to use your contributions
- You confirm you have the right to grant this permission

---

**Thank you for contributing to Prothom Pay bKash Gateway!**

Your contributions help improve the payment experience for WHMCS users in Bangladesh and beyond.

For questions about contributing, please contact:
- **Developer**: Mehedi Hasan
- **Email**: mehedihasan2002.myc@gmail.com
- **Phone**: +8801601300220