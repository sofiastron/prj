<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity('email', message: 'Cet e-mail est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_SERVER = 'ROLE_SERVER';
    public const ROLE_KITCHEN = 'ROLE_KITCHEN';
    public const ROLE_MANAGER = 'ROLE_MANAGER';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email]
    public ?string $email = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    public ?string $displayName = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    public array $roles = [];

    #[ORM\Column]
    public ?string $password = null;

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
    }

    public function roleLabel(): string
    {
        return match (true) {
            \in_array(self::ROLE_MANAGER, $this->roles, true) => 'Gérant',
            \in_array(self::ROLE_KITCHEN, $this->roles, true) => 'Cuisine',
            \in_array(self::ROLE_SERVER, $this->roles, true) => 'Serveur',
            default => 'Utilisateur',
        };
    }
}
