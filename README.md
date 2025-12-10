# Telegram Channel Management Bot

A single-file, production-ready **Telegram Channel Management Bot** written in PHP.  
It lets each user privately manage only the channels where they have added the bot as an administrator, with strict multi-tenant isolation and a rich, button‑driven control panel.

The bot uses Telegram webhooks and a local SQLite database, and is fully self-contained in one file: `telegram_channel_bot.php`.

---

## Key Features

### Multi-tenant & private dashboards

- Each Telegram user gets their **own dashboard** in a private chat.
- The bot tracks:
  - Which channels it is installed in.
  - Which user added it as admin (channel owner).
- Owners only see and manage **their own** channels; other users and channels remain invisible.

Channel ownership is detected automatically via Telegram `my_chat_member` events.

### Channel onboarding

- When the bot is added as an **administrator** to a channel or supergroup:
  - The channel is registered in the `channels` table.
  - The user who added the bot is stored as the owner in `channel_owners`.
- When the bot is removed from a channel:
  - The channel is marked inactive for that owner.
  - The dashboard reflects that it is no longer manageable.

### Full posting suite

From the channel menu in your dashboard you can:

- Create and send posts to your channels:
  - Plain text posts
  - Photos, videos, and documents
  - Polls
  - Posts with inline buttons
- Save **drafts** and reuse templates.
- Keep a **post history** of what was published by the bot.

All posted messages are recorded in the `posts` table, and basic analytics are updated automatically.

### Scheduling (one-time & recurring)

- Schedule posts for a future time:
  - One-time posts.
  - Recurring posts (hourly, daily, weekly, monthly).
- Scheduled jobs are stored in the `scheduled` table.
- A cron endpoint (`?cron=1`) picks up due posts and publishes them.
- Recurring posts automatically reschedule using a simple recurrence rule:
  - Hourly
  - Daily
  - Weekly
  - Monthly

### RSS / Auto-posting

- Attach **RSS feeds** (and similar sources like YouTube channels) to a channel.
- Feeds are stored in the `rss_feeds` table, with:
  - `feed_url`
  - Mapping to the target channel
  - Template used for rendering
  - Last-checked information
- The cron handler (`processCron`) includes a hook for polling feeds and auto-posting new items (RSS fetching/parsing is simplified in this file and is intended as a hook for your own implementation).

### Analytics & insights

- `analytics` table stores per-day, per-channel metrics such as:
  - Number of posts per day.
- The **Analytics** menu in the channel dashboard shows a summary of recent activity to help you understand posting behavior and engagement (high-level).

### Channel settings & customization

Per-channel settings are stored in `channel_settings`:

- Reactions:
  - Enable/disable reactions.
  - Configure default reactions.
  - Enable automatic reaction with a chosen emoji.
- Views:
  - Toggle visibility of view counters (where supported).
- Comments:
  - Enable/disable comments (for channels linked to discussion groups).
- Anti-spam for comments:
  - Toggle anti-spam mode and define a blacklist of spam words.
- CAPTCHA:
  - Optional CAPTCHA gate for new members in the linked discussion group.
- Branding:
  - **Watermark** text applied to posts (where supported).
  - **Signature** text appended to content.
- Deep-link welcome:
  - Custom message to send when users start the bot with a `/start` payload (e.g. campaign links).

### Templates, backups & drafts

- **Templates** (`templates` table):
  - Named message templates bound to a user.
  - Use them as starting points for repetitive posts or campaigns.
- **Drafts** (`drafts` table):
  - Save in-progress posts per channel and finish them later.
- **Backups** (`backups` table):
  - Store serialized snapshots of selected channel data (posts/config).
  - Accessible via the “💾 Backup” menu item for channel owners.

### Sessions & rate limiting

- `sessions` table:
  - Tracks per-user state for multi-step flows (e.g. posting wizard, scheduling wizard).
  - Ensures a clean conversational experience when collecting content and options.
- `rate_limits` table:
  - Per-user action counter and sliding time window.
  - Prevents abuse by limiting how many bot actions a single user can trigger per minute.

### Security & robustness

- Request authentication helper:
  - `verifyTelegramRequest()` can be used to enforce Telegram-origin checks (disabled when `DEBUG` is `true`).
- Centralized error logging via `logError()` (when `DEBUG` is enabled).
- All Telegram API calls handled through `apiRequest()` with basic safety checks.
- SQLite schema created and migrated automatically on first run.

