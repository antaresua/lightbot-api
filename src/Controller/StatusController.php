<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\DateRangeDTO;
use App\DTO\StatusDTO;
use App\Entity\Status;
use App\Repository\StatusRepository;
use App\Service\LightScheduleService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/light')]
class StatusController extends AbstractController
{
    private const int TYPE_OFF = 0;
    private const int TYPE_ON = 1;
    private const int TYPE_ERROR = 2;
    private const string TIMEZONE = 'Europe/Kyiv';

    private readonly SerializerInterface $serializer;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly TelegramService $telegramService,
        private readonly StatusRepository $statusRepository,
        private readonly LightScheduleService $lightScheduleService,
    ) {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new DateTimeNormalizer()],
            [new JsonEncoder()],
        );
    }

    #[Route('/on', name: 'light_on', methods: ['POST'])]
    public function lightOn(): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $lastStatus = $this->statusRepository->findLastLightOffStatus();
            $this->addStatus(true);

            $currentDateTime = new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
            $lastChangedAt = $lastStatus ? $lastStatus->getCreatedAt() : $currentDateTime;
            $lastChangedAt->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $duration = $this->lightScheduleService->calculateDuration($lastChangedAt, $currentDateTime);

            $nextEvent = $this->lightScheduleService->getNextEventData($currentDateTime, true);

            $message = $this->formatMessage(
                $currentDateTime,
                self::TYPE_ON,
                $this->formatDuration($duration['days'], $duration['hours'], $duration['minutes']),
                $nextEvent,
            );

            if (!$message) {
                $this->logger->error('Failed to send Telegram message');
                throw new \Exception('Failed to send Telegram message');
            }

            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Light turned on'], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/off', name: 'light_off', methods: ['POST'])]
    public function lightOff(): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $lastStatus = $this->statusRepository->findLastLightOnStatus();
            $this->addStatus(false);

            $currentDateTime = new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
            $lastChangedAt = $lastStatus ? $lastStatus->getCreatedAt() : $currentDateTime;
            $lastChangedAt->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $duration = $this->lightScheduleService->calculateDuration($lastChangedAt, $currentDateTime);

            $nextEvent = $this->lightScheduleService->getNextEventData($currentDateTime, false);

            $message = $this->formatMessage(
                $currentDateTime,
                self::TYPE_OFF,
                $this->formatDuration($duration['days'], $duration['hours'], $duration['minutes']),
                $nextEvent,
            );

            if (!$message) {
                $this->logger->error('Failed to send Telegram message');
                throw new \Exception('Failed to send Telegram message');
            }

            $this->telegramService->sendMessage($message);

            return new JsonResponse(['message' => 'Light turned off'], Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function formatDuration(int $days, int $hours, int $minutes): string
    {
        $parts = [];

        if ($days > 0) {
            $parts[] = sprintf('%d дн', $days);
        }
        if ($hours > 0) {
            $parts[] = sprintf('%d год', $hours);
        }
        if ($minutes > 0) {
            $parts[] = sprintf('%d хв', $minutes);
        }

        return implode(' ', $parts);
    }

    /**
     * @throws \Exception
     */
    private function addStatus(bool $isOn): void
    {
        $lightStatus = new Status();
        $lightStatus->setIsOn($isOn);
        $lightStatus->setCreatedAt(new \DateTime());

        $this->em->persist($lightStatus);
        $this->em->flush();
    }

    private function formatMessage(\DateTimeInterface $currentDateTime, int $type = self::TYPE_ERROR, string $duration = '', array $nextEvent = []): string
    {
        if (self::TYPE_ON === $type) {
            if (isset($nextEvent['nextOffTimeStart'], $nextEvent['nextOffTimeEnd'])) {
                return sprintf(
                    "🟢 Світло з'явилося о *%s*\n🕓 Його не було *%s*\n📅 Наступне відключення за графіком: з *%s* по *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                    $nextEvent['nextOffTimeStart'],
                    $nextEvent['nextOffTimeEnd'],
                );
            } else {
                return sprintf(
                    "🟢 Світло з'явилося о *%s*\n🕓 Його не було *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                );
            }
        }
        if (self::TYPE_OFF === $type) {
            if (isset($nextEvent['nextGuaranteedOnStart'], $nextEvent['nextGuaranteedOnEnd'], $nextEvent['nextPossibleOnStart'], $nextEvent['nextPossibleOnEnd'])) {
                return sprintf(
                    "🔴 Світло зникло о *%s*\n🕓 Воно було *%s*\n🗓 Наступне планове включення: *%s* \- *%s*\n⚠️ Можливе включення з *%s* по *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                    $nextEvent['nextGuaranteedOnStart'],
                    $nextEvent['nextGuaranteedOnEnd'],
                    $nextEvent['nextPossibleOnStart'],
                    $nextEvent['nextPossibleOnEnd'],
                );
            } else {
                return sprintf(
                    "🔴 Світло зникло о *%s*\n🕓 Воно було *%s*",
                    $currentDateTime->format('H:i'),
                    empty($duration) ? '0 хв' : $duration,
                );
            }
        }

        return 'Щось зламалось. Адмін уже займається питанням...';
    }

    #[Route('/status', name: 'light_status', methods: ['GET'])]
    public function getStatus(): JsonResponse
    {
        try {
            $lastStatus = $this->statusRepository->findLastStatus();
            $lastStatus->getCreatedAt()->setTimezone(new \DateTimeZone(self::TIMEZONE));

            if (!$lastStatus) {
                return new JsonResponse(['message' => 'No status available'], Response::HTTP_NOT_FOUND);
            }

            $status = new StatusDTO(
                $lastStatus->getId(),
                $lastStatus->isOn() ? 'on' : 'off',
                $lastStatus->getCreatedAt()->format('Y-m-d H:i:s'),
            );

            return new JsonResponse($status, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statuses', name: 'light_statuses', methods: ['GET'])]
    public function getStatuses(): JsonResponse
    {
        try {
            $statuses = $this->statusRepository->findBy([], ['createdAt' => 'DESC']);
            foreach ($statuses as $status) {
                $status->getCreatedAt()->setTimezone(new \DateTimeZone(self::TIMEZONE));
            }
            $statusDTOs = array_map(fn ($status) => new StatusDTO(
                $status->getId(),
                $status->isOn() ? 'on' : 'off',
                $status->getCreatedAt()->format('Y-m-d H:i:s'),
            ), $statuses);

            return new JsonResponse($statusDTOs, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statuses/range', name: 'light_statuses_range', methods: ['POST'])]
    public function getStatusesForDateRange(Request $request): JsonResponse
    {
        try {
            $data = $this->serializer->deserialize($request->getContent(), DateRangeDTO::class, 'json');
            $statuses = $this->statusRepository->findByDateRange($data->getStartDate(), $data->getEndDate());
            foreach ($statuses as $status) {
                $status->getCreatedAt()->setTimezone(new \DateTimeZone(self::TIMEZONE));
            }
            $statusDTOs = array_map(fn ($status) => new StatusDTO(
                $status->getId(),
                $status->isOn() ? 'on' : 'off',
                $status->getCreatedAt()->format('Y-m-d H:i:s'),
            ), $statuses);

            return new JsonResponse($statusDTOs, Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
