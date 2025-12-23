<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Container;
use App\Services\ChannelService;
use App\Services\PostService;
use App\Services\UserService;

/**
 * REST API Controller
 * 
 * Provides programmatic access to bot features
 */
class ApiController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Handle API request
     */
    public function handle(string $method, string $endpoint, array $data, string $apiKey): array
    {
        // Verify API key
        if (!$this->verifyApiKey($apiKey)) {
            return $this->error('Invalid API key', 401);
        }

        $userId = $this->getUserFromApiKey($apiKey);

        // Route to appropriate handler
        switch ($endpoint) {
            case '/channels':
                return $method === 'GET' ? $this->listChannels($userId) : $this->error('Method not allowed', 405);

            case '/posts':
                if ($method === 'POST') {
                    return $this->createPost($userId, $data);
                } elseif ($method === 'GET') {
                    return $this->listPosts($userId, $data);
                }
                return $this->error('Method not allowed', 405);

            case '/schedule':
                return $method === 'POST' ? $this->schedulePost($userId, $data) : $this->error('Method not allowed', 405);

            default:
                return $this->error('Endpoint not found', 404);
        }
    }

    /**
     * List user's channels
     */
    private function listChannels(int $userId): array
    {
        $channelService = $this->container->make(ChannelService::class);
        $channels = $channelService->getUserChannels($userId, 0, 100);

        return $this->success($channels);
    }

    /**
     * Create post via API
     */
    private function createPost(int $userId, array $data): array
    {
        if (!isset($data['channel_id']) || !isset($data['content'])) {
            return $this->error('Missing required fields', 400);
        }

        $telegram = $this->container->make(\App\Telegram\Client::class);
        $postService = $this->container->make(PostService::class);

        try {
            $result = $telegram->sendMessage([
                'chat_id' => $data['channel_id'],
                'text' => $data['content']
            ]);

            if ($result) {
                $postId = $postService->createPost(
                    $data['channel_id'],
                    $result['message_id'],
                    $userId,
                    ['content_type' => 'text', 'content' => $data['content']]
                );

                return $this->success(['post_id' => $postId, 'message_id' => $result['message_id']]);
            }

            return $this->error('Failed to create post', 500);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * List posts
     */
    private function listPosts(int $userId, array $params): array
    {
        if (!isset($params['channel_id'])) {
            return $this->error('channel_id required', 400);
        }

        $postService = $this->container->make(PostService::class);
        $posts = $postService->getChannelPosts($params['channel_id'], 0, 50);

        return $this->success($posts);
    }

    /**
     * Schedule post via API
     */
    private function schedulePost(int $userId, array $data): array
    {
        if (!isset($data['channel_id']) || !isset($data['content']) || !isset($data['schedule_time'])) {
            return $this->error('Missing required fields', 400);
        }

        $postService = $this->container->make(PostService::class);

        try {
            $scheduleTime = strtotime($data['schedule_time']);
            
            if ($scheduleTime < time()) {
                return $this->error('Schedule time must be in the future', 400);
            }

            $scheduledId = $postService->createScheduledPost(
                $data['channel_id'],
                $userId,
                $scheduleTime,
                ['content_type' => 'text', 'content' => $data['content']]
            );

            return $this->success(['scheduled_id' => $scheduledId]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Verify API key
     */
    private function verifyApiKey(string $apiKey): bool
    {
        // In production, verify against database
        // For now, simple check
        return !empty($apiKey) && strlen($apiKey) >= 32;
    }

    /**
     * Get user ID from API key
     */
    private function getUserFromApiKey(string $apiKey): int
    {
        // In production, look up in database
        // For now, return a test user ID
        return 123456; // This should be replaced with actual lookup
    }

    /**
     * Success response
     */
    private function success($data): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Error response
     */
    private function error(string $message, int $code): array
    {
        return [
            'success' => false,
            'error' => $message,
            'code' => $code
        ];
    }
}
