<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\PostService;
use App\Services\ChannelService;
use App\Services\AuthorizationService;
use App\Services\UserService;
use App\Telegram\Client;

/**
 * Scheduling Module
 * 
 * Handles post scheduling, recurring posts, and editorial calendar
 */
class SchedulingModule implements PluginInterface
{
    public function register(Container $container): void
    {
        // Services already registered
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

        // Schedule new post
        if (strpos($data, 'schedule:') === 0) {
            $channelId = (int)substr($data, 9);
            
            $telegram->answer($query['id']);
            $userService = $container->make(UserService::class);
            $userService->setSession($userId, 'awaiting_schedule_content', ['channel_id' => $channelId]);

            $telegram->edit(
                $chatId,
                $messageId,
                "⏰ <b>Schedule New Post</b>\n\n" .
                "First, send me the content you want to schedule:\n\n" .
                "📝 Text message\n" .
                "🖼 Photo with caption\n" .
                "🎥 Video with caption\n" .
                "📄 Document with caption",
                [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $channelId]]]
            );
            return;
        }

        // View scheduled posts
        if (strpos($data, 'scheduled:') === 0) {
            $parts = explode(':', $data);
            $channelId = (int)$parts[1];
            $offset = (int)($parts[2] ?? 0);

            $telegram->answer($query['id']);
            $this->showScheduled($container, $userId, $chatId, $channelId, $offset, $messageId);
            return;
        }

        // Cancel scheduled post
        if (strpos($data, 'cancel_scheduled:') === 0) {
            $scheduledId = (int)substr($data, 17);
            
            $telegram->answer($query['id']);
            $this->cancelScheduled($container, $userId, $chatId, $scheduledId);
            return;
        }

        // Set schedule time buttons
        if (strpos($data, 'schedule_time:') === 0) {
            $parts = explode(':', $data);
            $timeOption = $parts[1];
            
            $telegram->answer($query['id']);
            $this->handleTimeSelection($container, $userId, $chatId, $timeOption, $messageId);
            return;
        }
    }

    /**
     * Handle text for scheduling
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

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session) {
            return;
        }

        // Awaiting schedule content
        if ($session['state'] === 'awaiting_schedule_content') {
            $this->processScheduleContent($container, $userId, $chatId, $message);
        }

        // Awaiting custom time
        if ($session['state'] === 'awaiting_schedule_time') {
            $this->processScheduleTime($container, $userId, $chatId, $message['text']);
        }
    }

    /**
     * Process schedule content
     */
    private function processScheduleContent(Container $container, int $userId, int $chatId, array $message): void
    {
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);
        $session = $userService->getSession($userId);

        // Prepare content data
        $contentData = [];

        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $photo = end($photos);
            $contentData = [
                'content_type' => 'photo',
                'media_id' => $photo['file_id'],
                'content' => $message['caption'] ?? ''
            ];
        } elseif (isset($message['video'])) {
            $contentData = [
                'content_type' => 'video',
                'media_id' => $message['video']['file_id'],
                'content' => $message['caption'] ?? ''
            ];
        } elseif (isset($message['document'])) {
            $contentData = [
                'content_type' => 'document',
                'media_id' => $message['document']['file_id'],
                'content' => $message['caption'] ?? ''
            ];
        } else {
            $contentData = [
                'content_type' => 'text',
                'content' => $message['text'] ?? ''
            ];
        }

        // Store content and ask for time
        $session['data']['content'] = $contentData;
        $userService->setSession($userId, 'awaiting_schedule_time_select', $session['data']);

        $telegram->send(
            $chatId,
            "✅ Content saved!\n\n⏰ When would you like to post this?",
            [
                [['text' => '⏱ In 1 hour', 'callback_data' => 'schedule_time:1h']],
                [['text' => '🕐 In 3 hours', 'callback_data' => 'schedule_time:3h']],
                [['text' => '📅 Tomorrow 9 AM', 'callback_data' => 'schedule_time:tomorrow']],
                [['text' => '📆 Custom date/time', 'callback_data' => 'schedule_time:custom']],
                [['text' => '❌ Cancel', 'callback_data' => 'ch:' . $session['data']['channel_id']]]
            ]
        );
    }

    /**
     * Handle time selection
     */
    private function handleTimeSelection(Container $container, int $userId, int $chatId, string $timeOption, ?int $messageId): void
    {
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);
        $session = $userService->getSession($userId);

        if (!$session || !isset($session['data']['content'])) {
            $telegram->send($chatId, "❌ Session expired. Please start over.");
            return;
        }

        $scheduleTime = null;

        switch ($timeOption) {
            case '1h':
                $scheduleTime = time() + 3600;
                break;
            case '3h':
                $scheduleTime = time() + (3 * 3600);
                break;
            case 'tomorrow':
                $scheduleTime = strtotime('tomorrow 9:00');
                break;
            case 'custom':
                $userService->setSession($userId, 'awaiting_schedule_time', $session['data']);
                $telegram->edit(
                    $chatId,
                    $messageId,
                    "📆 <b>Custom Date/Time</b>\n\n" .
                    "Send me the date and time in format:\n\n" .
                    "<code>YYYY-MM-DD HH:MM</code>\n\n" .
                    "Example: <code>2025-12-25 14:30</code>",
                    [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $session['data']['channel_id']]]]
                );
                return;
        }

        if ($scheduleTime) {
            $this->createScheduledPost($container, $userId, $chatId, $session['data'], $scheduleTime, $messageId);
        }
    }

    /**
     * Process custom schedule time
     */
    private function processScheduleTime(Container $container, int $userId, int $chatId, string $text): void
    {
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);
        $session = $userService->getSession($userId);

        if (!$session || !isset($session['data']['content'])) {
            $telegram->send($chatId, "❌ Session expired. Please start over.");
            return;
        }

        // Parse date/time
        $scheduleTime = strtotime($text);

        if (!$scheduleTime || $scheduleTime < time()) {
            $telegram->send(
                $chatId,
                "❌ Invalid date/time. Please use format: <code>YYYY-MM-DD HH:MM</code>\n\n" .
                "The time must be in the future.",
                [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $session['data']['channel_id']]]]
            );
            return;
        }

        $this->createScheduledPost($container, $userId, $chatId, $session['data'], $scheduleTime);
    }

    /**
     * Create scheduled post
     */
    private function createScheduledPost(Container $container, int $userId, int $chatId, array $data, int $scheduleTime, ?int $messageId = null): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($data['channel_id']);

        try {
            $postService->createScheduledPost(
                $data['channel_id'],
                $userId,
                $scheduleTime,
                $data['content']
            );

            $userService->clearSession($userId);

            $text = "✅ <b>Post Scheduled!</b>\n\n" .
                    "Your post will be published to <b>" . htmlspecialchars($channel['title']) . "</b>\n\n" .
                    "📅 <b>Schedule time:</b>\n" .
                    date('F d, Y @ H:i', $scheduleTime) . "\n\n" .
                    "💡 The post will be automatically published at the scheduled time.";

            $keyboard = [[['text' => '« Back to Channel', 'callback_data' => 'ch:' . $data['channel_id']]]];

            if ($messageId) {
                $telegram->edit($chatId, $messageId, $text, $keyboard);
            } else {
                $telegram->send($chatId, $text, $keyboard);
            }
        } catch (\Exception $e) {
            error_log("Failed to schedule post: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Show scheduled posts
     */
    private function showScheduled(Container $container, int $userId, int $chatId, int $channelId, int $offset, ?int $messageId): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $scheduled = $postService->getChannelScheduledPosts($channelId, $offset, 5);

        $text = "📅 <b>Scheduled Posts - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($scheduled)) {
            $text .= "No scheduled posts.\n\n";
            $text .= "💡 Schedule posts to publish them automatically!";

            $keyboard = [
                [['text' => '➕ Schedule Post', 'callback_data' => 'schedule:' . $channelId]],
                [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
            ];
        } else {
            $text .= "Upcoming posts:\n\n";

            foreach ($scheduled as $post) {
                $content = mb_substr($post['content'] ?? 'Media', 0, 40);
                $time = date('M d, H:i', strtotime($post['schedule_time']));
                $text .= "⏱ $time\n";
                $text .= "  " . htmlspecialchars($content) . "\n";
                $text .= "  <code>/cancel_" . $post['id'] . "</code>\n\n";
            }

            $keyboard = [
                [['text' => '➕ Schedule New', 'callback_data' => 'schedule:' . $channelId]],
                [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
            ];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Cancel scheduled post
     */
    private function cancelScheduled(Container $container, int $userId, int $chatId, int $scheduledId): void
    {
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);

        $scheduled = $postService->getScheduledPost($scheduledId);

        if (!$scheduled) {
            $telegram->send($chatId, "❌ Scheduled post not found");
            return;
        }

        $postService->deleteScheduledPost($scheduledId);

        $telegram->send(
            $chatId,
            "✅ Scheduled post cancelled",
            [[['text' => '« Back', 'callback_data' => 'scheduled:' . $scheduled['channel_id'] . ':0']]]
        );
    }
}
