<?php

namespace App\Service;

use App\Entity\TimeSlot;
use App\Repository\TimeSlotRepository;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;

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
     */
    public function getNextEventData(DateTimeInterface $currentTime, bool $isLightOn): array
    {
        $dayOfWeek = (int)$currentTime->format('w');
        $timeFormatted = $currentTime->format('H:i:s');

        if ($isLightOn === true) {
            $nextOffEvent = $this->findNextEvent($dayOfWeek, TimeSlot::TYPE_OFF, $timeFormatted);
            $nextOnEvent = $this->findNextEvent($nextOffEvent->getStartDay()->getDayOfWeek(), TimeSlot::TYPE_ON, $nextOffEvent->getStartTime()->format('H:i:s'));

            return [
                'nextOffTimeStart' => $nextOffEvent->getStartTime()->format('H:i') ?? null,
                'nextOffTimeEnd' => $nextOnEvent->getStartTime()->format('H:i') ?? null,
            ];
        }

        if ($isLightOn === false) {
            $nextPossibleOnEvent = $this->findNextEvent($dayOfWeek, TimeSlot::TYPE_POSSIBLE_ON, $timeFormatted);
            $nextOnEvent = $this->findNextEvent($nextPossibleOnEvent->getStartDay()->getDayOfWeek(), TimeSlot::TYPE_ON, $nextPossibleOnEvent->getStartTime()->format('H:i:s'));
            $nextOffEvent = $this->findNextEvent($nextOnEvent->getStartDay()->getDayOfWeek(), TimeSlot::TYPE_OFF, $nextOnEvent->getStartTime()->format('H:i:s'));

            return [
                'nextPossibleOnStart' => $nextPossibleOnEvent->getStartTime()->format('H:i') ?? null,
                'nextPossibleOnEnd' => $nextOnEvent->getStartTime()->format('H:i') ?? null,
                'nextGuaranteedOnStart' => $nextOnEvent->getStartTime()->format('H:i') ?? null,
                'nextGuaranteedOnEnd' => $nextOffEvent->getStartTime()->format('H:i') ?? null,
            ];
        }

        return [];
    }

    /**
     * @param int $dayOfWeek
     * @param string $type
     * @param string $time
     * @return TimeSlot|null
     * @throws NonUniqueResultException
     */
    private function findNextEvent(int $dayOfWeek, string $type, string $time): ?TimeSlot
    {
        $nextEvent = $this->timeSlotRepository->findNextEvent($dayOfWeek, $type, $time);

        if ($nextEvent !== null) {
            return $nextEvent;
        }

        $nextDayOfWeek = ($dayOfWeek + 1) % 7;

        return $this->timeSlotRepository->findNextEvent($nextDayOfWeek, $type);
    }

    public function calculateDuration(DateTimeInterface $startTime, DateTimeInterface $endTime): array
    {
        $interval = $endTime->diff($startTime);

        return ['days' => $interval->d, 'hours' => $interval->h, 'minutes' => $interval->i];
    }
}
