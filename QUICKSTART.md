# ⚡ Quick Start Guide

## 🎯 Get Your Bot Running in 5 Minutes

### Step 1: Configure `.env`

```bash
cp .env.example .env
```

Edit `.env`:
```env
BOT_TOKEN=YOUR_BOT_TOKEN_FROM_BOTFATHER
WEBHOOK_URL=https://yourdomain.com/webhook.php

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_channel_bot
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 2: Create Database

```bash
mysql -u root -p -e "CREATE DATABASE telegram_channel_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Step 3: Install Dependencies

```bash
composer install
```

### Step 4: Set Webhook

Visit: `https://yourdomain.com/webhook.php?setup=1`

You should see: ✅ Webhook set successfully!

### Step 5: Test It!

1. Find your bot on Telegram
2. Send `/start`
3. Add bot as admin to a channel
4. Select channel and create a post!

## 🎨 What You Can Do

### ✍️ Create Posts
1. Select channel
2. Click "✍️ New Post"
3. Send text, photo, video, or document
4. Post published instantly!

### 📝 Save Drafts
1. Select channel
2. Click "📝 Drafts"
3. Click "➕ New Draft"
4. Send content
5. Publish later or delete

### ⏰ Schedule Posts
1. Select channel  
2. Click "⏰ Schedule"
3. Send content
4. Choose when to post
5. Done! Auto-posts at scheduled time

### 📊 View Analytics
1. Select channel
2. Click "📊 Analytics"
3. See subscribers, posts, top content

### 🔧 Configure Settings
1. Select channel
2. Click "🔧 Settings"
3. Toggle auto-pin, approvals, etc.

## 📅 Setup Cron (For Scheduled Posts)

**Option A: Linux/Mac**
```bash
crontab -e
# Add:
* * * * * curl -fsS "https://yourdomain.com/webhook.php?cron=1" > /dev/null 2>&1
```

**Option B: Windows Task Scheduler**
- Create task to run every minute
- Action: `curl -fsS "https://yourdomain.com/webhook.php?cron=1"`

**Option C: Online Service**
- Use [cron-job.org](https://cron-job.org)
- URL: `https://yourdomain.com/webhook.php?cron=1`
- Interval: Every minute

## 🆘 Troubleshooting

### Bot doesn't respond?
```bash
# Check logs
tail -f storage/logs/error.log

# Enable debug
# In .env: APP_DEBUG=true
```

### Can't post to channel?
- Make sure bot is admin
- Give "Post messages" permission
- Check error logs

### Webhook not working?
- Must be HTTPS
- Check `BOT_TOKEN` is correct
- Verify URL is accessible

### Database errors?
- Check MySQL is running
- Verify credentials in `.env`
- Ensure database exists

## 📚 Learn More

- **Full Guide**: [README.md](README.md)
- **Setup Details**: [SETUP.md](SETUP.md)  
- **Deployment**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **Architecture**: [walkthrough.md](walkthrough.md)

## 🎉 You're Done!

Your enterprise-grade Telegram Channel Management Bot is ready!

**Features Working:**
- ✅ Multi-channel management
- ✅ Post creation (text, media)
- ✅ Draft system
- ✅ Post scheduling
- ✅ Analytics dashboard
- ✅ Channel settings
- ✅ RBAC permissions
- ✅ Auto-posting via cron

**Enjoy managing your channels! 🚀**
