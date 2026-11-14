<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[UniqueEntity('label', message: 'Une table porte déjà ce nom.')]
class DiningTable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    public ?string $label = null;

    #[ORM\Column]
    #[Assert\Positive]
    public int $seats = 2;

    public function __toString(): string
    {
        return (string) $this->label;
    }
}
