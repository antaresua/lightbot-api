<?php

declare(strict_types=1);

namespace App\DTO;

class DateRangeDTO
{
    private \DateTimeInterface $startDate;
    private \DateTimeInterface $endDate;

    /**
     * @throws \Exception
     */
    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = new \DateTime($startDate);
        $this->endDate = new \DateTime($endDate);
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }
}
