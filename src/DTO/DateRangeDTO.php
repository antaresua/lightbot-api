<?php

declare(strict_types=1);

namespace App\DTO;

class DateRangeDTO
{
    public function __construct(public \DateTimeInterface $start, public \DateTimeInterface $end)
    {
    }
}
