<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Core\Config;
use Exception;

/**
 * Telegram Bot API Client
 * 
 * Provides methods for all Telegram Bot API endpoints
 */
class Client
{
    private Config $config;
    private string $token;
    private ?string $proxy;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->token = $config->get('BOT_TOKEN');
        $this->proxy = $config->get('HTTP_PROXY');

        if (!$this->token) {
            throw new Exception('BOT_TOKEN is required in .env file');
        }
    }

    /**
     * Make API request to Telegram
     */
    public function request(string $method, array $params = []): ?array
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Telegram API cURL error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Telegram API HTTP {$httpCode}: {$response}");
            return null;
        }

        $result = json_decode($response, true);
        
        if (!isset($result['ok']) || !$result['ok']) {
            error_log("Telegram API error: " . ($result['description'] ?? 'Unknown error'));
            return null;
        }

        return $result['result'] ?? null;
    }

    // ========================================================================
    // Getting Updates
    // ========================================================================

    public function getMe(): ?array
    {
        return $this->request('getMe');
    }

    public function setWebhook(string $url, array $params = []): ?array
    {
        return $this->request('setWebhook', array_merge(['url' => $url], $params));
    }

    public function deleteWebhook(): ?array
    {
        return $this->request('deleteWebhook');
    }

    public function getWebhookInfo(): ?array
    {
        return $this->request('getWebhookInfo');
    }

    // ========================================================================
    // Sending Messages
    // ========================================================================

    public function sendMessage(array $params): ?array
    {
        return $this->request('sendMessage', $params);
    }

    public function forwardMessage(int $chatId, int $fromChatId, int $messageId): ?array
    {
        return $this->request('forwardMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ]);
    }

    public function copyMessage(int $chatId, int $fromChatId, int $messageId, array $params = []): ?array
    {
        return $this->request('copyMessage', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ], $params));
    }

    public function sendPhoto(array $params): ?array
    {
        return $this->request('sendPhoto', $params);
    }

    public function sendAudio(array $params): ?array
    {
        return $this->request('sendAudio', $params);
    }

    public function sendDocument(array $params): ?array
    {
        return $this->request('sendDocument', $params);
    }

    public function sendVideo(array $params): ?array
    {
        return $this->request('sendVideo', $params);
    }

    public function sendAnimation(array $params): ?array
    {
        return $this->request('sendAnimation', $params);
    }

    public function sendVoice(array $params): ?array
    {
        return $this->request('sendVoice', $params);
    }

    public function sendVideoNote(array $params): ?array
    {
        return $this->request('sendVideoNote', $params);
    }

    public function sendMediaGroup(array $params): ?array
    {
        return $this->request('sendMediaGroup', $params);
    }

    public function sendLocation(array $params): ?array
    {
        return $this->request('sendLocation', $params);
    }

    public function sendPoll(array $params): ?array
    {
        return $this->request('sendPoll', $params);
    }

    public function sendDice(array $params): ?array
    {
        return $this->request('sendDice', $params);
    }

    public function sendChatAction(int $chatId, string $action): ?array
    {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }

    // ========================================================================
    // Updating Messages
    // ========================================================================

    public function editMessageText(array $params): ?array
    {
        return $this->request('editMessageText', $params);
    }

    public function editMessageCaption(array $params): ?array
    {
        return $this->request('editMessageCaption', $params);
    }

    public function editMessageMedia(array $params): ?array
    {
        return $this->request('editMessageMedia', $params);
    }

    public function editMessageReplyMarkup(array $params): ?array
    {
        return $this->request('editMessageReplyMarkup', $params);
    }

    public function deleteMessage(int $chatId, int $messageId): ?array
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    // ========================================================================
    // Inline Mode & Callbacks
    // ========================================================================

    public function answerCallbackQuery(array $params): ?array
    {
        return $this->request('answerCallbackQuery', $params);
    }

    public function answerInlineQuery(array $params): ?array
    {
        return $this->request('answerInlineQuery', $params);
    }

    // ========================================================================
    // Chat Management
    // ========================================================================

    public function getChat(int $chatId): ?array
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    public function getChatAdministrators(int $chatId): ?array
    {
        return $this->request('getChatAdministrators', ['chat_id' => $chatId]);
    }

    public function getChatMemberCount(int $chatId): ?int
    {
        $result = $this->request('getChatMemberCount', ['chat_id' => $chatId]);
        return $result ? (int)$result : null;
    }

    public function getChatMember(int $chatId, int $userId): ?array
    {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    public function setChatTitle(int $chatId, string $title): ?array
    {
        return $this->request('setChatTitle', [
            'chat_id' => $chatId,
            'title' => $title
        ]);
    }

    public function setChatDescription(int $chatId, string $description): ?array
    {
        return $this->request('setChatDescription', [
            'chat_id' => $chatId,
            'description' => $description
        ]);
    }

    public function setChatPhoto(int $chatId, $photo): ?array
    {
        return $this->request('setChatPhoto', [
            'chat_id' => $chatId,
            'photo' => $photo
        ]);
    }

    public function deleteChatPhoto(int $chatId): ?array
    {
        return $this->request('deleteChatPhoto', ['chat_id' => $chatId]);
    }

    public function pinChatMessage(int $chatId, int $messageId, bool $disableNotification = false): ?array
    {
        return $this->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification
        ]);
    }

    public function unpinChatMessage(int $chatId, ?int $messageId = null): ?array
    {
        $params = ['chat_id' => $chatId];
        if ($messageId) {
            $params['message_id'] = $messageId;
        }
        return $this->request('unpinChatMessage', $params);
    }

    public function unpinAllChatMessages(int $chatId): ?array
    {
        return $this->request('unpinAllChatMessages', ['chat_id' => $chatId]);
    }

    public function leaveChat(int $chatId): ?array
    {
        return $this->request('leaveChat', ['chat_id' => $chatId]);
    }

    // ========================================================================
    // Chat Member Management
    // ========================================================================

    public function banChatMember(int $chatId, int $userId, array $params = []): ?array
    {
        return $this->request('banChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId
        ], $params));
    }

    public function unbanChatMember(int $chatId, int $userId, bool $onlyIfBanned = true): ?array
    {
        return $this->request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned
        ]);
    }

    public function restrictChatMember(int $chatId, int $userId, array $permissions, array $params = []): ?array
    {
        return $this->request('restrictChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => $permissions
        ], $params));
    }

    public function promoteChatMember(int $chatId, int $userId, array $params = []): ?array
    {
        return $this->request('promoteChatMember', array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId
        ], $params));
    }

    // ========================================================================
    // Invite Links
    // ========================================================================

    public function exportChatInviteLink(int $chatId): ?string
    {
        $result = $this->request('exportChatInviteLink', ['chat_id' => $chatId]);
        return $result ?? null;
    }

    public function createChatInviteLink(int $chatId, array $params = []): ?array
    {
        return $this->request('createChatInviteLink', array_merge([
            'chat_id' => $chatId
        ], $params));
    }

    public function editChatInviteLink(int $chatId, string $inviteLink, array $params = []): ?array
    {
        return $this->request('editChatInviteLink', array_merge([
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ], $params));
    }

    public function revokeChatInviteLink(int $chatId, string $inviteLink): ?array
    {
        return $this->request('revokeChatInviteLink', [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ]);
    }

    // ========================================================================
    // Files
    // ========================================================================

    public function getFile(string $fileId): ?array
    {
        return $this->request('getFile', ['file_id' => $fileId]);
    }

    public function getFileUrl(string $fileId): ?string
    {
        $file = $this->getFile($fileId);
        if (!$file || !isset($file['file_path'])) {
            return null;
        }
        return "https://api.telegram.org/file/bot{$this->token}/{$file['file_path']}";
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Send a simple text message
     */
    public function send(int $chatId, string $text, ?array $keyboard = null, string $parseMode = 'HTML'): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }

        return $this->sendMessage($params);
    }

    /**
     * Edit a message
     */
    public function edit(int $chatId, int $messageId, string $text, ?array $keyboard = null, string $parseMode = 'HTML'): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }

        return $this->editMessageText($params);
    }

    /**
     * Answer callback query
     */
    public function answer(string $callbackQueryId, string $text = '', bool $alert = false): ?array
    {
        return $this->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $alert
        ]);
    }

    /**
     * Check if bot is admin in chat
     */
    public function isBotAdmin(int $chatId): bool
    {
        $me = $this->getMe();
        if (!$me) return false;

        $member = $this->getChatMember($chatId, $me['id']);
        return $member && in_array($member['status'], ['administrator', 'creator']);
    }
}
