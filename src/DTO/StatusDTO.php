<?php

namespace App\DTO;

class StatusDTO
{
    public int $id;
    public string $status;
    public string $createdAt;

    public function __construct(int $id, string $status, string $createdAt)
    {
        $this->id = $id;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }
}
