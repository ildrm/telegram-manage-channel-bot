# 🚀 Quick Setup Guide

## Prerequisites Completed ✅

- ✅ MySQL database created
- ✅ Web server with PHP 7.4+
- ✅ Composer installed
- ✅ Telegram Bot Token from @BotFather

## Step 1: Configure Environment

Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
# Required - Get from @BotFather
BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11

# Required - Your webhook URL
WEBHOOK_URL=https://yourdomain.com/webhook.php

# MySQL Database (PRIMARY)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_channel_bot
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Optional
TIMEZONE=UTC
APP_DEBUG=false
```

## Step 2: Create MySQL Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE telegram_channel_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON telegram_channel_bot.* TO 'your_username'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Step 3: Install Dependencies

```bash
cd c:\xampp\htdocs\bot\telegram-manage-channel-bot
composer install
```

## Step 4: Set Permissions (Linux/Mac)

```bash
chmod -R 755 .
chmod -R 775 storage/
```

On Windows with XAMPP: No action needed.

## Step 5: Set Webhook

Visit in your browser:
```
https://yourdomain.com/webhook.php?setup=1
```

You should see:
```
✅ Webhook set successfully!

Bot: @YourBotUsername
Webhook URL: https://yourdomain.com/webhook.php

Your bot is now ready to receive updates!
```

## Step 6: Test the Bot

1. Open Telegram and find your bot (@YourBotUsername)
2. Send `/start`
3. Add the bot as administrator to a channel
4. Go back to the bot and send `/start` again
5. You should see your channel in the dashboard!

## Step 7: Setup Cron for Scheduled Posts (Optional)

Add to crontab (Linux/Mac):
```bash
crontab -e
```

Add this line:
```
* * * * * curl -fsS "https://yourdomain.com/webhook.php?cron=1" > /dev/null 2>&1
```

On Windows with XAMPP, use Task Scheduler or a service like [cron-job.org](https://cron-job.org):
```
https://yourdomain.com/webhook.php?cron=1
```

## 🎉 You're All Set!

### Basic Usage

1. **Add Channel**: Make the bot administrator of your channel
2. **Create Post**: Select channel → ✍️ New Post → Send content
3. **View Posts**: Select channel → 📋 View Posts
4. **Analytics**: Select channel → 📊 Analytics (coming soon)
5. **Settings**: Select channel → 🔧 Settings (coming soon)

### What Works Now (Phase 1)

✅ Multi-channel management  
✅ Channel ownership detection  
✅ Text, photo, video, document posting  
✅ Post history  
✅ RBAC framework  
✅ MySQL database with 27 tables  
✅ Session management  
✅ Rate limiting  

### Phases 2-10 (To Be Implemented)

You now have a solid foundation! The following features have database schemas and can be implemented:

📝 **Drafts & Scheduling** - Database tables ready  
📊 **Analytics** - Tables ready, need UI implementation  
🎯 **Campaigns** - Tables ready, need module implementation  
👥 **Roles & Permissions** - RBAC fully set up, need UI  
📡 **RSS Feeds** - Tables ready, need automation  
✅ **Approvals** - Workflow tables ready  
🔔 **Notifications** - Tables ready  
💾 **Backups** - Tables ready  

### Troubleshooting

**Error: BOT_TOKEN is required**
- Make sure `.env` file exists and contains BOT_TOKEN

**Error: Database connection failed**
- Check MySQL credentials in `.env`
- Ensure database exists
- Verify MySQL is running

**Webhook setup failed**
- Ensure WEBHOOK_URL is HTTPS (Telegram requires SSL)
- Check that webhook.php is accessible from the internet
- Verify BOT_TOKEN is correct

**Bot doesn't respond**
- Check error logs: `storage/logs/error.log`
- Enable debug mode: Set `APP_DEBUG=true` in `.env`
- Check update logs: `storage/logs/updates.log` (when debug is on)

**Posts not publishing**
- Ensure bot is administrator in the channel
- Check bot has "Post messages" permission
- Verify channel ID is correct

### File Structure

```
telegram-manage-channel-bot/
├── public/
│   └── webhook.php          # Entry point
├── src/
│   ├── Core/                # Framework core
│   │   ├── Bot.php
│   │   ├── Config.php
│   │   ├── Container.php
│   │   └── PluginManager.php
│   ├── Database/            # Database layer
│   │   ├── Database.php
│   │   └── Migration.php
│   ├── Services/            # Business logic
│   │   ├── AuthorizationService.php
│   │   ├── ChannelService.php
│   │   ├── PostService.php
│   │   └── UserService.php
│   ├── Modules/             # Feature modules
│   │   ├── CoreModule.php
│   │   ├── AuthModule.php
│   │   └── ContentModule.php
│   ├── Telegram/            # Telegram API
│   │   ├── Client.php
│   │   └── UpdateHandler.php
│   └── Interfaces/          # Contracts
│       └── PluginInterface.php
├── storage/
│   ├── logs/               # Log files
│   └── cache/              # Cache data
├── .env.example            # Environment template
├── .env                    # Your configuration
├── composer.json           # Dependencies
└── README.md              # Full documentation
```

### Development

To extend the bot:

1. **Create a Service**: `src/Services/MyService.php`
2. **Create a Module**: `src/Modules/MyModule.php` implementing `PluginInterface`
3. **Register in Bot.php**: Add `$this->pluginManager->register(MyModule::class);`
4. **Add event listeners**: Return them in `getListeners()`

Example module:
```php
<?php
namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;

class MyModule implements PluginInterface
{
    public function register(Container $container): void
    {
        // Register services
        $container->singleton(MyService::class);
    }

    public function boot(Container $container): void
    {
        // Initialize
    }

    public function getListeners(): array
    {
        return [
            'callback_query' => 'handleCallback'
        ];
    }

    public function handleCallback($query, $update, Container $c)
    {
        // Handle callback
    }
}
```

### Need Help?

- 📖 Full documentation: [README.md](README.md)
- 🐛 Report issues: GitHub Issues
- 💬 Community: Telegram Support Channel

---

**Built with ❤️ for the Telegram community**
