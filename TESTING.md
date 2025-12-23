# Testing Guide

## Running Tests

### Install Dependencies
```bash
composer install
```

### Run All Tests
```bash
./vendor/bin/phpunit
```

### Run Specific Test
```bash
./vendor/bin/phpunit tests/Core/ConfigTest.php
```

### Run with Coverage
```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Test Structure

```
tests/
├── Core/
│   ├── ConfigTest.php
│   └── ContainerTest.php
├── Services/
│   └── (add service tests here)
└── Modules/
    └── (add module tests here)
```

## Writing Tests

### Example Service Test
```php
<?php

use PHPUnit\Framework\TestCase;
use App\Services\UserService;

class UserServiceTest extends TestCase
{
    public function testCreateUser()
    {
        // Test implementation
    }
}
```

## Manual Testing Checklist

### Core Functionality
- [ ] Bot responds to /start
- [ ] Channel detection works
- [ ] Posts publish successfully
- [ ] Drafts save and load
- [ ] Scheduled posts execute

### Content Management
- [ ] Text posts work
- [ ] Photo posts work
- [ ] Video posts work
- [ ] Document posts work
- [ ] Media albums work
- [ ] Polls work

### Advanced Features
- [ ] Edit posts works
- [ ] Pin/unpin works
- [ ] RSS feeds auto-post
- [ ] Campaigns track posts
- [ ] Approvals function
- [ ] Backups create

### Security
- [ ] Rate limiting prevents spam
- [ ] RBAC enforces permissions
- [ ] Webhook secret validates
- [ ] Multi-tenant isolation works

## Integration Testing

### Setup Test Environment
```bash
# Copy .env for testing
cp .env .env.testing

# Set test database
DB_DATABASE=telegram_test
```

### Test Bot Commands
1. Send `/start` to bot
2. Add bot to test channel
3. Try creating posts
4. Test scheduling
5. Verify analytics

## Performance Testing

### Load Test Cron
```bash
# Simulate multiple scheduled posts
ab -n 100 -c 10 https://yourdomain.com/webhook.php?cron=1
```

### Database Performance
```sql
-- Check query performance
EXPLAIN SELECT * FROM posts WHERE channel_id = 1;
```

## Continuous Integration

### GitHub Actions Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/composer@v6
      - run: ./vendor/bin/phpunit
```

## Test Coverage Goals

- **Core**: 80%+
- **Services**: 70%+
- **Modules**: 60%+
- **Overall**: 70%+
