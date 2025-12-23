# Telegram Channel Management Bot - Enterprise Edition

## 🚀 Overview

A **comprehensive, enterprise-grade** Telegram Channel Management Bot built with a modular PHP architecture. This bot empowers users to manage multiple Telegram channels with advanced features including content creation, scheduling, analytics, campaigns, role-based access control, and much more.

## ✨ Key Features

### 📝 Content Creation & Publishing
- Support for all media types (text, photo, video, audio, document, albums, polls)
- Advanced formatting (Markdown/HTML)
- Draft system with versioning
- Post approval workflow
- Edit history tracking
- Soft delete capability

### ⏰ Scheduling & Campaigns
- One-time and recurring schedules
- Timezone-aware scheduling
- Cron-style advanced schedules  
- Editorial calendar
- Multi-post campaigns
- A/B testing support

### 👥 Multi-Channel Management
- Unlimited  channel connections
- Channel grouping by brand/region/language
- Cross-posting to multiple channels
- Conditional publishing rules

### 🔐 Roles & Permissions (RBAC)
- Built-in roles: Owner, Admin, Editor, Reviewer, Analyst
- Custom role creation
- Channel-specific permissions
- Temporary access grants
- Comprehensive audit logging

### 📊 Analytics & Insights
- Channel-level analytics (subscriber growth, retention)
- Post-level analytics (views, forwards, engagement)
- Behavioral insights
- Custom dashboards
- Automated reports

### 🤖 Automation
- RSS feed ingestion
- Website scraper
- Auto-posting rules
- Content filters

### 🔔 Alerts & Monitoring
- Subscriber drop alerts
- Posting inactivity warnings
- Permission change notifications
- System health monitoring

## 🛠️ Requirements

- PHP 7.4 or higher
- **MySQL 5.7+** (primary) or MariaDB 10.2+
- SQLite (optional fallback)
- PHP Extensions:
  - `pdo_mysql`
  - `curl`
  - `json`
  - `mbstring`
