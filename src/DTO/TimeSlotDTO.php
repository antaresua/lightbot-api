<?php

declare(strict_types=1);

namespace App\DTO;

class TimeSlotDTO
{
    public function __construct(
        public int $id,
        public string $startTime,
        public string $endTime,
        public DayDTO $startDay,
        public DayDTO $endDay,
        public string $type,
    ) {
    }
}
