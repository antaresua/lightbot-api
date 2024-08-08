<?php

namespace App\DTO;

class DateRangeDTO
{
    public \DateTimeInterface $start;
    public \DateTimeInterface $end;

    public function __construct(\DateTimeInterface $start, \DateTimeInterface $end)
    {
        $this->start = $start;
        $this->end = $end;
    }
}
