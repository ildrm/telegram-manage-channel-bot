<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\AnalyticsService;
use App\Services\HealthMonitoringService;
use App\Services\ChannelService;
use App\Telegram\Client;

/**
 * Dashboard Module
 * 
 * Advanced analytics dashboard
 */
class DashboardModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(AnalyticsService::class);
        $container->singleton(HealthMonitoringService::class);
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

        // Main dashboard
        if ($data === 'dashboard') {
            $telegram->answer($query['id']);
            $this->showDashboard($container, $userId, $chatId, $messageId);
            return;
        }

        // Channel dashboard
        if (strpos($data, 'dashboard:') === 0) {
            $channelId = (int)substr($data, 10);
            $telegram->answer($query['id']);
            $this->showChannelDashboard($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Export report
        if (strpos($data, 'export_report:') === 0) {
            $channelId = (int)substr($data, 14);
            $telegram->answer($query['id'], "📥 Generating report...");
            $this->exportReport($container, $userId, $chatId, $channelId);
            return;
        }
    }

    /**
     * Show main dashboard
     */
    private function showDashboard(Container $container, int $userId, int $chatId, ?int $messageId): void
    {
        $healthService = $container->make(HealthMonitoringService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        // Get cross-channel analytics
        $analytics = $healthService->getCrossChannelAnalytics($userId);
        
        $text = "📊 <b>Analytics Dashboard</b>\n\n";

        if (empty($analytics)) {
            $text .= "No data available yet.";
            $keyboard = [[['text' => '« Back', 'callback_data' => 'menu']]];
        } else {
            $totalPosts = array_sum(array_column($analytics, 'total_posts'));
            $totalViews = array_sum(array_column($analytics, 'total_views'));
            $avgEngagement = array_sum(array_column($analytics, 'avg_engagement')) / count($analytics);

            $text .= "📈 <b>Overall Stats</b>\n";
            $text .= "Total Posts: {$totalPosts}\n";
            $text .= "Total Views: " . number_format($totalViews) . "\n";
            $text .= "Avg Engagement: " . round($avgEngagement, 2) . "%\n\n";

            $text .= "<b>Top Channels:</b>\n\n";

            $keyboard = [];
            $count = 0;
            foreach ($analytics as $channel) {
                if ($count++ >= 5) break;

                $text .= "📢 <b>" . htmlspecialchars($channel['title']) . "</b>\n";
                $text .= "   Posts: {$channel['total_posts']} | Views: " . number_format($channel['total_views']) . "\n\n";

                $keyboard[] = [[
                    'text' => '📊 ' . $channel['title'],
                    'callback_data' => 'dashboard:' . $channel['channel_id']
                ]];
            }

            $keyboard[] = [['text' => '« Back', 'callback_data' => 'menu']];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Show channel-specific dashboard
     */
    private function showChannelDashboard(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $analyticsService = $container->make(AnalyticsService::class);
        $healthService = $container->make(HealthMonitoringService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $health = $healthService->getHealthScore($channelId);
        $performance = $analyticsService->getContentPerformance($channelId);
        $bestTimes = $analyticsService->getBestPostingTimes($channelId);

        $text = "📊 <b>Channel Dashboard</b>\n";
        $text .= "<b>" . htmlspecialchars($channel['title']) . "</b>\n\n";

        // Health Score
        $text .= "🏥 <b>Health Score: {$health['score']}/100</b> (Grade: {$health['grade']})\n";
        $text .= "Status: " . ucfirst($health['status']) . "\n\n";

        // Content Performance
        if (!empty($performance)) {
            $text .= "📈 <b>Content Performance:</b>\n";
            foreach ($performance as $perf) {
                $type = ucfirst($perf['content_type']);
                $avgViews = round($perf['avg_views'] ?? 0);
                $text .= "• {$type}: {$perf['count']} posts, avg {$avgViews} views\n";
            }
            $text .= "\n";
        }

        // Best Times
        if (!empty($bestTimes)) {
            $text .= "⏰ <b>Best Posting Times:</b>\n";
            foreach (array_slice($bestTimes, 0, 3) as $time) {
                $hour = str_pad($time['hour'], 2, '0', STR_PAD_LEFT);
                $engagement = round($time['avg_engagement'], 2);
                $text .= "• {$hour}:00 ({$engagement}% engagement)\n";
            }
$text .= "\n";
        }

        // Recommendations
        if (!empty($health['recommendations'])) {
            $text .= "💡 <b>Recommendations:</b>\n";
            foreach (array_slice($health['recommendations'], 0, 3) as $rec) {
                $text .= "• " . $rec . "\n";
            }
        }

        $keyboard = [
            [['text' => '📥 Export Report', 'callback_data' => 'export_report:' . $channelId]],
            [['text' => '« Back', 'callback_data' => 'dashboard']]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Export analytics report
     */
    private function exportReport(Container $container, int $userId, int $chatId, int $channelId): void
    {
        $analyticsService = $container->make(AnalyticsService::class);
        $telegram = $container->make(Client::class);

        $report = $analyticsService->exportReport($channelId, 'json');

        // Save to temp file
        $filename = "channel_{$channelId}_report_" . date('Y-m-d') . ".json";
        $filepath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($filepath, $report);

        // Send as document
        $telegram->sendDocument([
            'chat_id' => $chatId,
            'document' => new \CURLFile($filepath),
            'caption' => "📊 Analytics Report\nGenerated: " . date('Y-m-d H:i:s')
        ]);

        // Clean up
        unlink($filepath);
    }
}
