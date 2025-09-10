# Security Policy

## Supported Versions

We actively support the following versions of Laravel Prometheus with security updates:

| Version | Supported          | Laravel Versions | PHP Versions |
| ------- | ------------------ | ---------------- | ------------ |
| 1.x     | ✅ Active support  | 10.x, 11.x, 12.x | 8.2, 8.3, 8.4 |
| 0.x     | ❌ End of life     | 9.x, 10.x        | 8.1, 8.2     |

## Reporting a Vulnerability

We take security seriously and appreciate your help in keeping Laravel Prometheus secure.

### 🚨 For Critical Vulnerabilities

**Please DO NOT report critical security vulnerabilities through public GitHub issues.**

For serious security vulnerabilities, please email us directly at:
📧 **farhad.pd@gmail.com**

Include the following information:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We will acknowledge receipt within 24 hours and provide a detailed response within 72 hours.

### 🔍 For Non-Critical Security Issues

For less critical security issues, you can:
1. Create a private security advisory on GitHub
2. Use our security vulnerability issue template
3. Email us at the address above

## Security Response Process

### Timeline
- **24 hours**: Acknowledgment of report
- **72 hours**: Initial assessment and response
- **7 days**: Regular updates on progress
- **30 days**: Target resolution time

### Our Process
1. **Acknowledge** receipt of vulnerability report
2. **Assess** the severity and impact
3. **Develop** and test a fix
4. **Coordinate** disclosure with reporter
5. **Release** patched version
6. **Publish** security advisory

## Security Measures

### Code Security
- ✅ Regular security audits via GitHub CodeQL
- ✅ Dependency vulnerability scanning
- ✅ Automated security testing in CI/CD
- ✅ Static analysis with PHPStan

### Supply Chain Security
- ✅ Pinned GitHub Actions versions
- ✅ Dependabot security updates
- ✅ Package integrity verification
- ✅ License compliance checking

### Runtime Security
- ✅ Input validation and sanitization
- ✅ Secure default configurations
- ✅ Minimal privilege principles
- ✅ Safe error handling

## Security Best Practices

### For Package Users

#### Installation
```bash
# Always use specific versions in production
composer require iamfarhad/laravel-prometheus:^1.0

# Verify package integrity
composer audit
```

#### Configuration
```php
// Use secure Redis configurations
'storage' => [
    'adapter' => 'redis',
    'connection' => 'default',
    'key_namespace' => 'prometheus_',
    'auth' => env('REDIS_PASSWORD'), // Use authentication
],

// Restrict metrics endpoint access
'route' => [
    'middleware' => ['auth', 'verified'], // Add authentication
    'prefix' => 'internal', // Use internal prefix
],
```

#### Access Control
```php
// In routes/web.php or api.php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/metrics', [PrometheusController::class, 'metrics']);
});
```

### For Developers

#### Secure Development
- Always validate and sanitize input data
- Use parameterized queries for database operations
- Implement proper error handling without information disclosure
- Follow Laravel security best practices

#### Testing
- Write security-focused tests
- Test with invalid/malicious input
- Verify authorization checks
- Test error conditions

## Known Security Considerations

### Metrics Exposure
- **Risk**: Metrics may contain sensitive information
- **Mitigation**: Use proper access controls and data sanitization
- **Configuration**: Restrict metrics endpoint access

### Redis Storage
- **Risk**: Unprotected Redis instances
- **Mitigation**: Use Redis authentication and network restrictions
- **Configuration**: Set proper Redis passwords and firewall rules

### Performance Impact
- **Risk**: Resource exhaustion through metric collection
- **Mitigation**: Rate limiting and resource monitoring
- **Configuration**: Set appropriate collection limits

## Security Updates

### Notification Channels
- GitHub Security Advisories
- Release notes with security tags
- Email notifications for critical issues

### Update Recommendations
- Enable Dependabot for automated updates
- Subscribe to security advisories
- Regularly run `composer audit`
- Monitor our releases for security patches

## Responsible Disclosure

We believe in responsible disclosure and work with security researchers to:
- Provide reasonable time to fix vulnerabilities
- Coordinate public disclosure timing
- Credit researchers in security advisories (with permission)
- Maintain transparency about security issues

### Recognition
We appreciate security researchers who help keep Laravel Prometheus secure:
- Public recognition in security advisories
- Hall of fame listing (if desired)
- Potential bug bounty (case by case basis)

## Contact Information

### Security Team
- **Primary Contact**: Farhad Zand (farhad.pd@gmail.com)
- **GitHub**: @iamfarhad

### Response Expectations
- **Business Hours**: 9 AM - 5 PM UTC, Monday - Friday
- **Emergency Response**: 24/7 for critical vulnerabilities
- **Languages**: English, Persian/Farsi

## Legal

### Safe Harbor
We support safe harbor for security researchers who:
- Report vulnerabilities responsibly
- Follow our disclosure guidelines
- Do not access or modify user data
- Do not cause service disruption

### Scope
This security policy applies to:
- Laravel Prometheus package core functionality
- Official documentation and examples
- Build and deployment infrastructure

Out of scope:
- Third-party dependencies (report to respective maintainers)
- User application configurations
- Infrastructure not under our control

---

**Last Updated**: December 2024  
**Policy Version**: 1.0

For the most current version of this policy, please check our GitHub repository.
