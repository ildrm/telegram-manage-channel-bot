<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\UserService;
use App\Services\ChannelService;
use App\Services\PostService;
use App\Services\AuthorizationService;
use App\Telegram\Client;

/**
 * Content Module
 * 
 * Handles content creation, editing, and publishing
 */
class ContentModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(UserService::class);
        $container->singleton(ChannelService::class);
        $container->singleton(PostService::class);
    }

    public function boot(Container $container): void
    {
        // Booted
    }

    public function getListeners(): array
    {
        return [
            'callback_query' => 'handleCallback',
            'text' => 'handleText',
            'photo' => 'handlePhoto',
            'video' => 'handleVideo',
            'document' => 'handleDocument',
            'location' => 'handleLocation',
        ];
    }

    /**
     * Handle callback queries
     */
    public function handleCallback(array $query, array $update, Container $container): void
    {
        $data = $query['data'] ?? '';
        $chatId = $query['message']['chat']['id'] ?? null;
        $messageId = $query['message']['message_id'] ?? null;
        $userId = $query['from']['id'];

        if (!$chatId) return;

        $telegram = $container->make(Client::class);
        $channelService = $container->make(ChannelService::class);
        $userService = $container->make(UserService::class);
        $authService = $container->make(AuthorizationService::class);

        // Menu
        if ($data === 'menu') {
            $telegram->answer($query['id']);
            $this->showDashboard($container, $userId, $chatId, $messageId);
            return;
        }

        // Help
        if ($data === 'help') {
            $telegram->answer($query['id']);
            $this->showHelp($container, $chatId, $messageId);
            return;
        }

        // Channel selected
        if (strpos($data, 'ch:') === 0) {
            $channelId = (int)substr($data, 3);
            $telegram->answer($query['id']);
            
            if (!$authService->userOwnsChannel($userId, $channelId)) {
                $telegram->answer($query['id'], "❌ Access denied", true);
                return;
            }

            $this->showChannelMenu($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Post to channel
        if (strpos($data, 'post:') === 0) {
            $channelId = (int)substr($data, 5);
            $telegram->answer($query['id']);
            
            if (!$authService->userHasPermission($userId, $channelId, 'post.create')) {
                $telegram->answer($query['id'], "❌ No permission", true);
                return;
            }

            $userService->setSession($userId, 'awaiting_post', ['channel_id' => $channelId]);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "✍️ <b>Create New Post</b>\n\n" .
                "Send me the content for your post:\n\n" .
                "📝 Text message\n" .
                "🖼 Photo with caption\n" .
                "🎥 Video with caption\n" .
                "📄 Document with caption\n" .
                "📊 Poll (forward from another chat)\n\n" .
                "💡 You can use HTML formatting",
                [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $channelId]]]
            );
            return;
        }

        // View posts
        if (strpos($data, 'posts:') === 0) {
            $parts = explode(':', $data);
            $channelId = (int)$parts[1];
            $offset = (int)($parts[2] ?? 0);
            
            $telegram->answer($query['id']);
            $this->showPosts($container, $userId, $chatId, $channelId, $offset, $messageId);
            return;
        }

        // View post details
        if (strpos($data, 'view_post:') === 0) {
            $postId = (int)substr($data, 10);
            $telegram->answer($query['id']);
            $this->showPostDetails($container, $userId, $chatId, $postId, $messageId);
            return;
        }

        // Edit post
        if (strpos($data, 'edit_post:') === 0) {
            $postId = (int)substr($data, 10);
            $telegram->answer($query['id']);
            
            $userService->setSession($userId, 'awaiting_edit', ['post_id' => $postId]);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "✏️ <b>Edit Post</b>\n\n" .
                "Send me the new content for this post.\n\n" .
                "💡 You can send text or media with caption.",
                [[['text' => '❌ Cancel', 'callback_data' => 'view_post:' . $postId]]]
            );
            return;
        }

        // Pin post
        if (strpos($data, 'pin_post:') === 0) {
            $parts = explode(':', $data);
            $postId = (int)$parts[1];
            $channelId = (int)$parts[2];
            
            $telegram->answer($query['id']);
            $this->pinPost($container, $userId, $chatId, $postId, $channelId);
            return;
        }

        // Unpin post
        if (strpos($data, 'unpin_post:') === 0) {
            $parts = explode(':', $data);
            $postId = (int)$parts[1];
            $channelId = (int)$parts[2];
            
            $telegram->answer($query['id']);
            $this->unpinPost($container, $userId, $chatId, $postId, $channelId);
            return;
        }

        // Create poll
        if (strpos($data, 'create_poll:') === 0) {
            $channelId = (int)substr($data, 12);
            $telegram->answer($query['id']);
            
            $userService->setSession($userId, 'awaiting_poll_question', ['channel_id' => $channelId]);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "📊 <b>Create Poll</b>\n\n" .
                "Send me the poll question.",
                [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $channelId]]]
            );
            return;
        }

        // Analytics
        if (strpos($data, 'analytics:') === 0) {
            $channelId = (int)substr($data, 10);
            $telegram->answer($query['id'], "📊 Analytics coming soon!");
            return;
        }

        // Settings
        if (strpos($data, 'settings:') === 0) {
            $channelId = (int)substr($data, 9);
            $telegram->answer($query['id'], "🔧 Settings coming soon!");
            return;
        }
    }

    /**
     * Handle text messages
     */
    public function handleText(array $message, array $update, Container $container): void
    {
        // Only handle private chats
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        // Skip commands
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

        // Awaiting post content
        if ($session['state'] === 'awaiting_post') {
            $this->createPost($container, $userId, $chatId, $session['data']['channel_id'], [
                'content_type' => 'text',
                'content' => $text
            ]);
        }

        // Awaiting edit content
        if ($session['state'] === 'awaiting_edit') {
            $this->editPost($container, $userId, $chatId, $session['data']['post_id'], [
                'content_type' => 'text',
                'content' => $text
            ]);
        }

        // Awaiting poll question
        if ($session['state'] === 'awaiting_poll_question') {
            $session['data']['question'] = $text;
            $userService->setSession($userId, 'awaiting_poll_options', $session['data']);
            
            $telegram = $container->make(Client::class);
            $telegram->send(
                $chatId,
                "📊 <b>Poll Question</b>\n\n" .
                "<i>" . htmlspecialchars($text) . "</i>\n\n" .
                "Now send me the poll options, one per line.\n\n" .
                "Example:\n" .
                "Option 1\n" .
                "Option 2\n" .
                "Option 3"
            );
        }

        // Awaiting poll options
        if ($session['state'] === 'awaiting_poll_options') {
            $this->createPoll($container, $userId, $chatId, $session['data'], $text);
        }
    }

    /**
     * Handle photo messages
     */
    public function handlePhoto(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session || $session['state'] !== 'awaiting_post') {
            return;
        }

        // Get largest photo
        $photos = $message['photo'];
        $photo = end($photos);

        // Check if this is part of a media group (album)
        if (isset($message['media_group_id'])) {
            // Store for album processing
            $this->handleMediaGroup($container, $userId, $chatId, $message);
        } else {
            $this->createPost($container, $userId, $chatId, $session['data']['channel_id'], [
                'content_type' => 'photo',
                'media_id' => $photo['file_id'],
                'content' => $message['caption'] ?? ''
            ]);
        }
    }

    /**
     * Handle media group (albums)
     */
    private function handleMediaGroup(Container $container, int $userId, int $chatId, array $message): void
    {
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);
        $session = $userService->getSession($userId);

        if (!$session) return;

        // Store media group items temporarily
        $mediaGroupId = $message['media_group_id'];
        $sessionData = $session['data'];
        
        if (!isset($sessionData['media_group'])) {
            $sessionData['media_group'] = [];
        }
        
        if (!isset($sessionData['media_group'][$mediaGroupId])) {
            $sessionData['media_group'][$mediaGroupId] = [];
        }

        // Add current media
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $photo = end($photos);
            $sessionData['media_group'][$mediaGroupId][] = [
                'type' => 'photo',
                'media' => $photo['file_id'],
                'caption' => $message['caption'] ?? ''
            ];
        } elseif (isset($message['video'])) {
            $sessionData['media_group'][$mediaGroupId][] = [
                'type' => 'video',
                'media' => $message['video']['file_id'],
                'caption' => $message['caption'] ?? ''
            ];
        }

        $userService->setSession($userId, $session['state'], $sessionData);

        // Wait a bit for all media to arrive, then post
        // In real implementation, you'd use a timer or queue
        // For now, we'll post after receiving the group
        if (count($sessionData['media_group'][$mediaGroupId]) >= 1) {
            $telegram->send($chatId, "📸 Album received! Publishing to channel...");
            $this->postMediaGroup($container, $userId, $chatId, $sessionData['channel_id'], $sessionData['media_group'][$mediaGroupId]);
        }
    }

    /**
     * Post media group to channel
     */
    private function postMediaGroup(Container $container, int $userId, int $chatId, int $channelId, array $media): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);
        $userService = $container->make(UserService::class);

        try {
            $result = $telegram->sendMediaGroup([
                'chat_id' => $channelId,
                'media' => json_encode($media)
            ]);

            if ($result) {
                // Save first message as post
                $postService->createPost($channelId, $result[0]['message_id'], $userId, [
                    'content_type' => 'album',
                    'content' => 'Media album (' . count($media) . ' items)'
                ]);

                $userService->clearSession($userId);

                $telegram->send(
                    $chatId,
                    "✅ <b>Album Published!</b>\n\n" .
                    "Posted " . count($media) . " media items to the channel.",
                    [[['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to post album: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error posting album: " . $e->getMessage());
        }
    }

    /**
     * Handle video messages
     */
    public function handleVideo(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session || $session['state'] !== 'awaiting_post') {
            return;
        }

        $this->createPost($container, $userId, $chatId, $session['data']['channel_id'], [
            'content_type' => 'video',
            'media_id' => $message['video']['file_id'],
            'content' => $message['caption'] ?? ''
        ]);
    }

    /**
     * Handle document messages
     */
    public function handleDocument(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session || $session['state'] !== 'awaiting_post') {
            return;
        }

        $this->createPost($container, $userId, $chatId, $session['data']['channel_id'], [
            'content_type' => 'document',
            'media_id' => $message['document']['file_id'],
            'content' => $message['caption'] ?? ''
        ]);
    }

    /**
     * Create and publish post
     */
    private function createPost(Container $container, int $userId, int $chatId, int $channelId, array $data): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $userService = $container->make(UserService::class);

        $channel = $channelService->getChannel($channelId);

        if (!$channel) {
            $telegram->send($chatId, "❌ Channel not found");
            return;
        }

        try {
            // Publish to channel
            $params = ['chat_id' => $channelId];

            switch ($data['content_type']) {
                case 'photo':
                    $params['photo'] = $data['media_id'];
                    $params['caption'] = $data['content'];
                    $result = $telegram->sendPhoto($params);
                    break;

                case 'video':
                    $params['video'] = $data['media_id'];
                    $params['caption'] = $data['content'];
                    $result = $telegram->sendVideo($params);
                    break;

                case 'document':
                    $params['document'] = $data['media_id'];
                    $params['caption'] = $data['content'];
                    $result = $telegram->sendDocument($params);
                    break;

                default: // text
                    $params['text'] = $data['content'];
                    $result = $telegram->sendMessage($params);
                    break;
            }

            if ($result) {
                // Save post
                $postService->createPost($channelId, $result['message_id'], $userId, $data);

                // Clear session
                $userService->clearSession($userId);

                // Notify user
                $telegram->send(
                    $chatId,
                    "✅ <b>Post Published!</b>\n\n" .
                    "Your post has been published to <b>" . htmlspecialchars($channel['title']) . "</b>",
                    [[['text' => '« Back to Channel', 'callback_data' => 'ch:' . $channelId]]]
                );
            } else {
                $telegram->send($chatId, "❌ Failed to publish post. Make sure the bot has posting rights.");
            }
        } catch (\Exception $e) {
            error_log("Failed to create post: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Show dashboard
     */
    private function showDashboard(Container $container, int $userId, int $chatId, ?int $messageId = null): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channels = $channelService->getUserChannels($userId, 0, 5);
        $total = $channelService->countUserChannels($userId);

        $text = "🎛 <b>Channel Management Dashboard</b>\n\n";

        if (empty($channels)) {
            $text .= "You don't have any channels yet.\n\n";
            $text .= "➕ Add this bot as administrator to your channel to get started!";
            $keyboard = [[['text' => '📖 Help', 'callback_data' => 'help']]];
        } else {
            $text .= "📊 You manage <b>$total</b> channel" . ($total !== 1 ? 's' : '') . "\n\n";
            $text .= "Select a channel:";

            $keyboard = [];
            foreach ($channels as $ch) {
                $emoji = $ch['type'] === 'channel' ? '📢' : '👥';
                $keyboard[] = [['text' => "$emoji " . $ch['title'], 'callback_data' => 'ch:' . $ch['channel_id']]];
            }

            $keyboard[] = [
                ['text' => '📖 Help', 'callback_data' => 'help'],
                ['text' => '🎯 Campaigns', 'callback_data' => 'campaigns']
            ];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show channel menu
     */
    private function showChannelMenu(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId = null): void
    {
        $channelService = $container->make(ChannelService::class);
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $postCount = $postService->countChannelPosts($channelId);

        $text = "📢 <b>" . htmlspecialchars($channel['title']) . "</b>\n\n";
        $text .= "📊 Total posts: $postCount\n\n";
        $text .= "What would you like to do?";

        $keyboard = [
            [['text' => '✍️ New Post', 'callback_data' => 'post:' . $channelId]],
            [['text' => '📋 View Posts', 'callback_data' => 'posts:' . $channelId . ':0']],
            [
                ['text' => '⏰ Schedule', 'callback_data' => 'schedule:' . $channelId],
                ['text' => '📝 Drafts', 'callback_data' => 'drafts:' . $channelId . ':0']
            ],
            [
                ['text' => '📊 Analytics', 'callback_data' => 'analytics:' . $channelId],
                ['text' => '🔧 Settings', 'callback_data' => 'settings:' . $channelId]
            ],
            [
                ['text' => '📡 RSS Feeds', 'callback_data' => 'rss:' . $channelId],
                ['text' => '💾 Backups', 'callback_data' => 'backup:' . $channelId]
            ],
            [['text' => '« Back', 'callback_data' => 'menu']]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show posts
     */
    private function showPosts(Container $container, int $userId, int $chatId, int $channelId, int $offset, ?int $messageId = null): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $posts = $postService->getChannelPosts($channelId, $offset, 5);
        $total = $postService->countChannelPosts($channelId);

        $text = "📋 <b>Posts - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($posts)) {
            $text .= "No posts yet. Create your first post!";
        } else {
            $text .= "Showing " . ($offset + 1) . "-" . min($offset + 5, $total) . " of $total\n\n";
            foreach ($posts as $post) {
                $content = mb_substr($post['content'] ?? 'Media', 0, 50);
                $date = date('M d, H:i', strtotime($post['posted_at']));
                $text .= "• $date\n  " . htmlspecialchars($content) . "\n\n";
            }
        }

        $keyboard = [];
        
        // Pagination
        $navRow = [];
        if ($offset > 0) {
            $navRow[] = ['text' => '« Prev', 'callback_data' => 'posts:' . $channelId . ':' . ($offset - 5)];
        }
        if ($offset + 5 < $total) {
            $navRow[] = ['text' => 'Next »', 'callback_data' => 'posts:' . $channelId . ':' . ($offset + 5)];
        }
        if (!empty($navRow)) {
            $keyboard[] = $navRow;
        }

        $keyboard[] = [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show help
     */
    private function showHelp(Container $container, int $chatId, ?int $messageId = null): void
    {
        $telegram = $container->make(Client::class);

        $text = "📖 <b>Help & Guide</b>\n\n" .
                "<b>Getting Started:</b>\n" .
                "1. Add bot as admin to your channel\n" .
                "2. Use /start to see your channels\n" .
                "3. Select a channel to manage\n\n" .
                "<b>Features:</b>\n" .
                "✍️ Post text & media\n" .
                "⏰ Schedule posts\n" .
                "📝 Save drafts\n" .
                "📊 View analytics\n" .
                "🔧 Configure settings\n\n" .
                "<b>Commands:</b>\n" .
                "/start - Dashboard\n" .
                "/help - This help\n" .
                "/cancel - Cancel operation";

        $keyboard = [[['text' => '« Back', 'callback_data' => 'menu']]];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show post details
     */
    private function showPostDetails(Container $container, int $userId, int $chatId, int $postId, ?int $messageId): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $post = $postService->getPost($postId);

        if (!$post) {
            $telegram->send($chatId, "❌ Post not found");
            return;
        }

        $channel = $channelService->getChannel($post['channel_id']);

        $text = "📋 <b>Post Details</b>\n\n";
        $text .= "Channel: <b>" . htmlspecialchars($channel['title']) . "</b>\n";
        $text .= "Type: " . ucfirst($post['content_type']) . "\n";
        $text .= "Posted: " . date('M d, Y H:i', strtotime($post['posted_at'])) . "\n\n";

        if ($post['content']) {
            $text .= "<b>Content:</b>\n" . htmlspecialchars(mb_substr($post['content'], 0, 200));
            if (mb_strlen($post['content']) > 200) {
                $text .= "...";
            }
        }

        $keyboard = [
            [
                ['text' => '✏️ Edit', 'callback_data' => 'edit_post:' . $postId],
                ['text' => '📌 Pin', 'callback_data' => 'pin_post:' . $postId . ':' . $post['channel_id']]
            ],
            [['text' => '« Back', 'callback_data' => 'posts:' . $post['channel_id'] . ':0']]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Edit post
     */
    private function editPost(Container $container, int $userId, int $chatId, int $postId, array $data): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);
        $userService = $container->make(UserService::class);

        $post = $postService->getPost($postId);

        if (!$post) {
            $telegram->send($chatId, "❌ Post not found");
            return;
        }

        try {
            // Update on Telegram
            $params = [
                'chat_id' => $post['channel_id'],
                'message_id' => $post['message_id']
            ];

            if ($data['content_type'] === 'text') {
                $params['text'] = $data['content'];
                $result = $telegram->editMessageText($params);
            } else {
                $params['caption'] = $data['content'];
                $result = $telegram->editMessageCaption($params);
            }

            if ($result) {
                // Update in database
                $postService->updatePost($postId, $data);
                $userService->clearSession($userId);

                $telegram->send(
                    $chatId,
                    "✅ <b>Post Updated!</b>\n\nYour post has been edited successfully.",
                    [[['text' => '« View Post', 'callback_data' => 'view_post:' . $postId]]]
                );
            } else {
                $telegram->send($chatId, "❌ Failed to edit post");
            }
        } catch (\Exception $e) {
            error_log("Failed to edit post: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Pin post
     */
    private function pinPost(Container $container, int $userId, int $chatId, int $postId, int $channelId): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);

        $post = $postService->getPost($postId);

        if (!$post) {
            $telegram->send($chatId, "❌ Post not found");
            return;
        }

        try {
            $result = $telegram->pinChatMessage([
                'chat_id' => $channelId,
                'message_id' => $post['message_id'],
                'disable_notification' => true
            ]);

            if ($result) {
                $telegram->send($chatId, "✅ Post pinned successfully!");
            } else {
                $telegram->send($chatId, "❌ Failed to pin post. Make sure bot has permission.");
            }
        } catch (\Exception $e) {
            error_log("Failed to pin post: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Unpin post
     */
    private function unpinPost(Container $container, int $userId, int $chatId, int $postId, int $channelId): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);

        $post = $postService->getPost($postId);

        if (!$post) {
            $telegram->send($chatId, "❌ Post not found");
            return;
        }

        try {
            $result = $telegram->unpinChatMessage([
                'chat_id' => $channelId,
                'message_id' => $post['message_id']
            ]);

            if ($result) {
                $telegram->send($chatId, "✅ Post unpinned successfully!");
            } else {
                $telegram->send($chatId, "❌ Failed to unpin post");
            }
        } catch (\Exception $e) {
            error_log("Failed to unpin post: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Create poll
     */
    private function createPoll(Container $container, int $userId, int $chatId, array $data, string $optionsText): void
    {
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);
        $userService = $container->make(UserService::class);

        $options = array_filter(array_map('trim', explode("\n", $optionsText)));

        if (count($options) < 2) {
            $telegram->send($chatId, "❌ Poll must have at least 2 options");
            return;
        }

        if (count($options) > 10) {
            $telegram->send($chatId, "❌ Poll can have maximum 10 options");
            return;
        }

        try {
            $result = $telegram->sendPoll([
                'chat_id' => $data['channel_id'],
                'question' => $data['question'],
                'options' => json_encode($options),
                'is_anonymous' => true
            ]);

            if ($result) {
                // Save post
                $postService->createPost($data['channel_id'], $result['message_id'], $userId, [
                    'content_type' => 'poll',
                    'content' => $data['question']
                ]);

                $userService->clearSession($userId);

                $telegram->send(
                    $chatId,
                    "✅ <b>Poll Created!</b>\n\nYour poll has been posted to the channel.",
                    [[['text' => '« Back to Channel', 'callback_data' => 'ch:' . $data['channel_id']]]]
                );
            } else {
                $telegram->send($chatId, "❌ Failed to create poll");
            }
        } catch (\Exception $e) {
            error_log("Failed to create poll: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }
}
