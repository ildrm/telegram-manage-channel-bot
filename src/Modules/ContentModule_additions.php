
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
