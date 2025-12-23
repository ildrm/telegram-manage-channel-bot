<?php
declare(strict_types=1);

/**
 * Webhook Entry Point
 * 
 * Receives incoming Telegram updates and processes them
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Bot;
use App\Core\Config;

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/error.log');

// Handle setup request
if (isset($_GET['setup']) && $_GET['setup'] === '1') {
    try {
        $bot = new Bot();
        
        if ($bot->setWebhook()) {
            $me = $bot->getMe();
            echo "✅ Webhook set successfully!\n\n";
            echo "Bot: @" . ($me['username'] ?? 'Unknown') . "\n";
            echo "Webhook URL: " . $bot->getConfig()->get('WEBHOOK_URL') . "\n\n";
            echo "Your bot is now ready to receive updates!";
        } else {
            http_response_code(500);
            echo "❌ Failed to set webhook. Check your configuration.";
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo "❌ Error: " . $e->getMessage();
    }
    exit;
}

// Handle webhook info request
if (isset($_GET['info'])) {
    try {
        $bot = new Bot();
        $info = $bot->getWebhookInfo();
        
        header('Content-Type: application/json');
        echo json_encode($info, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle cron request (for scheduled posts)
if (isset($_GET['cron']) && $_GET['cron'] === '1') {
    try {
        $bot = new Bot();
        $config = $bot->getConfig();
        
        // Verify cron secret if set
        $cronSecret = $config->get('CRON_SECRET');
        if ($cronSecret && ($_GET['secret'] ?? '') !== $cronSecret) {
            http_response_code(403);
            echo "❌ Invalid secret";
            exit;
        }

        // Process scheduled posts
        $container = $bot->getContainer();
        $postService = $container->make(\App\Services\PostService::class);
        $telegram = $container->make(\App\Telegram\Client::class);
        $channelService = $container->make(\App\Services\ChannelService::class);

        $scheduled = $postService->getPendingScheduledPosts(50);
        $posted = 0;

        foreach ($scheduled as $post) {
            try {
                // Prepare content
                $params = [
                    'chat_id' => $post['channel_id'],
                ];

                // Add content based on type
                switch ($post['content_type']) {
                    case 'photo':
                        $params['photo'] = $post['media_id'];
                        $params['caption'] = $post['content'];
                        $result = $telegram->sendPhoto($params);
                        break;

                    case 'video':
                        $params['video'] = $post['media_id'];
                        $params['caption'] = $post['content'];
                        $result = $telegram->sendVideo($params);
                        break;

                    case 'document':
                        $params['document'] = $post['media_id'];
                        $params['caption'] = $post['content'];
                        $result = $telegram->sendDocument($params);
                        break;

                    default: // text
                        $params['text'] = $post['content'];
                        $result = $telegram->sendMessage($params);
                        break;
                }

                if ($result) {
                    // Create post record
                    $postService->createPost(
                        $post['channel_id'],
                        $result['message_id'],
                        $post['user_id'],
                        [
                            'campaign_id' => $post['campaign_id'],
                            'content_type' => $post['content_type'],
                            'content' => $post['content'],
                            'media_id' => $post['media_id'],
                            'approval_status' => 'approved'
                        ]
                    );

                    // Handle recurring
                    if ($post['recurring']) {
                        $recurring = json_decode($post['recurring'], true);
                        // Calculate next time based on recurring schedule
                        // For simplicity, we'll just mark as completed
                        $postService->updateScheduledStatus($post['id'], 'completed');
                    } else {
                        $postService->updateScheduledStatus($post['id'], 'completed');
                    }

                    $posted++;
                } else {
                    $postService->updateScheduledStatus($post['id'], 'failed');
                }
            } catch (Exception $e) {
                error_log("Failed to post scheduled: " . $e->getMessage());
                $postService->updateScheduledStatus($post['id'], 'failed');
            }
        }

        // Process RSS feeds
        $rssPosted = \App\Modules\RSSModule::processFeed($container);

        echo "✅ Processed $posted scheduled posts\n";
        echo "✅ Posted $rssPosted items from RSS feeds";
    } catch (Exception $e) {
        http_response_code(500);
        echo "❌ Error: " . $e->getMessage();
    }
    exit;
}

// Verify request is from Telegram
$config = Config::getInstance();
$secretToken = $config->get('WEBHOOK_SECRET');

if ($secretToken) {
    $headerToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($headerToken !== $secretToken) {
        http_response_code(403);
        exit('Unauthorized');
    }
}

// Get incoming update
$input = file_get_contents('php://input');

if (empty($input)) {
    http_response_code(200);
    exit('OK');
}

$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Log update if debug mode
if ($config->isDebug()) {
    $logFile = dirname(__DIR__) . '/storage/logs/updates.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $input . PHP_EOL, FILE_APPEND);
}

// Process update
try {
    $bot = new Bot();
    $bot->processUpdate($update);
    
    http_response_code(200);
    echo 'OK';
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    exit('Error processing update');
}
