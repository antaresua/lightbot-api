<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

class TelegramController extends AbstractController
{
    #[Route('/api/telegram/maintenance/on', name: 'api_telegram_set_maintenance', methods: ['POST'])]
    public function setMaintenance(Request $request): JsonResponse
    {
        $process = new Process(['php', 'bin/console', 'app:notify:maintenance', 'on']);
        $process->run();

        if (!$process->isSuccessful()) {
            return new JsonResponse(['error' => $process->getErrorOutput()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Channel set to maintenance mode.']);
    }

    #[Route('/api/telegram/maintenance/off', name: 'api_telegram_restore_service', methods: ['POST'])]
    public function restoreService(Request $request): JsonResponse
    {
        $process = new Process(['php', 'bin/console', 'app:notify:maintenance', 'off']);
        $process->run();

        if (!$process->isSuccessful()) {
            return new JsonResponse(['error' => $process->getErrorOutput()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Channel restored to normal operation.']);
    }

    #[Route('/api/telegram/run-check-host', name: 'api_telegram_run_check_host', methods: ['POST'])]
    public function checkHostAvailability(): JsonResponse
    {
        $process = new Process(['php', 'bin/console', 'app:check-host-availability', '194.28.102.52', '555']);
        $process->start();

        return new JsonResponse(['message' => 'Host availability check started.']);
    }
}
