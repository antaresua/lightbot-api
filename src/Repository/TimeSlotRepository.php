<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Day;
use App\Entity\TimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

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
     * @throws NonUniqueResultException
     */
    public function findNextEvent(int $dayOfWeek, string $type, string $time = '00:00:00'): ?TimeSlot
    {
        return $this->createQueryBuilder('ts')
            ->join('ts.startDay', 'sd')
            ->where('ts.type = :type')
            ->andWhere('sd.dayOfWeek = :dayOfWeek')
            ->andWhere('ts.startTime >= :time')
            ->setParameter('type', $type)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $time)
            ->orderBy('ts.startTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
