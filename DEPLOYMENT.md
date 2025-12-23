# 🚀 Deployment Checklist

## Before Going Live

### 1. Environment Setup ✅

- [x] `.env` file created and configured
- [x] `BOT_TOKEN` set from @BotFather
- [x] `WEBHOOK_URL` configured (must be HTTPS)
- [x] MySQL database created
- [x] Database credentials configured

### 2. MySQL Database Setup

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE telegram_channel_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (optional, recommended for production)
CREATE USER 'telegram_bot'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON telegram_channel_bot.* TO 'telegram_bot'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_channel_bot
DB_USERNAME=telegram_bot
DB_PASSWORD=strong_password_here
```

### 3. Composer Dependencies

```bash
cd c:\xampp\htdocs\bot\telegram-manage-channel-bot
composer install --no-dev --optimize-autoloader
```

### 4. File Permissions (Linux/Mac)

```bash
chmod -R 755 .
chmod -R 775 storage/
chown -R www-data:www-data .
```

Windows with XAMPP: No action needed.

### 5. Set Webhook

Visit in browser:
```
https://yourdomain.com/webhook.php?setup=1
```

Expected output:
```
✅ Webhook set successfully!

Bot: @YourBotUsername
Webhook URL: https://yourdomain.com/webhook.php

Your bot is now ready to receive updates!
```

### 6. Test the Bot

1. Open Telegram, find your bot
2. Send `/start` - you should see the dashboard
3. Add bot as admin to a test channel
4. Bot should notify you
5. Send `/start` again - your channel should appear
6. Try posting content

### 7. Setup Cron (for Scheduled Posts)

**Linux/Mac crontab:**
```bash
crontab -e

# Add this line:
* * * * * curl -fsS "https://yourdomain.com/webhook.php?cron=1" > /dev/null 2>&1
```

**Windows Task Scheduler:**
- Create new task
- Trigger: Every minute
- Action: Start program
- Program: `C:\Windows\System32\curl.exe`
- Arguments: `-fsS "https://yourdomain.com/webhook.php?cron=1"`

**Or use external cron service:**
- [cron-job.org](https://cron-job.org)
- Schedule: Every minute
- URL: `https://yourdomain.com/webhook.php?cron=1`

### 8. Security Hardening (Production)

**1. Add webhook secret:**
`.env`:
```env
WEBHOOK_SECRET=your_random_secret_here_use_long_string
```

**2. Add cron secret:**
`.env`:
```env
CRON_SECRET=another_random_secret
```

Update cron URL:
```
https://yourdomain.com/webhook.php?cron=1&secret=another_random_secret
```

**3. Restrict access to webhook.php:**

Add to `.htaccess` (if using Apache):
```apache
<Files "webhook.php">
    # Allow only from Telegram IPs (example)
    Require ip 149.154.160.0/20
    Require ip 91.108.4.0/22
</Files>
```

**4. Enable HTTPS only:**
Ensure your server forces HTTPS redirect.

**5. Set admin users:**
`.env`:
```env
ADMIN_IDS=123456789,987654321
```

### 9. Monitoring

**Check logs:**
```bash
tail -f storage/logs/error.log
tail -f storage/logs/updates.log  # When debug is on
```

**Check webhook status:**
```
https://yourdomain.com/webhook.php?info
```

**Enable debug mode (development only):**
`.env`:
```env
APP_DEBUG=true
```

⚠️ **Disable debug in production!**

### 10. Backup Strategy

**Database backup (daily):**
```bash
# Add to crontab
0 2 * * * mysqldump -u telegram_bot -p'password' telegram_channel_bot > /backup/telegram_bot_$(date +\%Y\%m\%d).sql
```

**Code backup:**
- Use Git for version control
- Keep `.env` in `.gitignore`
- Store `.env` securely separately

## Common Issues & Solutions

### Webhook not working
- ✅ Check URL is HTTPS
- ✅ Verify SSL certificate is valid
- ✅ Check `BOT_TOKEN` is correct
- ✅ Ensure webhook.php is accessible publicly

### Database connection failed
- ✅ Verify MySQL is running
- ✅ Check credentials in `.env`
- ✅ Ensure database exists
- ✅ Check user has permissions

### Bot doesn't respond
- ✅ Check error logs: `storage/logs/error.log`
- ✅ Enable debug mode temporarily
- ✅ Verify webhook is set: `webhook.php?info`
- ✅ Check Telegram API status

### Scheduled posts not working
- ✅ Verify cron is running
- ✅ Check cron secret matches
- ✅ Test cron manually: visit `webhook.php?cron=1&secret=...`
- ✅ Check error logs

### Posts not publishing
- ✅ Ensure bot is admin in channel
- ✅ Check bot has "Post messages" permission
- ✅ Verify channel ID is correct

## Performance Optimization

### 1. Enable OPcache (PHP)

`php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # Production only
```

### 2. MySQL Optimization

```sql
-- Add indexes if needed (already done in migrations)
-- Enable query cache (if MySQL < 8.0)
SET GLOBAL query_cache_size = 268435456;
SET GLOBAL query_cache_type = ON;
```

### 3. Caching

The bot includes built-in permission caching. No additional setup needed.

## Scaling Considerations

**For high-traffic bots (1000+ channels):**

1. **Use connection pooling**
2. **Add Redis for caching**
3. **Use queue service for scheduled posts (e.g., Laravel Queue, RabbitMQ)**
4. **Consider database replication**
5. **Use CDN for media**
6. **Horizontal scaling with load balancer**

## Production Checklist

- [ ] `.env` configured with production values
- [ ] Database created and credentials set
- [ ] Composer dependencies installed
- [ ] Webhook set successfully
- [ ] Tested posting to channel
- [ ] Cron job configured
- [ ] Logs directory writable
- [ ] Debug mode disabled (`APP_DEBUG=false`)
- [ ] Webhook secret configured
- [ ] HTTPS enforced
- [ ] Backup strategy in place
- [ ] Monitoring set up
- [ ] Error alerting configured

## Support

Need help? Check:
- 📖 README.md
- 📋 SETUP.md
- 🐛 Error logs
- 💬 GitHub Issues

---

**Ready to go live? Good luck! 🚀**
