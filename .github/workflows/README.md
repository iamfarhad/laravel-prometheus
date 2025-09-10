# GitHub Actions Workflows

This directory contains comprehensive CI/CD workflows for the Laravel Prometheus package, built following modern security and best practices.

## 🚀 Overview

Our workflow setup provides:
- **Comprehensive CI/CD** with security scanning
- **Automated releases** with proper versioning
- **Security auditing** and vulnerability management
- **Automated maintenance** and dependency updates

## 📋 Workflows

### 1. `ci.yml` - Comprehensive CI Pipeline ⭐ **PRIMARY**

**Triggers:** Push to `main`/`develop`, Pull Requests (excluding docs)

**Features:**
- **Smart Change Detection**: Only runs relevant jobs based on file changes
- **Code Style Enforcement**: Laravel Pint with auto-fix on push, fail on PR
- **Security Scanning**: CodeQL analysis and Composer security audit
- **Static Analysis**: PHPStan with caching for performance
- **Matrix Testing**: PHP 8.2-8.4 × Laravel 10-12 with Redis integration
- **Build Validation**: Production build testing and package structure validation
- **Concurrency Control**: Cancels outdated runs automatically

**Security Measures:**
- Pinned action versions with SHA hashes
- Minimal permissions (principle of least privilege)
- Dependency review for PRs
- Automated security scanning

### 2. `release.yml` - Automated Release Management

**Triggers:** Git tags (`v*.*.*`) or manual dispatch

**Features:**
- **Version Validation**: Semantic versioning enforcement
- **Full CI Integration**: Runs complete CI before release
- **Release Assets**: Automated archive creation with checksums
- **GitHub Releases**: Auto-generated release notes with changelog
- **Build Provenance**: Cryptographic attestation of build artifacts
- **Packagist Integration**: Ready for automated package publishing

**Security Measures:**
- Build artifact attestation
- Checksum generation and verification
- Secure release asset handling

### 3. `security.yml` - Security Audit & Monitoring

**Triggers:** Schedule (Monday 6 AM), Dependency changes, Manual

**Features:**
- **Composer Security Audit**: Vulnerability scanning with automated issues
- **CodeQL Analysis**: Advanced security and quality analysis
- **Dependency Scanning**: Trivy and Snyk integration
- **Secrets Detection**: TruffleHog and GitLeaks scanning
- **License Compliance**: Automated license checking
- **Security Reporting**: PR comments and issue creation

**Automated Actions:**
- Creates security issues for vulnerabilities
- Comments on PRs with security status
- Maintains security artifact history

### 4. `maintenance.yml` - Automated Maintenance

**Triggers:** Schedule (Sunday 3 AM), Manual with options

**Features:**
- **Dependency Management**: Automated dev dependency updates
- **Performance Monitoring**: Cross-version performance benchmarks
- **Compatibility Testing**: Laravel/PHP compatibility matrix
- **Documentation Auditing**: Completeness and structure checks
- **Repository Cleanup**: Large file and temporary file detection

**Automated Actions:**
- Creates PRs for dependency updates
- Generates performance reports
- Creates maintenance issues for failures

## 🔒 Security Features

### Action Security
- **Pinned Versions**: All actions use SHA-pinned versions
- **Minimal Permissions**: Each job has only required permissions
- **Dependency Review**: Automated review of dependency changes
- **Secrets Scanning**: Multi-tool secret detection

### Supply Chain Security
- **Build Attestation**: Cryptographic proof of build integrity
- **Checksum Verification**: SHA256/SHA512 checksums for releases
- **License Compliance**: Automated license violation detection
- **Vulnerability Scanning**: Multiple tools for comprehensive coverage

## 🏃‍♂️ Performance Optimizations

### Caching Strategy
- **Composer Dependencies**: Smart cache invalidation
- **PHPStan Results**: Persistent analysis cache
- **Action Dependencies**: Version-specific caching

### Parallelization
- **Matrix Builds**: Parallel testing across versions
- **Job Dependencies**: Optimal job scheduling
- **Conditional Execution**: Smart job skipping based on changes

### Resource Management
- **Timeouts**: Prevent runaway jobs
- **Concurrency Control**: Prevent resource conflicts
- **Artifact Cleanup**: Automatic cleanup with retention policies

