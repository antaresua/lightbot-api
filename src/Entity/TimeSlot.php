<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TimeSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeSlotRepository::class)]
class TimeSlot
{
    public const TYPE_ON          = 'on';
    public const TYPE_OFF         = 'off';
    public const TYPE_POSSIBLE_ON = 'possible_on';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $endTime;

    #[ORM\ManyToOne(targetEntity: Day::class, inversedBy: 'timeSlots')]
    private ?Day $startDay = null;

    #[ORM\ManyToOne(targetEntity: Day::class, inversedBy: 'timeSlots')]
    private ?Day $endDay = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getStartDay(): ?Day
    {
        return $this->startDay;
    }

    public function setStartDay(?Day $startDay): self
    {
        $this->startDay = $startDay;

        return $this;
    }

    public function getEndDay(): ?Day
    {
        return $this->endDay;
    }

    public function setEndDay(?Day $endDay): self
    {
        $this->endDay = $endDay;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, [self::TYPE_ON, self::TYPE_OFF, self::TYPE_POSSIBLE_ON], true)) {
            throw new \InvalidArgumentException('Invalid type');
        }
        $this->type = $type;

        return $this;
    }
}
