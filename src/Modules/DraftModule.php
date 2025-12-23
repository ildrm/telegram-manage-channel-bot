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
 * Draft Module
 * 
 * Handles draft creation, editing, and publishing
 */
class DraftModule implements PluginInterface
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

        // View drafts
        if (strpos($data, 'drafts:') === 0) {
            $parts = explode(':', $data);
            $channelId = (int)$parts[1];
            $offset = (int)($parts[2] ?? 0);

            $telegram->answer($query['id']);
            $this->showDrafts($container, $userId, $chatId, $channelId, $offset, $messageId);
            return;
        }

        // Save as draft
        if (strpos($data, 'save_draft:') === 0) {
            $channelId = (int)substr($data, 11);
            
            $telegram->answer($query['id']);
            $userService = $container->make(UserService::class);
            $userService->setSession($userId, 'awaiting_draft', ['channel_id' => $channelId]);

            $telegram->edit(
                $chatId,
                $messageId,
                "📝 <b>Save as Draft</b>\n\n" .
                "Send me the content you want to save as a draft.\n\n" .
                "You can send:\n" .
                "• Text\n" .
                "• Photo with caption\n" .
                "• Video with caption\n" .
                "• Document with caption",
                [[['text' => '❌ Cancel', 'callback_data' => 'ch:' . $channelId]]]
            );
            return;
        }

        // View specific draft
        if (strpos($data, 'draft:') === 0) {
            $draftId = (int)substr($data, 6);
            
            $telegram->answer($query['id']);
            $this->showDraft($container, $userId, $chatId, $draftId, $messageId);
            return;
        }

        // Publish draft
        if (strpos($data, 'publish_draft:') === 0) {
            $draftId = (int)substr($data, 14);
            
            $telegram->answer($query['id']);
            $this->publishDraft($container, $userId, $chatId, $draftId, $messageId);
            return;
        }

        // Delete draft
        if (strpos($data, 'delete_draft:') === 0) {
            $draftId = (int)substr($data, 13);
            
            $telegram->answer($query['id']);
            $this->deleteDraft($container, $userId, $chatId, $draftId);
            return;
        }
    }

    /**
     * Show drafts list
     */
    private function showDrafts(Container $container, int $userId, int $chatId, int $channelId, int $offset, ?int $messageId): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $drafts = $postService->getChannelDrafts($channelId, $userId, $offset, 5);

        $text = "📝 <b>Drafts - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($drafts)) {
            $text .= "No drafts saved yet.\n\n";
            $text .= "💡 Save your work in progress for later!";

            $keyboard = [
                [['text' => '➕ Create Draft', 'callback_data' => 'save_draft:' . $channelId]],
                [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
            ];
        } else {
            $text .= "Your saved drafts:\n\n";
            
            $keyboard = [];
            foreach ($drafts as $draft) {
                $content = mb_substr($draft['content'] ?? 'Media', 0, 30);
                $date = date('M d', strtotime($draft['created_at']));
                $emoji = $this->getContentEmoji($draft['content_type']);
                
                $keyboard[] = [[
                    'text' => "$emoji $date: $content...",
                    'callback_data' => 'draft:' . $draft['id']
                ]];
            }

            $keyboard[] = [['text' => '➕ New Draft', 'callback_data' => 'save_draft:' . $channelId]];
            $keyboard[] = [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show specific draft
     */
    private function showDraft(Container $container, int $userId, int $chatId, int $draftId, ?int $messageId): void
    {
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);

        $draft = $postService->getDraft($draftId);

        if (!$draft || $draft['user_id'] != $userId) {
            $telegram->answer($chatId, "❌ Draft not found");
            return;
        }

        $content = $draft['content'] ?? 'Media content';
        $type = $draft['content_type'];
        $emoji = $this->getContentEmoji($type);

        $text = "📝 <b>Draft Preview</b>\n\n";
        $text .= "Type: $emoji " . ucfirst($type) . "\n";
        $text .= "Created: " . date('M d, Y H:i', strtotime($draft['created_at'])) . "\n\n";
        $text .= "Content:\n" . htmlspecialchars(mb_substr($content, 0, 200));

        if (mb_strlen($content) > 200) {
            $text .= "...";
        }

        $keyboard = [
            [['text' => '📤 Publish Now', 'callback_data' => 'publish_draft:' . $draftId]],
            [['text' => '🗑 Delete', 'callback_data' => 'delete_draft:' . $draftId]],
            [['text' => '« Back', 'callback_data' => 'drafts:' . $draft['channel_id'] . ':0']]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Publish draft
     */
    private function publishDraft(Container $container, int $userId, int $chatId, int $draftId, ?int $messageId): void
    {
        $postService = $container->make(PostService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $draft = $postService->getDraft($draftId);

        if (!$draft || $draft['user_id'] != $userId) {
            $telegram->send($chatId, "❌ Draft not found");
            return;
        }

        $channel = $channelService->getChannel($draft['channel_id']);

        try {
            // Publish to channel
            $params = ['chat_id' => $draft['channel_id']];

            switch ($draft['content_type']) {
                case 'photo':
                    $params['photo'] = $draft['media_id'];
                    $params['caption'] = $draft['content'];
                    $result = $telegram->sendPhoto($params);
                    break;

                case 'video':
                    $params['video'] = $draft['media_id'];
                    $params['caption'] = $draft['content'];
                    $result = $telegram->sendVideo($params);
                    break;

                case 'document':
                    $params['document'] = $draft['media_id'];
                    $params['caption'] = $draft['content'];
                    $result = $telegram->sendDocument($params);
                    break;

                default: // text
                    $params['text'] = $draft['content'];
                    $result = $telegram->sendMessage($params);
                    break;
            }

            if ($result) {
                // Save as post
                $postService->createPost($draft['channel_id'], $result['message_id'], $userId, [
                    'content_type' => $draft['content_type'],
                    'content' => $draft['content'],
                    'media_id' => $draft['media_id']
                ]);

                // Delete draft
                $postService->deleteDraft($draftId);

                // Notify
                $telegram->edit(
                    $chatId,
                    $messageId,
                    "✅ <b>Draft Published!</b>\n\n" .
                    "Your draft has been published to <b>" . htmlspecialchars($channel['title']) . "</b>",
                    [[['text' => '« Back to Channel', 'callback_data' => 'ch:' . $draft['channel_id']]]]
                );
            } else {
                $telegram->send($chatId, "❌ Failed to publish draft");
            }
        } catch (\Exception $e) {
            error_log("Failed to publish draft: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Delete draft
     */
    private function deleteDraft(Container $container, int $userId, int $chatId, int $draftId): void
    {
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);

        $draft = $postService->getDraft($draftId);

        if (!$draft || $draft['user_id'] != $userId) {
            $telegram->send($chatId, "❌ Draft not found");
            return;
        }

        $postService->deleteDraft($draftId);

        $telegram->send(
            $chatId,
            "✅ Draft deleted",
            [[['text' => '« Back', 'callback_data' => 'drafts:' . $draft['channel_id'] . ':0']]]
        );
    }

    /**
     * Get emoji for content type
     */
    private function getContentEmoji(string $type): string
    {
        $emojis = [
            'text' => '📝',
            'photo' => '🖼',
            'video' => '🎥',
            'document' => '📄',
            'audio' => '🎵'
        ];

        return $emojis[$type] ?? '📄';
    }
}
