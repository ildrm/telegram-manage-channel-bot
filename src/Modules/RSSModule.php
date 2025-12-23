<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\RSSService;
use App\Services\ChannelService;
use App\Services\UserService;
use App\Services\PostService;
use App\Telegram\Client;

/**
 * RSS Module
 * 
 * Handles RSS feed automation
 */
class RSSModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(RSSService::class);
    }

    public function boot(Container $container): void
    {
        // Booted
    }

    public function getListeners(): array
    {
        return [
            'callback_query' => 'handleCallback',
            'text' => 'handleText'
        ];
    }

    /**
     * Handle callbacks
     */
    public function handleCallback(array $query, array $update, Container $container): void
    {
        $data = $query['data'] ?? '';
        $chatId = $query['message']['chat']['id'] ?? null;
        $messageId = $query['message']['message_id'] ?? null;
        $userId = $query['from']['id'];

        if (!$chatId) return;

        $telegram = $container->make(Client::class);

        // View RSS feeds
        if (strpos($data, 'rss:') === 0) {
            $channelId = (int)substr($data, 4);
            $telegram->answer($query['id']);
            $this->showFeeds($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Add RSS feed
        if (strpos($data, 'add_rss:') === 0) {
            $channelId = (int)substr($data, 8);
            $telegram->answer($query['id']);
            
            $userService = $container->make(UserService::class);
            $userService->setSession($userId, 'awaiting_rss_url', ['channel_id' => $channelId]);

            $telegram->edit(
                $chatId,
                $messageId,
                "📡 <b>Add RSS Feed</b>\n\n" .
                "Send me the RSS/Atom feed URL.\n\n" .
                "Example: <code>https://example.com/feed.xml</code>",
                [[['text' => '❌ Cancel', 'callback_data' => 'rss:' . $channelId]]]
            );
            return;
        }

        // Toggle feed
        if (strpos($data, 'toggle_rss:') === 0) {
            $parts = explode(':', $data);
            $feedId = (int)$parts[1];
            $channelId = (int)$parts[2];

            $telegram->answer($query['id']);
            
            $rssService = $container->make(RSSService::class);
            $rssService->toggleFeed($feedId);
            
            $this->showFeeds($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Delete feed
        if (strpos($data, 'delete_rss:') === 0) {
            $parts = explode(':', $data);
            $feedId = (int)$parts[1];
            $channelId = (int)$parts[2];

            $telegram->answer($query['id']);
            
            $rssService = $container->make(RSSService::class);
            $rssService->deleteFeed($feedId);
            
            $this->showFeeds($container, $userId, $chatId, $channelId, $messageId);
            return;
        }
    }

    /**
     * Handle text
     */
    public function handleText(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session) {
            return;
        }

        // Awaiting RSS URL
        if ($session['state'] === 'awaiting_rss_url') {
            $this->addFeed($container, $userId, $chatId, $session['data']['channel_id'], $text);
        }
    }

    /**
     * Show feeds
     */
    private function showFeeds(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $rssService = $container->make(RSSService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $feeds = $rssService->getChannelFeeds($channelId);

        $text = "📡 <b>RSS Feeds - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($feeds)) {
            $text .= "No RSS feeds configured.\n\n";
            $text .= "💡 Auto-post new content from your favorite sources!";

            $keyboard = [
                [['text' => '➕ Add RSS Feed', 'callback_data' => 'add_rss:' . $channelId]],
                [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
            ];
        } else {
            $text .= "Your feeds:\n\n";

            foreach ($feeds as $feed) {
                $status = $feed['active'] ? '🟢' : '🔴';
                $domain = parse_url($feed['feed_url'], PHP_URL_HOST);
                $lastCheck = $feed['last_check'] ? date('M d H:i', strtotime($feed['last_check'])) : 'Never';
                
                $text .= "$status <b>$domain</b>\n";
                $text .= "  Last check: $lastCheck\n\n";
            }

            $keyboard = [];
            foreach ($feeds as $feed) {
                $status = $feed['active'] ? '🟢' : '🔴';
                $domain = parse_url($feed['feed_url'], PHP_URL_HOST);
                
                $keyboard[] = [
                    ['text' => "$status $domain", 'callback_data' => 'toggle_rss:' . $feed['id'] . ':' . $channelId],
                    ['text' => '🗑', 'callback_data' => 'delete_rss:' . $feed['id'] . ':' . $channelId]
                ];
            }

            $keyboard[] = [['text' => '➕ Add Feed', 'callback_data' => 'add_rss:' . $channelId]];
            $keyboard[] = [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Add feed
     */
    private function addFeed(Container $container, int $userId, int $chatId, int $channelId, string $url): void
    {
        $rssService = $container->make(RSSService::class);
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);

        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $telegram->send($chatId, "❌ Invalid URL. Please send a valid RSS feed URL.");
                return;
            }

            // Try to fetch feed
            $items = $rssService->fetchFeedItems($url);

            if (empty($items)) {
                $telegram->send($chatId, "❌ Could not fetch feed. Please check the URL and try again.");
                return;
            }

            // Add feed
            $feedId = $rssService->addFeed($channelId, $userId, $url);
            $userService->clearSession($userId);

            $telegram->send(
                $chatId,
                "✅ <b>RSS Feed Added!</b>\n\n" .
                "URL: <code>" . htmlspecialchars($url) . "</code>\n\n" .
                "Found " . count($items) . " items in the feed.\n\n" .
                "The bot will check for new posts every 15 minutes.",
                [[['text' => '« Back to Feeds', 'callback_data' => 'rss:' . $channelId]]]
            );
        } catch (\Exception $e) {
            error_log("Failed to add RSS feed: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Process RSS feeds (called by cron)
     */
    public static function processFeed(Container $container): int
    {
        $rssService = $container->make(RSSService::class);
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);

        $feeds = $rssService->getActiveFeeds(10);
        $posted = 0;

        foreach ($feeds as $feed) {
            try {
                $items = $rssService->fetchFeedItems($feed['feed_url']);

                if (empty($items)) {
                    $rssService->updateLastCheck($feed['id']);
                    continue;
                }

                // Get the first item (most recent)
                $latestItem = $items[0];

                // Check if it's new
                if ($feed['last_item_id'] && $latestItem['guid'] === $feed['last_item_id']) {
                    $rssService->updateLastCheck($feed['id']);
                    continue;
                }

                // Format and post
                $content = $rssService->formatItem($latestItem, $feed['template']);

                $result = $telegram->sendMessage([
                    'chat_id' => $feed['channel_id'],
                    'text' => $content,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => false
                ]);

                if ($result) {
                    // Save post
                    $postService->createPost($feed['channel_id'], $result['message_id'], $feed['user_id'], [
                        'content_type' => 'text',
                        'content' => $content
                    ]);

                    // Update feed
                    $rssService->updateLastCheck($feed['id'], $latestItem['guid']);
                    $posted++;
                }
            } catch (\Exception $e) {
                error_log("Failed to process RSS feed {$feed['id']}: " . $e->getMessage());
                $rssService->updateLastCheck($feed['id']);
            }
        }

        return $posted;
    }
}
