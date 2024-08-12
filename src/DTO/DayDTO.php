<?php

declare(strict_types=1);

namespace App\DTO;

class DayDTO
{
    public function __construct(public int $id, public int $dayOfWeek, public string $name)
    {
    }
}
