<?php

namespace App\Repository;

use App\Entity\Day;
use App\Entity\TimeSlot;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeSlot>
 */
class TimeSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeSlot::class);
    }

    public function findByDay(Day $day)
    {
        return $this->createQueryBuilder('t')
            ->where('t.startDay = :day')
            ->setParameter('day', $day)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the next event of a given type after the current time.
     *
     * @param DateTimeInterface $time
     * @param string $type
     * @return TimeSlot|null
     * @throws NonUniqueResultException
     */
    private function findNextEvent(DateTimeInterface $time, string $type): ?TimeSlot
    {
        $dayOfWeek = (int) $time->format('w');
        $timeFormatted = $time->format('H:i:s');

        // First query: find next event today after the current time
        $nextEvent = $this->createQueryBuilder('ts')
            ->join('ts.startDay', 'sd')
            ->andWhere('ts.type = :type')
            ->andWhere('sd.dayOfWeek = :currentDayOfWeek')
            ->andWhere('ts.startTime > :currentTime')
            ->setParameter('type', $type)
            ->setParameter('currentDayOfWeek', $dayOfWeek)
            ->setParameter('currentTime', $timeFormatted)
            ->orderBy('ts.startTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($nextEvent !== null) {
            return $nextEvent;
        }

        // Second query: find the first event on the next day
        $nextDayOfWeek = ($dayOfWeek + 1) % 7;

        return $this->createQueryBuilder('ts')
            ->join('ts.startDay', 'sd')
            ->andWhere('ts.type = :type')
            ->andWhere('sd.dayOfWeek = :nextDayOfWeek')
            ->setParameter('type', $type)
            ->setParameter('nextDayOfWeek', $nextDayOfWeek)
            ->orderBy('ts.startTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param DateTimeInterface $currentTime
     * @return TimeSlot|null
     * @throws NonUniqueResultException
     */
    public function findNextOffEvent(DateTimeInterface $currentTime): ?TimeSlot
    {
        return $this->findNextEvent($currentTime, TimeSlot::TYPE_OFF);
    }

    /**
     * @param DateTimeInterface $currentTime
     * @return TimeSlot|null
     * @throws NonUniqueResultException
     */
    public function findNextOnEvent(DateTimeInterface $currentTime): ?TimeSlot
    {
        return $this->findNextEvent($currentTime, TimeSlot::TYPE_ON);
    }

    /**
     * @param DateTimeInterface $currentTime
     * @return TimeSlot|null
     * @throws NonUniqueResultException
     */
    public function findNextPossibleOnEvent(DateTimeInterface $currentTime): ?TimeSlot
    {
        return $this->findNextEvent($currentTime, TimeSlot::TYPE_POSSIBLE_ON);
    }
}
