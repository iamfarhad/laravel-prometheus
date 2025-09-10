# GitHub Actions Workflows

This directory contains the CI/CD workflows for the Laravel Prometheus package.

## Workflows

### 1. `ci.yml` - Comprehensive CI Pipeline ⭐ **RECOMMENDED**
**Triggers:** Push to `main`/`develop`, Pull Requests

**Jobs:**
- **Code Style (Pint)**: Automatically fixes code style on push, fails PRs with style issues
- **Static Analysis (PHPStan)**: Runs static analysis to catch potential bugs
- **Tests**: Runs tests across multiple PHP/Laravel versions with fallback for compatibility issues
- **Coverage**: Generates coverage reports (main branch only)

### 2. `code-style.yml` - Standalone Code Style Check
**Triggers:** Push to `main`/`develop`, Pull Requests

Runs Laravel Pint to ensure consistent code formatting. Use this if you only want style checks without full CI.

## Local Development

Run the same checks locally:

```bash
# Code formatting
composer format

# Static analysis
composer analyse

# Tests
composer test

# All checks
composer ci
```

## Notes

- Tests include fallback logic for Laravel 12 compatibility issues
- Core functionality tests (Storage, Metrics, Prometheus) are prioritized
- Memory limits are increased for test environments
- Redis is set up for integration tests
- Coverage reports are only generated on main branch pushes
