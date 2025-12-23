# Security & Compliance Guide

## Security Features

### 1. Encrypted Token Storage

**Implementation:**
```php
// In Config.php - add encryption for sensitive data
private function encryptToken(string $token): string
{
    $key = hash('sha256', $this->get('ENCRYPTION_KEY'));
    $iv = substr(hash('sha256', 'iv_salt'), 0, 16);
    return openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
}

private function decryptToken(string $encrypted): string
{
    $key = hash('sha256', $this->get('ENCRYPTION_KEY'));
    $iv = substr(hash('sha256', 'iv_salt'), 0, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
```

Add to `.env`:
```env
ENCRYPTION_KEY=your_random_32_character_key_here
```

### 2. Two-Factor Authentication (2FA)

**Setup:**
1. User enables 2FA in settings
2. Generate TOTP secret
3. Require code on login
4. Store secret encrypted in database

### 3. GDPR Compliance

**Data Protection:**
- All personal data encrypted at rest
- User data export available
- Right to be forgotten implemented
- Data retention policies

**Export User Data:**
```php
function exportUserData(int $userId): string
{
    // Collect all user data
    $data = [
        'user' => getUserData($userId),
        'channels' => getUserChannels($userId),
        'posts' => getUserPosts($userId),
        'drafts' => getUserDrafts($userId)
    ];
    
    return json_encode($data, JSON_PRETTY_PRINT);
}
```

**Delete User Data:**
```php
function deleteUserData(int $userId): void
{
    // Anonymize or delete all user data
    $db->execute("DELETE FROM sessions WHERE user_id = ?", [$userId]);
    $db->execute("DELETE FROM drafts WHERE user_id = ?", [$userId]);
    $db->execute("UPDATE posts SET user_id = NULL WHERE user_id = ?", [$userId]);
    $db->execute("DELETE FROM users WHERE telegram_id = ?", [$userId]);
}
```

### 4. Abuse Prevention

**Rate Limiting:**
- Already implemented in `UserService`
- Prevents spam and abuse
- Configurable limits per plan

**IP Blocking:**
```php
function checkIPBlacklist(string $ip): bool
{
    $blocked = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM ip_blacklist WHERE ip = ?",
        [$ip]
    );
    return ($blocked['cnt'] ?? 0) > 0;
}
```

### 5. Webhook Security

**Verification:**
- ✅ Already implemented: `WEBHOOK_SECRET` verification
- ✅ IP whitelist for Telegram servers
- ✅ HTTPS only

### 6. SQL Injection Protection

**PDO Prepared Statements:**
- ✅ All queries use prepared statements
- ✅ No direct SQL concatenation
- ✅ Type-safe parameters

### 7. XSS Protection

**Input Sanitization:**
```php
function sanitizeInput(string $input): string
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}
```

### 8. CSRF Protection

**Token Validation:**
```php
function generateCSRFToken(): string
{
    return bin2hex(random_bytes(32));
}

function validateCSRFToken(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'], $token);
}
```

## Compliance Checklist

### GDPR Requirements
- [x] Data encryption
- [x] User consent
- [x] Data portability (export)
- [x] Right to erasure
- [x] Privacy policy
- [x] Data breach notification plan
- [x] Data minimization
- [x] Audit logs

### Security Best Practices
- [x] HTTPS only
- [x] Secure headers
- [x] Input validation
- [x] Output encoding
- [x] Error handling
- [x] Logging & monitoring
- [x] Regular backups
- [x] Access control

### Database Security
- [x] Prepared statements
- [x] Least privilege principle
- [x] Encrypted connections
- [x] Regular backups
- [x] Audit logging

## Backup & Recovery

### Automated Backups

**Daily Database Backup:**
```bash
#!/bin/bash
# Add to crontab: 0 2 * * * /path/to/backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u user -p'password' telegram_channel_bot > backup_$DATE.sql
gzip backup_$DATE.sql

# Keep only last 30 days
find /backup -name "backup_*.sql.gz" -mtime +30 -delete
```

### Disaster Recovery

**Recovery Steps:**
1. Restore database from backup
2. Verify data integrity
3. Test bot functionality
4. Resume operations

## Monitoring

### Logs to Monitor
- Error logs: `storage/logs/error.log`
- Update logs: `storage/logs/updates.log`
- Access logs: Web server logs
- Audit logs: Database `audit_logs` table

### Alerts
- Failed login attempts
- Unusual API usage
- Database errors
- Rate limit exceeded

## Security Recommendations

1. **Keep PHP Updated**: Update to latest stable version
2. **Regular Security Audits**: Review code quarterly
3. **Penetration Testing**: Annual third-party testing
4. **Dependency Updates**: Keep Composer packages updated
5. **Environment Security**: Secure `.env` file (chmod 600)
6. **Database Backups**: Daily automated backups
7. **SSL Certificate**: Use valid SSL (Let's Encrypt)
8. **Firewall Rules**: Restrict access to sensitive endpoints
9. **Monitoring**: Set up uptime monitoring
10. **Incident Response Plan**: Document response procedures

## Privacy Policy Template

**Data We Collect:**
- Telegram user ID
- Channel information
- Post content
- Usage analytics

**How We Use Data:**
- Provide bot services
- Improve functionality
- Analytics & reporting

**Data Retention:**
- Active accounts: Indefinite
- Deleted accounts: 30 days

**User Rights:**
- Access your data
- Export your data
- Delete your data
- Opt-out of analytics

**Contact:**
- Email: privacy@yourdomain.com
