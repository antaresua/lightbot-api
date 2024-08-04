<?php

namespace App\Service;

use App\Repository\TimeSlotRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

class LightScheduleService
{
    private TimeSlotRepository $timeSlotRepository;

    public function __construct(
        TimeSlotRepository $timeSlotRepository
    ) {
        $this->timeSlotRepository = $timeSlotRepository;
    }

    /**
     * @param DateTimeInterface $currentTime
     * @param bool $isLightOn
     *
     * @return array
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function getNextEventData(DateTimeInterface $currentTime, bool $isLightOn): array
    {
        if ($isLightOn === true) {
            $nextOffEvent = $this->timeSlotRepository->findNextOffEvent($currentTime);
            $nextOnEvent = $this->timeSlotRepository->findNextOnEvent($nextOffEvent->getStartTime());

            return [
                'nextOffTimeStart'  => $nextOffEvent->getStartTime()->format('H:i') ?? null,
                'nextOffTimeEnd'    => $nextOnEvent->getStartTime()->format('H:i') ?? null,
            ];
        }

        if ($isLightOn === false) {
            // Знаходимо наступне можливе включення
            $nextPossibleOnEvent = $this->timeSlotRepository->findNextPossibleOnEvent($currentTime);
            // далі знаходимо наступне включення після можливого включення
            $nextOnEvent = $this->timeSlotRepository->findNextOnEvent($nextPossibleOnEvent->getStartTime());
            // і далі знаходимо наступне вимкнення після включення
            $nextOffEvent = $this->timeSlotRepository->findNextOffEvent($nextOnEvent->getStartTime());

            return [
                'nextPossibleOnStart'   => $nextPossibleOnEvent->getStartTime()->format('H:i') ?? null,
                'nextPossibleOnEnd'     => $nextOnEvent->getStartTime()->format('H:i') ?? null,
                'nextGuaranteedOnStart' => $nextOnEvent->getStartTime()->format('H:i') ?? null,
                'nextGuaranteedOnEnd'   => $nextOffEvent->getStartTime()->format('H:i') ?? null,
            ];
        }

        return [];
    }

    public function calculateDuration(DateTimeInterface $startTime, DateTimeInterface $endTime): array
    {
        $interval = $endTime->diff($startTime);

        return ['days' => $interval->d, 'hours' => $interval->h, 'minutes' => $interval->i];
    }
}