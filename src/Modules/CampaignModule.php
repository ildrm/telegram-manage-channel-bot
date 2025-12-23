<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\CampaignService;
use App\Services\UserService;
use App\Services\ChannelService;
use App\Telegram\Client;

/**
 * Campaign Module
 * 
 * Complete campaign management with UI
 */
class CampaignModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(CampaignService::class);
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
        $userService = $container->make(UserService::class);

        // View campaigns
        if ($data === 'campaigns') {
            $telegram->answer($query['id']);
            $this->showCampaigns($container, $userId, $chatId, $messageId);
            return;
        }

        // Create campaign
        if ($data === 'create_campaign') {
            $telegram->answer($query['id']);
            $userService->setSession($userId, 'awaiting_campaign_name', []);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "📊 <b>Create New Campaign</b>\n\nSend me the campaign name:",
                [[['text' => '❌ Cancel', 'callback_data' => 'campaigns']]]
            );
            return;
        }

        // View campaign
        if (strpos($data, 'campaign:') === 0) {
            $campaignId = (int)substr($data, 9);
            $telegram->answer($query['id']);
            $this->showCampaignDetails($container, $userId, $chatId, $campaignId, $messageId);
            return;
        }

        // Start campaign
        if (strpos($data, 'start_campaign:') === 0) {
            $campaignId = (int)substr($data, 15);
            $telegram->answer($query['id']);
            
            $campaignService = $container->make(CampaignService::class);
            $campaignService->startCampaign($campaignId);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "✅ Campaign started!",
                [[['text' => '« Back', 'callback_data' => 'campaign:' . $campaignId]]]
            );
            return;
        }

        // End campaign
        if (strpos($data, 'end_campaign:') === 0) {
            $campaignId = (int)substr($data, 13);
            $telegram->answer($query['id']);
            
            $campaignService = $container->make(CampaignService::class);
            $campaignService->endCampaign($campaignId);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "✅ Campaign ended!",
                [[['text' => '« Back', 'callback_data' => 'campaigns']]]
            );
            return;
        }
    }

    /**
     * Handle text input
     */
    public function handleText(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if (strpos($text, '/') === 0) {
            return; // Skip commands
        }

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session) {
            return;
        }

        // Create campaign
        if ($session['state'] === 'awaiting_campaign_name') {
            $this->createCampaign($container, $userId, $chatId, $text);
            return;
        }
    }

    /**
     * Show campaigns list
     */
    private function showCampaigns(Container $container, int $userId, int $chatId, ?int $messageId): void
    {
        $campaignService = $container->make(CampaignService::class);
        $telegram = $container->make(Client::class);

        $campaigns = $campaignService->getUserCampaigns($userId);

        $text = "📊 <b>Campaigns</b>\n\n";

        if (empty($campaigns)) {
            $text .= "No campaigns yet. Create your first campaign!";
            $keyboard = [
                [['text' => '➕ Create Campaign', 'callback_data' => 'create_campaign']],
                [['text' => '« Back', 'callback_data' => 'menu']]
            ];
        } else {
            $text .= "Your campaigns:\n\n";

            $keyboard = [];
            foreach ($campaigns as $campaign) {
                $status = ['draft' => '📝', 'active' => '▶️', 'completed' => '✅'][$campaign['status']] ?? '•';
                $text .= "$status <b>" . htmlspecialchars($campaign['name']) . "</b>\n";
                $text .= "   Status: " . ucfirst($campaign['status']) . "\n";
                $text .= "   Posts: " . $campaign['post_count'] . "\n\n";

                $keyboard[] = [['text' => $campaign['name'], 'callback_data' => 'campaign:' . $campaign['id']]];
            }

            $keyboard[] = [['text' => '➕ Create Campaign', 'callback_data' => 'create_campaign']];
            $keyboard[] = [['text' => '« Back', 'callback_data' => 'menu']];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show campaign details
     */
    private function showCampaignDetails(Container $container, int $userId, int $chatId, int $campaignId, ?int $messageId): void
    {
        $campaignService = $container->make(CampaignService::class);
        $telegram = $container->make(Client::class);

        $campaign = $campaignService->getCampaign($campaignId);

        if (!$campaign || $campaign['user_id'] != $userId) {
            $telegram->send($chatId, "❌ Campaign not found");
            return;
        }

        $text = "📊 <b>" . htmlspecialchars($campaign['name']) . "</b>\n\n";
        $text .= "Status: " . ucfirst($campaign['status']) . "\n";
        $text .= "Posts: " . $campaign['post_count'] . "\n";
        
        if ($campaign['start_date']) {
            $text .= "Started: " . date('M d, Y', strtotime($campaign['start_date'])) . "\n";
        }
        
        if ($campaign['end_date']) {
            $text .= "Ended: " . date('M d, Y', strtotime($campaign['end_date'])) . "\n";
        }

        $keyboard = [];

        if ($campaign['status'] === 'draft') {
            $keyboard[] = [['text' => '▶️ Start Campaign', 'callback_data' => 'start_campaign:' . $campaignId]];
        } elseif ($campaign['status'] === 'active') {
            $keyboard[] = [['text' => '⏹️ End Campaign', 'callback_data' => 'end_campaign:' . $campaignId]];
        }

        $keyboard[] = [['text' => '« Back', 'callback_data' => 'campaigns']];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Create campaign
     */
    private function createCampaign(Container $container, int $userId, int $chatId, string $name): void
    {
        $campaignService = $container->make(CampaignService::class);
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);

        $campaignId = $campaignService->createCampaign($userId, $name);

        $userService->clearSession($userId);

        $telegram->send(
            $chatId,
            "✅ <b>Campaign Created!</b>\n\nCampaign '<b>" . htmlspecialchars($name) . "</b>' has been created.",
            [[['text' => 'View Campaign', 'callback_data' => 'campaign:' . $campaignId]]]
        );
    }
}