## 🛠️ Local Development

Run the same checks locally:

```bash
# Code formatting
composer format
./vendor/bin/pint

# Static analysis
composer analyse
./vendor/bin/phpstan analyse

# Security audit
composer audit

# Tests with coverage
composer test
./vendor/bin/phpunit --coverage-html coverage

# All checks (CI simulation)
composer ci
```

## 🔧 Configuration

### Required Secrets
- `GITHUB_TOKEN`: Automatically provided
- `CODECOV_TOKEN`: For coverage reporting (optional)
- `SNYK_TOKEN`: For Snyk security scanning (optional)
- `GITLEAKS_LICENSE`: For GitLeaks Pro features (optional)

### Branch Protection
Recommended branch protection settings:
- Require status checks: `ci`, `security`
- Require up-to-date branches
- Require signed commits
- Include administrators
- Restrict force pushes

### Repository Settings
- Enable dependency graph
- Enable Dependabot alerts
- Enable secret scanning
- Enable code scanning (CodeQL)

## 📊 Monitoring & Reporting

### Artifacts
- **Coverage Reports**: Uploaded to Codecov
- **Security Scans**: Available in Security tab
- **Performance Data**: 30-day retention
- **License Reports**: Compliance tracking

### Notifications
- **Security Issues**: Auto-created for vulnerabilities
- **Maintenance PRs**: Automated dependency updates
- **Release Notes**: Auto-generated from git history

### Dashboards
- **Actions Tab**: Workflow run history
- **Security Tab**: Vulnerability alerts
- **Insights**: Repository analytics

## 🚀 Release Process

### Automatic Release (Recommended)
1. Push changes to `main`
2. Create and push tag: `git tag v1.2.3 && git push origin v1.2.3`
3. Release workflow automatically triggers
4. GitHub release created with assets
5. Packagist automatically updates

### Manual Release
1. Go to Actions → Release workflow
2. Click "Run workflow"
3. Enter version (e.g., `v1.2.3`)
4. Workflow creates tag and release

### Version Scheme
- **Major**: Breaking changes (`v2.0.0`)
- **Minor**: New features (`v1.1.0`)
- **Patch**: Bug fixes (`v1.0.1`)
- **Prerelease**: Beta versions (`v1.0.0-beta.1`)

## 🤝 Contributing

### Workflow Contributions
- Follow security best practices
- Pin action versions to SHA hashes
- Add comprehensive error handling
- Document changes in this README

### Testing Workflows
- Use workflow dispatch for testing
- Validate on feature branches
- Check security implications
- Monitor resource usage

## 📚 Best Practices Implemented

### GitHub Actions Best Practices
- ✅ Pinned action versions with SHA hashes
- ✅ Minimal permissions per job
- ✅ Proper error handling and timeouts
- ✅ Concurrency control and cancellation
- ✅ Smart caching strategies
- ✅ Matrix builds for compatibility
- ✅ Conditional job execution

### Security Best Practices
- ✅ Multi-tool security scanning
- ✅ Automated vulnerability management
- ✅ Secrets detection and prevention
- ✅ License compliance checking
- ✅ Build artifact attestation
- ✅ Supply chain security

### CI/CD Best Practices
- ✅ Fast feedback loops
- ✅ Comprehensive test coverage
- ✅ Automated releases
- ✅ Proper versioning
- ✅ Documentation automation
- ✅ Performance monitoring

## 🆘 Troubleshooting

### Common Issues

**Workflow Failing on Dependencies**
```bash
# Clear composer cache
composer clear-cache
rm -rf vendor/ composer.lock
composer install
```

**Security Scan False Positives**
- Review security alerts in repository Security tab
- Update dependencies if needed
- Use `.gitleaksignore` for false positive secrets

**Performance Issues**
- Check workflow run times in Actions tab
- Review cache hit rates
- Consider reducing matrix size for faster feedback

### Getting Help
- Check workflow logs in Actions tab
- Review this documentation
- Open issue with `workflow` label
- Check GitHub Actions documentation

---

*Last updated: $(date -u '+%Y-%m-%d')*
*Workflow version: 2.0*