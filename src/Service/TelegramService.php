<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;

class TelegramService
{
    private readonly BotApi $bot;

    /**
     * @throws \Exception
     */
    public function __construct(string $telegramToken, private readonly string $chatId, private readonly LoggerInterface $logger)
    {
        $this->bot = new BotApi($telegramToken);
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
