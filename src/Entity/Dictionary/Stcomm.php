<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_stcomm', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_c_stcomm', columns: ['code'])]
class Stcomm
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, name: 'id')]
    private int $id;

    #[ORM\Column(type: Types::STRING, name: 'code', length: 24, nullable: false)]
    private string $code;

    #[ORM\Column(type: Types::STRING, name: 'libelle', length: 128, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::STRING, name: 'picto', length: 128, nullable: true)]
    private ?string $picto = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'sortorder', nullable: true, options: ['default' => 0])]
    private ?int $sortorder = 0;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getPicto(): ?string
    {
        return $this->picto;
    }

    public function setPicto(?string $picto): static
    {
        $this->picto = $picto;

        return $this;
    }

    public function getSortorder(): ?int
    {
        return $this->sortorder;
    }

    public function setSortorder(?int $sortorder): static
    {
        $this->sortorder = $sortorder;

        return $this;
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function setActive(int $active): static
    {
        $this->active = $active;

        return $this;
    }
}
