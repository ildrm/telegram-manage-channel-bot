<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\ApprovalService;
use App\Services\ChannelService;
use App\Telegram\Client;

/**
 * Approval Module
 * 
 * Manages post approval workflows
 */
class ApprovalModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(ApprovalService::class);
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

        // View pending approvals
        if (strpos($data, 'approvals:') === 0) {
            $channelId = (int)substr($data, 10);
            $telegram->answer($query['id']);
            $this->showApprovals($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Approve post
        if (strpos($data, 'approve:') === 0) {
            $approvalId = (int)substr($data, 8);
            $telegram->answer($query['id'], "✅ Post approved!");
            
            $approvalService = $container->make(ApprovalService::class);
            $approvalService->approvePost($approvalId, $userId);
            
            // Refresh view
            $telegram->edit($chatId, $messageId, "✅ <b>Approved!</b>\n\nThe post has been approved.", []);
            return;
        }

        // Reject post
        if (strpos($data, 'reject:') === 0) {
            $approvalId = (int)substr($data, 7);
            $telegram->answer($query['id'], "❌ Post rejected");
            
            $approvalService = $container->make(ApprovalService::class);
            $approvalService->rejectPost($approvalId, $userId, "Rejected via bot");
            
            // Refresh view
            $telegram->edit($chatId, $messageId, "❌ <b>Rejected</b>\n\nThe post has been rejected.", []);
            return;
        }
    }

    /**
     * Show pending approvals
     */
    private function showApprovals(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $approvalService = $container->make(ApprovalService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $approvals = $approvalService->getPendingApprovals($userId, $channelId);

        $text = "✅ <b>Pending Approvals - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($approvals)) {
            $text .= "No pending approvals.\n\n";
            $text .= "💡 All posts are up to date!";

            $keyboard = [[['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]];
        } else {
            $text .= "Posts waiting for your review:\n\n";

            $keyboard = [];
            foreach ($approvals as $approval) {
                $content = mb_substr($approval['content'] ?? 'Media', 0, 40);
                $emoji = $this->getContentEmoji($approval['content_type']);

                $text .= "$emoji " . htmlspecialchars($content) . "...\n";
                $text .= "  Workflow: {$approval['workflow_name']}\n\n";

                $keyboard[] = [
                    ['text' => '✅ Approve', 'callback_data' => 'approve:' . $approval['id']],
                    ['text' => '❌ Reject', 'callback_data' => 'reject:' . $approval['id']]
                ];
            }

            $keyboard[] = [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
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
            'document' => '📄'
        ];

        return $emojis[$type] ?? '📄';
    }
}
