<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Minimal local read-model placeholder for llx_user.
 *
 * The identity service owns the real user table; this stub exists only so
 * that fk_user_* soft references (plain integers, no FK constraints, same as
 * upstream) have something resolvable to join against locally. The primary
 * key keeps the upstream name `rowid` because every upstream fk_user_* column
 * references llx_user.rowid.
 */
#[ORM\Entity]
#[ORM\Table(name: 'llx_user', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_user_login', columns: ['login', 'entity'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::STRING, name: 'login', length: 50, nullable: false)]
    private string $login;

    #[ORM\Column(type: Types::STRING, name: 'firstname', length: 50, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(type: Types::STRING, name: 'lastname', length: 50, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'email', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, name: 'job', length: 128, nullable: true)]
    private ?string $job = null;

    #[ORM\Column(type: Types::STRING, name: 'office_phone', length: 30, nullable: true)]
    private ?string $officePhone = null;

    #[ORM\Column(type: Types::STRING, name: 'office_fax', length: 30, nullable: true)]
    private ?string $officeFax = null;

    #[ORM\Column(type: Types::STRING, name: 'user_mobile', length: 30, nullable: true)]
    private ?string $userMobile = null;

    #[ORM\Column(type: Types::STRING, name: 'personal_mobile', length: 30, nullable: true)]
    private ?string $personalMobile = null;

    #[ORM\Column(type: Types::STRING, name: 'gender', length: 10, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(type: Types::STRING, name: 'photo', length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'statut', nullable: true, options: ['default' => 1])]
    private ?int $statut = 1;

    /** Soft reference to the contact this user is linked to (upstream llx_user.fk_socpeople). */
    #[ORM\Column(type: Types::INTEGER, name: 'fk_socpeople', nullable: true)]
    private ?int $fkSocpeople = null;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEntity(): int
    {
        return $this->entity;
    }

    public function setEntity(int $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(?string $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getOfficePhone(): ?string
    {
        return $this->officePhone;
    }

    public function setOfficePhone(?string $officePhone): static
    {
        $this->officePhone = $officePhone;

        return $this;
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function setStatut(?int $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getFkSocpeople(): ?int
    {
        return $this->fkSocpeople;
    }

    public function setFkSocpeople(?int $fkSocpeople): static
    {
        $this->fkSocpeople = $fkSocpeople;

        return $this;
    }
}
