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
            $nextOnEvent = isset($nextOffEventStart) ? $this->timeSlotRepository->findNextOnEvent(new DateTime($nextOffEvent->getStartTime())) : null;

            return [
                'nextOffTimeStart'  => $nextOffEvent->getStartTime()->format('H:i') ?? null,
                'nextOffTimeEnd'    => $nextOnEvent->getStartTime()->format('H:i') ?? null,
            ];
        }

        if ($isLightOn === false) {
            $nextPossibleOnEvent = $this->timeSlotRepository->findNextPossibleOnEvent($currentTime);
            $nextOnEvent = $this->timeSlotRepository->findNextOnEvent($currentTime);
            $nextOffEvent = $this->timeSlotRepository->findNextOffEvent(new DateTime($nextOnEvent->getStartTime()));

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

        $days = $interval->days;
        $hours = $interval->h + ($days * 24);
        $minutes = $interval->i;

        return ['days' => $days, 'hours' => $hours, 'minutes' => $minutes];
    }
}