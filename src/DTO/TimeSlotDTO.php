<?php

namespace App\DTO;

class TimeSlotDTO
{
    public int $id;
    public string $startTime;
    public string $endTime;
    public DayDTO $startDay;
    public DayDTO $endDay;
    public string $type;

    public function __construct(int $id, string $startTime, string $endTime, DayDTO $startDay, DayDTO $endDay, string $type)
    {
        $this->id = $id;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->startDay = $startDay;
        $this->endDay = $endDay;
        $this->type = $type;
    }
}
