<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Supplier
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    public ?string $name = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $contactName = null;

    #[ORM\Column(length: 40, nullable: true)]
    public ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    public ?string $email = null;
}
