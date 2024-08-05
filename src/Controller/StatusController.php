<?php

namespace App\Controller;

use App\Entity\Status;
use App\Service\LightScheduleService;
use App\Repository\StatusRepository;
use App\Service\TelegramService;
use DateTime;

use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatusController extends AbstractController
{
    private const TYPE_OFF = 0;
    private const TYPE_ON = 1;
    private const TYPE_ERROR = 2;
    private LightScheduleService $lightScheduleService;
    private StatusRepository $statusRepository;
    private EntityManagerInterface $em;
    private TelegramService $telegramService;
    private LoggerInterface $logger;

    public function __construct(
        TelegramService $telegramService,
        LightScheduleService $lightScheduleService,
        StatusRepository $statusRepository,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->telegramService = $telegramService;
        $this->lightScheduleService = $lightScheduleService;
        $this->statusRepository = $statusRepository;
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/light/on", name="light_on", methods={"POST"})
     */
    public function lightOn(): JsonResponse
    {
        try {
            $lastStatus = $this->statusRepository->findLastLightOffStatus();
            $this->addStatus(true);

            $currentDateTime = new DateTime('now', new DateTimeZone('Europe/Kiev'));
            $lastChangedAt = $lastStatus ? $lastStatus->getCreatedAt() : $currentDateTime;
            $duration = $this->lightScheduleService->calculateDuration($lastChangedAt, $currentDateTime);

            $nextEvent = $this->lightScheduleService->getNextEventData($currentDateTime, true);

            $message = $this->formatMessage(
                $currentDateTime,
                self::TYPE_ON,
                $this->formatDuration($duration['days'], $duration['hours'], $duration['minutes']),
                $nextEvent
            );

            if (!$message) {
                $this->logger->error('Failed to send Telegram message');
                throw new \Exception('Failed to send Telegram message');
            }

            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Light turned on'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/api/light/off", name="light_off", methods={"POST"})
     */
    public function lightOff(): JsonResponse
    {
        try {
            $lastStatus = $this->statusRepository->findLastLightOnStatus();
            $this->addStatus(false);

            $currentDateTime = new DateTime('now', new DateTimeZone('Europe/Kiev'));
            $lastChangedAt = $lastStatus ? $lastStatus->getCreatedAt() : $currentDateTime;
            $duration = $this->lightScheduleService->calculateDuration($lastChangedAt, $currentDateTime);

            $nextEvent = $this->lightScheduleService->getNextEventData($currentDateTime, false);

            $message = $this->formatMessage(
                $currentDateTime,
                self::TYPE_OFF,
                $this->formatDuration($duration['days'], $duration['hours'], $duration['minutes']),
                $nextEvent
            );

            if (!$message) {
                $this->logger->error('Failed to send Telegram message');
                throw new \Exception('Failed to send Telegram message');
            }

            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Light turned off'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function formatDuration(int $days, int $hours, int $minutes): string
    {
        $parts = [];

        if ($days > 0) {
            $parts[] = sprintf("%d дн", $days);
        }
        if ($hours > 0) {
            $parts[] = sprintf("%d год", $hours);
        }
        if ($minutes > 0) {
            $parts[] = sprintf("%d хв", $minutes);
        }

        return implode(' ', $parts);
    }

    private function addStatus(bool $isOn): void
    {
        $lightStatus = new Status();
        $lightStatus->setIsOn($isOn);
        $lightStatus->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($lightStatus);
        $this->em->flush();
    }

    private function formatMessage(DateTimeInterface $currentDateTime, int $type = self::TYPE_ERROR, string $duration = '', array $nextEvent = []): ?string
    {
        if ($type === self::TYPE_ON) {
            if (isset($nextEvent['nextOffTimeStart'], $nextEvent['nextOffTimeEnd'])) {
                return sprintf(
                    "🟢 Світло з'явилося о *%s*\n🕓 Його не було *%s*\n📅 Наступне відключення: з *%s* по *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                    $nextEvent['nextOffTimeStart'],
                    $nextEvent['nextOffTimeEnd']
                );
            } else {
                return sprintf(
                    "🟢 Світло з'явилося о *%s*\n🕓 Його не було *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration
                );
            }
        }
        if ($type === self::TYPE_OFF) {
            if (isset($nextEvent['nextGuaranteedOnStart'], $nextEvent['nextGuaranteedOnEnd'], $nextEvent['nextPossibleOnStart'], $nextEvent['nextPossibleOnEnd'])) {
                return sprintf(
                    "🔴 Світло зникло о *%s*\n🕓 Воно було *%s*\n🗓 Наступне включення: *%s* \- *%s*\n⚠️ Можливе включення з *%s* по *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                    $nextEvent['nextGuaranteedOnStart'],
                    $nextEvent['nextGuaranteedOnEnd'],
                    $nextEvent['nextPossibleOnStart'],
                    $nextEvent['nextPossibleOnEnd']
                );
            } else {
                return sprintf(
                    "🔴 Світло зникло о *%s*\n🕓 Воно було *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                );
            }
        }

        return "Щось зламалось. Адмін уже займається питанням...";
    }

    /**
     * @Route("/api/light/status", name="light_status", methods={"GET"})
     */
    public function getStatus(): JsonResponse
    {
        try {
            $lastStatus = $this->statusRepository->findLastStatus();

            if (!$lastStatus) {
                return new JsonResponse(['message' => 'No status available'], Response::HTTP_NOT_FOUND);
            }

            $statusData = [
                'status' => $lastStatus->isOn() ? 'on' : 'off',
                'changeTime' => $lastStatus->getCreatedAt()->format('Y-m-d H:i:s')
            ];

            return new JsonResponse($statusData, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