---

## Requirements

- **PHP** 7.4 or higher
- PHP extensions:
  - `pdo_sqlite` / `sqlite3`
  - `curl`
  - `json`
  - `mbstring` (recommended)
- Public **HTTPS** endpoint for Telegram webhooks
- A Telegram bot token from [@BotFather](https://t.me/BotFather)

---

## Configuration

All configuration is defined near the top of `telegram_channel_bot.php`:

```php
// CONFIGURATION

define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');               // Get from @BotFather
define('WEBHOOK_URL', 'https://yourdomain.com/bot.php');  // Public HTTPS URL of this script
define('DB_FILE', __DIR__ . '/database.sqlite');          // SQLite DB path
define('ADMIN_IDS', []);                                  // Optional: array of Telegram user IDs for bot admins
define('TIMEZONE', 'UTC');                                // Default timezone
define('RATE_LIMIT', 30);                                 // Max actions per minute per user
define('DEBUG', false);                                   // Enable error logging / relaxed security checks
```

### 1. Bot token

1. Talk to **@BotFather** in Telegram.
2. Create a new bot and copy the **token**.
3. Paste it into `BOT_TOKEN`.

### 2. Webhook URL

Set `WEBHOOK_URL` to the public HTTPS URL where the script is reachable, for example:

```php
define('WEBHOOK_URL', 'https://example.com/telegram_channel_bot.php');
```

The URL must be:

- Accessible from the public internet.
- Served over HTTPS with a valid certificate.

### 3. Database file

By default, the script uses:

```php
define('DB_FILE', __DIR__ . '/database.sqlite');
```

Make sure the directory containing `DB_FILE` is writable by your web server user:

```bash
chown www-data:www-data /var/www/html
chmod 750 /var/www/html
```

Adjust user and path for your environment.

### 4. Admin IDs (optional)

You can set a list of admin user IDs:

```php
define('ADMIN_IDS', [123456789, 987654321]);
```

These can be used inside the script for extra diagnostic or support features.  
They are **not required** for normal operation.

### 5. Timezone & rate limit

- `TIMEZONE`: affects how dates/times are stored and displayed (e.g. scheduling, analytics).
- `RATE_LIMIT`: maximum actions per minute per user; adjust based on your traffic and server capacity.

---

## Database schema (high level)

The bot initializes its SQLite schema automatically on first run. Core tables:

- `users`  
  Basic Telegram user data (id, username, names, created_at).

- `channels`  
  Channels where the bot is installed; includes title, username and active flag.

- `channel_owners`  
  Links owners (Telegram users) to channels they manage; handles multi-tenant separation.

- `posts`  
  Published posts sent by the bot to channels (with type, content, media, timestamps).

- `scheduled`  
  Scheduled posts (one-time and recurring), with:
  - `schedule_time`
  - `recurring` JSON rule
  - `status` (pending, sent, failed, cancelled)

- `drafts`  
  Saved drafts for posts per channel and per user.

- `rss_feeds`  
  RSS/auto-post feeds linked to channels:
  - Feed URL
  - Template
  - Last checked time
  - Last seen item ID
  - Activation flag

- `analytics`  
  Per-day metrics per channel (`metric_type` and `metric_value`).

- `channel_settings`  
  Per-channel toggles and customization:
  - Reactions, default reactions, auto-react
  - Views and comments
  - Anti-spam and blacklist
  - CAPTCHA
  - Watermark, signature
  - `/start` payload message

- `sessions`  
  Per-user conversation state (wizard steps + data).

- `rate_limits`  
  Per-user counters for rate limiting.

- `templates`  
  Named content templates for each user.

- `backups`  
  Serialized backup data for channels (metadata and content snapshots).

Backing up the bot is as simple as copying the SQLite file:

```bash
cp database.sqlite database_backup.sqlite
```

---

## Installation & Setup

1. **Upload the script**

   Place `telegram_channel_bot.php` on your web server, for example:

   ```text
   /var/www/html/telegram_channel_bot.php
   ```

2. **Configure the script**

   Open the file and update the configuration block:

   - `BOT_TOKEN`
   - `WEBHOOK_URL`
   - `DB_FILE` (optional)
   - `TIMEZONE`, `RATE_LIMIT`, and `DEBUG` as desired

3. **Ensure write permissions**

   The web server user must be able to create and write `database.sqlite`:

   ```bash
   chown www-data:www-data /var/www/html/telegram_channel_bot.php
   chown www-data:www-data /var/www/html
   chmod 750 /var/www/html
   ```

4. **Set the webhook (built-in setup endpoint)**

   Visit the setup URL in your browser:

   ```text
   https://yourdomain.com/telegram_channel_bot.php?setup=1
   ```

   If everything is correct, you should see:

   - “✅ Webhook set successfully! Bot is ready to use.”

   If it fails, review:

   - `BOT_TOKEN`
   - `WEBHOOK_URL`
   - Server logs / `DEBUG` mode

5. **CLI sanity check (optional)**

   You can run the script via CLI:

   ```bash
   php telegram_channel_bot.php
   ```

   In CLI mode it will simply print the webhook URL and exit.

---

## Cron setup (scheduling & RSS)

The bot exposes a lightweight cron endpoint:

```text
https://yourdomain.com/telegram_channel_bot.php?cron=1
```

`processCron()` will:

- Publish due **scheduled posts**.
- Re-schedule recurring posts.
- Process active **RSS/auto-post feeds** (placeholder hook for your feed logic).

Set up a system cron job, for example:

```bash
* * * * * curl -fsS "https://yourdomain.com/telegram_channel_bot.php?cron=1" >/dev/null 2>&1
```

Running it every minute ensures schedule accuracy; you can reduce frequency if you tolerate more delay.

---

## Using the Bot

### 1. Start the dashboard

- Open a private chat with your bot in Telegram.
- Send `/start`.
- The bot shows the **Channel Management Dashboard**, including:
  - How many channels you manage.
  - A paginated list of your channels (if any).
  - Buttons for **Help** and **Refresh**.

### 2. Connect a channel

- Add the bot as **administrator** to your channel.
- Once added, the bot receives a `my_chat_member` event and:
  - Registers the channel.
  - Assigns you as the owner.
- Go back to the private chat and tap “🔄 Refresh” or send `/start` again.
- Your channel should now appear in “Your Channels”.

### 3. Channel menu

Selecting a channel opens a menu with actions such as:

- **✍️ Post** – send a new post now.
- **📝 Draft** – create, view, or reuse drafts.
- **⏰ Schedule** – schedule a new post.
- **📋 Scheduled Posts** – view or manage existing scheduled posts.
- **📊 Analytics** – view high-level metrics.
- **📜 Post History** – review previous posts sent by the bot.
- **🔧 Settings** – manage reactions, views, comments, anti-spam, etc.
- **🎨 Customize** – configure watermark, signature, default reactions.
- **📡 RSS/Auto-Post** – attach or manage RSS feeds for auto-posting.
- **💾 Backup** – create or manage channel backups.

All navigation is done via inline keyboards; no command memorization is necessary.

### 4. Commands

- `/start` – open or refresh your dashboard.
- `/help` – show detailed help text and feature overview.
- `/cancel` – cancel the current multi-step operation (posting wizard, schedule wizard, etc.).

All other functionality is exposed through buttons.

---

## Security & Best Practices

- Always serve the bot via **HTTPS**.
- Keep `BOT_TOKEN` secret:
  - Do not commit it to public version control.
  - Prefer environment variables or private configuration includes in production.
- Restrict file system access:
  - Ensure `database.sqlite` and any logs are not directly reachable from the web.
  - Use restrictive file permissions.
- Consider protecting:
  - `?setup=1`
  - `?cron=1`  
  via IP allowlists or HTTP auth in your web server configuration.

---

## Extending the Bot

The script is organized into clear sections:

- Configuration
- Security & helper functions
- Database setup and migrations
- Telegram API wrappers
- Business logic:
  - Handling of `update`, `message`, `callback_query`, `my_chat_member`, `channel_post`, etc.
- Menus, wizards, and session management
- Cron processing
- Webhook & setup endpoints

You can extend it by:

- Adding new settings in `channel_settings` and their corresponding UI buttons.
- Enhancing analytics to track more metrics (views, reactions, joins).
- Implementing full RSS/YouTube fetch & parsing in the `rss_feeds` cron section.
- Adding export/import tools for templates and backups.
- Integrating external monitoring or logging solutions.

Because everything is in one file with a simple SQLite backend, deployment and updates remain straightforward.

---

## License

If the header in `telegram_channel_bot.php` does not specify a different license, you may treat this bot as MIT-style licensed for your own projects.  
Always check and adjust licensing information according to your distribution requirements.