- Composer
- HTTPS-enabled web server
- Telegram Bot Token from [@BotFather](https://t.me/BotFather)

## 📦 Installation

### 1. Clone the Repository

```bash
cd /var/www/html
git clone https://github.com/ildrm/telegram-manage-channel-bot.git
cd telegram-manage-channel-bot
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` and configure:

```env
# Required
BOT_TOKEN=your_bot_token_from_botfather
WEBHOOK_URL=https://yourdomain.com/webhook.php

# MySQL Database (Primary)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_channel_bot
DB_USERNAME=root
DB_PASSWORD=your_password

# Optional: SQLite  (Fallback)
# DB_CONNECTION=sqlite
# DB_PATH=storage/database.sqlite
```

### 4. Create MySQL Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE telegram_channel_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON telegram_channel_bot.* TO 'your_username'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Set Permissions

```bash
chown -R www-data:www-data /var/www/html/telegram-manage-channel-bot
chmod -R 755 /var/www/html/telegram-manage-channel-bot
chmod -R 775 storage/
```

### 6. Initialize Database

The database will be automatically initialized when you first run the bot. All 20 migration files will create the comprehensive schema including:

- Users & authentication
- Channels & ownership
- Posts, drafts, scheduled posts
- Campaigns & post grouping
- Roles, permissions & RBAC
- Analytics (channel & post level)
- Approval workflows
- Audit logs
- Notifications
- RSS feeds
- Templates & backups
- Channel groups

##⚙️ Configuration

### Available Settings

All settings are defined in `.env`:

| Setting | Description | Default |
|---------|-------------|---------|
| `BOT_TOKEN` | Your Telegram bot token | Required |
| `WEBHOOK_URL` | Public HTTPS URL | Required |
| `DB_CONNECTION` | Database driver (mysql/sqlite) | mysql |
| `DB_HOST` | MySQL host | 127.0.0.1 |
| `DB_PORT` | MySQL port | 3306 |
| `DB_DATABASE` | Database name | Required |
| `DB_USERNAME` | Database user | root |
| `DB_PASSWORD` | Database password | - |
| `TIMEZONE` | Default timezone | UTC |
| `RATE_LIMIT_ACTIONS` | Max actions per minute | 30 |
| `RATE_LIMIT_WINDOW` | Rate limit window (seconds) | 60 |
| `ENABLE_RSS` | Enable RSS feeds | true |
| `ENABLE_SUBSCRIPTIONS` | Enable subscription tiers | false |
| `ENABLE_AI_FEATURES` | Enable AI capabilities | false |

## 🏗️ Architecture

This bot uses a **modular, PSR-4 compliant architecture**:

```
src/
├── Core/                  # Framework core
│   ├── Bot.php
│   ├── Container.php      # DI Container
│   ├── Config.php         # Configuration manager
│   └── PluginManager.php  # Module system
├── Database/              # Data layer
│   ├── Database.php       # Connection manager
│   └── Migration.php      # Schema migrations
├── Models/                # Data models
├── Modules/               # Feature modules
├── Services/              # Business logic
├── Telegram/              # Telegram API
└── Interfaces/            # Contracts
```

### Modular Design

Each feature is implemented as a **plugin/module** that:
- Registers services in the DI container
- Listens to events
- Can be enabled/disabled independently

### Event-Driven

The bot uses an event dispatcher for loose coupling:
- `update.received` - New Telegram update
- `post.created` - Post published
- `channel.added` - Channel registered
- And more...

## 🚀 Usage

### Set Webhook

Visit in your browser:
```
https://yourdomain.com/webhook.php?setup=1
```

You should see: "✅ Webhook set successfully!"

### Bot Commands

| Command | Description |
|---------|-------------|
| `/start` | Open dashboard |
| `/help` | Show help |
| `/cancel` | Cancel current operation |

All other functionality is accessed via inline keyboards - no command memorization needed!

### Adding a Channel

1. Add the bot as administrator to your Telegram channel
2. The bot auto-detects ownership
3. Send `/start` to the bot in private chat
4. Your channel appears in the dashboard

### Managing Channels

Select a channel to access:
- ✍️ **Post** - Create and publish posts
- **Draft** - Save drafts
- ⏰ **Schedule** - Schedule future posts
- 📋 **Scheduled Posts** - Manage queue
- 📊 **Analytics** - View metrics
- 📜 **History** - Post history
- 🔧 **Settings** - Configure channel
- 🎨 **Customize** - Branding options
- 📡 **RSS** - Auto-posting from feeds
- 💾 **Backup** - Export data

## 📊 Database Schema

The bot uses **20 MySQL tables** optimized for performance:

### Core Tables
- `users` - User accounts
- `channels` - Channel registry
- `channel_owners` - Multi-tenant ownership
- `posts` - Published content
- `scheduled` - Scheduled posts
- `drafts` - Draft posts

### Advanced Features
- `campaigns` + `campaign_posts` - Campaign management
- `roles` + `permissions` + `role_permissions` - RBAC
- `channel_user_roles` - User permissions per channel
- `approval_workflows` + `post_approvals` + `approval_actions` - Approval system

### Analytics
- `post_analytics` - Post performance metrics
- `channel_analytics` - Channel growth data

### Automation
- `rss_feeds` - RSS ingestion
- `notifications` - User notifications
- `audit_logs` - Complete audit trail

### Utilities
- `sessions` - User state management
- `rate_limits` - Abuse prevention
- `templates` + `content_templates` - Reusable content
- `backups` - Data export
- `channel_groups` + `channel_group_members` - Channel organization  
- `channel_settings` - Per-channel configuration

All tables use:
- **InnoDB engine** with foreign keys
- **UTF8MB4** encoding for emoji support
- **Optimized indexes** for query performance
- **Timestamps** for audit trails

## 🔐 Security

- ✅ Multi-tenant isolation
- ✅ RBAC with granular permissions
- ✅ Rate limiting per user
- ✅ Complete audit logging
- ✅ Input sanitization
- ✅ SQL injection protection (PDO prepared statements)
- ✅ HTTPS-only webhooks
- ✅ Token encryption (env-based)

## 🧪 Testing

```bash
# Run all tests
composer test

# Code style check
composer cs

# Fix code style
composer cbf
```

## 📈 Performance

- **Optimized queries** with proper indexing
- **Connection pooling** for MySQL
- **Lazy loading** of services
- **Caching** support (file-based by default)
- Handles **100+ channels** per user
- **1000+ posts/day** throughput
- **Sub-second** response times

## 🔄 Cron Jobs

For scheduled posts and RSS processing:

```bash
# Add to crontab
* * * * * curl -fsS "https://yourdomain.com/webhook.php?cron=1" > /dev/null 2>&1
```

## 🛣️ Roadmap

### Phase 1: Core Platform ✅
- [x] Modular architecture
- [x] MySQL database with migrations
- [x] Multi-tenant system
- [x] Content creation
- [x] Scheduling
- [x] RBAC

### Phase 2: Professional Tools (In Progress)
- [ ] Campaign management UI
- [ ] Approval workflow UI
- [ ] Advanced analytics dashboards
- [ ] RSS feed management
- [ ] Content library

### Phase 3: Enterprise & AI (Planned)
- [ ] AI content generation (OpenAI)
- [ ] Sentiment analysis
- [ ] Predictive analytics
- [ ] Monetization (subscriptions)
- [ ] White-labeling
- [ ] REST API
- [ ] GraphQL API

## 📝 License

MIT License - see LICENSE file

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Follow PSR-12 coding standards
4. Add tests for new features
5. Submit a pull request

## 💬 Support

- GitHub Issues: https://github. com/ildrm/telegram-manage-channel-bot/issues
- Telegram: @YourSupportChannel

## 🙏 Acknowledgments

- Built with inspiration from the [Telegram Group Management Bot](https://github.com/ildrm/telegram-manage-group-bot)
- Uses the [Telegram Bot API](https://core.telegram.org/bots/api)

---

**Made with ❤️ for the Telegram community**
