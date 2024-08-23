<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TelegramService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/telegram', name: 'telegram')]
class TelegramController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TelegramService $telegramService
    ) {
    }

    #[Route('/maintenance/on', name: 'api_telegram_set_maintenance', methods: ['POST'])]
    public function setMaintenance(): JsonResponse
    {
        $message = '⚠️ Канал зупинено на технічне обслуговування\.';

        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Channel set to maintenance mode.'], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to send Telegram message: '.$exception->getMessage());

            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/maintenance/off', name: 'api_telegram_restore_service', methods: ['POST'])]
    public function restoreService(): JsonResponse
    {
        $message = '✅ Роботу каналу відновлено\.';

        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Channel restored.'], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to send Telegram message: '.$exception->getMessage());

            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/run-check-host', name: 'api_telegram_run_check_host', methods: ['POST'])]
    public function checkHostAvailability(): JsonResponse
    {
        $process = new Process(['php', 'bin/console', 'app:check-host-availability', '194.28.102.52', '555']);
        $process->start();

        return new JsonResponse(['message' => 'Host availability check started.']);
    }
}
