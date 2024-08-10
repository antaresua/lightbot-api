<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;

class TelegramService
{
    private BotApi $bot;
    private string $chatId;
    private LoggerInterface $logger;

    public function __construct(string $telegramToken, string $telegramChatId, LoggerInterface $logger)
    {
        $this->bot = new BotApi($telegramToken);
        $this->chatId = $telegramChatId;
        $this->logger = $logger;
    }

    public function sendMessage(string $message): void
    {
        try {
            $this->bot->sendMessage($this->chatId, $message, 'MarkdownV2');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Telegram message', [
                'message' => $message,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
