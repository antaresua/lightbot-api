<?php

namespace App\DTO;

class DayDTO
{
    public int $id;
    public int $dayOfWeek;
    public string $name;

    public function __construct(int $id, string $dayOfWeek, string $name)
    {
        $this->id = $id;
        $this->dayOfWeek = $dayOfWeek;
        $this->name = $name;
    }
}
