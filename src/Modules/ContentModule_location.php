
    /**
     * Handle location messages
     */
    public function handleLocation(array $message, array $update, Container $container): void
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

        $location = $message['location'];
        $telegram = $container->make(Client::class);
        $postService = $container->make(PostService::class);
        $channelId = $session['data']['channel_id'];

        try {
            // Post location to channel
            $result = $telegram->sendLocation([
                'chat_id' => $channelId,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'live_period' => $session['data']['live_period'] ?? null
            ]);

            if ($result) {
                $postService->createPost($channelId, $result['message_id'], $userId, [
                    'content_type' => 'location',
                    'content' => json_encode([
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude']
                    ])
                ]);

                $userService->clearSession($userId);

                $telegram->send(
                    $chatId,
                    "✅ <b>Location Posted!</b>\n\nYour location has been shared to the channel.",
                    [[['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to post location: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error: " . $e->getMessage());
        }
    }
