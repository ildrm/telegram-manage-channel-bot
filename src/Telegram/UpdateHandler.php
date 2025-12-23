<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Core\Container;
use App\Core\PluginManager;

/**
 * Update Handler
 * 
 * Routes incoming Telegram updates to appropriate handlers
 */
class UpdateHandler
{
    private Container $container;
    private PluginManager $pluginManager;

    public function __construct(Container $container, PluginManager $pluginManager)
    {
        $this->container = $container;
        $this->pluginManager = $pluginManager;
    }

    /**
     * Process incoming update
     */
    public function handle(array $update): void
    {
        // Dispatch general update event
        $this->pluginManager->dispatch('update.received', $update, $this->container);

        // Route to specific handler based on update type
        if (isset($update['message'])) {
            $this->handleMessage($update['message'], $update);
        } elseif (isset($update['edited_message'])) {
            $this->handleEditedMessage($update['edited_message'], $update);
        } elseif (isset($update['channel_post'])) {
            $this->handleChannelPost($update['channel_post'], $update);
        } elseif (isset($update['edited_channel_post'])) {
            $this->handleEditedChannelPost($update['edited_channel_post'], $update);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $update);
        } elseif (isset($update['inline_query'])) {
            $this->handleInlineQuery($update['inline_query'], $update);
        } elseif (isset($update['chosen_inline_result'])) {
            $this->handleChosenInlineResult($update['chosen_inline_result'], $update);
        } elseif (isset($update['my_chat_member'])) {
            $this->handleMyChatMember($update['my_chat_member'], $update);
        } elseif (isset($update['chat_member'])) {
            $this->handleChatMember($update['chat_member'], $update);
        } elseif (isset($update['poll'])) {
            $this->handlePoll($update['poll'], $update);
        } elseif (isset($update['poll_answer'])) {
            $this->handlePollAnswer($update['poll_answer'], $update);
        }
    }

    /**
     * Handle regular message
     */
    private function handleMessage(array $message, array $update): void
    {
        $this->pluginManager->dispatch('message', $message, $update, $this->container);

        // Command
        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            $this->pluginManager->dispatch('command', $message, $update, $this->container);
        }

        // Text
        if (isset($message['text'])) {
            $this->pluginManager->dispatch('text', $message, $update, $this->container);
        }

        // Photo
        if (isset($message['photo'])) {
            $this->pluginManager->dispatch('photo', $message, $update, $this->container);
        }

        // Video
        if (isset($message['video'])) {
            $this->pluginManager->dispatch('video', $message, $update, $this->container);
        }

        // Document
        if (isset($message['document'])) {
            $this->pluginManager->dispatch('document', $message, $update, $this->container);
        }

        // Audio
        if (isset($message['audio'])) {
            $this->pluginManager->dispatch('audio', $message, $update, $this->container);
        }

        // Voice
        if (isset($message['voice'])) {
            $this->pluginManager->dispatch('voice', $message, $update, $this->container);
        }

        // Sticker
        if (isset($message['sticker'])) {
            $this->pluginManager->dispatch('sticker', $message, $update, $this->container);
        }

        // Location
        if (isset($message['location'])) {
            $this->pluginManager->dispatch('location', $message, $update, $this->container);
        }

        // Poll
        if (isset($message['poll'])) {
            $this->pluginManager->dispatch('message.poll', $message, $update, $this->container);
        }

        // New chat members
        if (isset($message['new_chat_members'])) {
            $this->pluginManager->dispatch('new_chat_members', $message, $update, $this->container);
        }

        // Left chat member
        if (isset($message['left_chat_member'])) {
            $this->pluginManager->dispatch('left_chat_member', $message, $update, $this->container);
        }
    }

    /**
     * Handle edited message
     */
    private function handleEditedMessage(array $message, array $update): void
    {
        $this->pluginManager->dispatch('edited_message', $message, $update, $this->container);
    }

    /**
     * Handle channel post
     */
    private function handleChannelPost(array $post, array $update): void
    {
        $this->pluginManager->dispatch('channel_post', $post, $update, $this->container);
    }

    /**
     * Handle edited channel post
     */
    private function handleEditedChannelPost(array $post, array $update): void
    {
        $this->pluginManager->dispatch('edited_channel_post', $post, $update, $this->container);
    }

    /**
     * Handle callback query (button press)
     */
    private function handleCallbackQuery(array $query, array $update): void
    {
        $this->pluginManager->dispatch('callback_query', $query, $update, $this->container);
    }

    /**
     * Handle inline query
     */
    private function handleInlineQuery(array $query, array $update): void
    {
        $this->pluginManager->dispatch('inline_query', $query, $update, $this->container);
    }

    /**
     * Handle chosen inline result
     */
    private function handleChosenInlineResult(array $result, array $update): void
    {
        $this->pluginManager->dispatch('chosen_inline_result', $result, $update, $this->container);
    }

    /**
     * Handle my_chat_member (bot added/removed from chat)
     */
    private function handleMyChatMember(array $chatMember, array $update): void
    {
        $this->pluginManager->dispatch('my_chat_member', $chatMember, $update, $this->container);
    }

    /**
     * Handle chat_member (other member status changed)
     */
    private function handleChatMember(array $chatMember, array $update): void
    {
        $this->pluginManager->dispatch('chat_member', $chatMember, $update, $this->container);
    }

    /**
     * Handle poll
     */
    private function handlePoll(array $poll, array $update): void
    {
        $this->pluginManager->dispatch('poll', $poll, $update, $this->container);
    }

    /**
     * Handle poll answer
     */
    private function handlePollAnswer(array $answer, array $update): void
    {
        $this->pluginManager->dispatch('poll_answer', $answer, $update, $this->container);
    }

    /**
     * Extract user from update
     */
    public static function getUser(array $update): ?array
    {
        if (isset($update['message']['from'])) {
            return $update['message']['from'];
        }
        if (isset($update['callback_query']['from'])) {
            return $update['callback_query']['from'];
        }
        if (isset($update['inline_query']['from'])) {
            return $update['inline_query']['from'];
        }
        if (isset($update['my_chat_member']['from'])) {
            return $update['my_chat_member']['from'];
        }
        return null;
    }

    /**
     * Extract chat from update
     */
    public static function getChat(array $update): ?array
    {
        if (isset($update['message']['chat'])) {
            return $update['message']['chat'];
        }
        if (isset($update['callback_query']['message']['chat'])) {
            return $update['callback_query']['message']['chat'];
        }
        if (isset($update['my_chat_member']['chat'])) {
            return $update['my_chat_member']['chat'];
        }
        if (isset($update['channel_post']['chat'])) {
            return $update['channel_post']['chat'];
        }
        return null;
    }
}
