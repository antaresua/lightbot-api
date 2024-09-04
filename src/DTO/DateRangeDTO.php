<?php

declare(strict_types=1);

namespace App\DTO;

class DateRangeDTO
{
    public function __construct(public \DateTimeInterface $startDate, public \DateTimeInterface $endDate)
    {
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
