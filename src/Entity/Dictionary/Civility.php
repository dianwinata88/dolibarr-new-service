<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_civility', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_c_civility', columns: ['code'])]
class Civility
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::STRING, name: 'code', length: 6, nullable: false)]
    private string $code;

    #[ORM\Column(type: Types::STRING, name: 'label', length: 128, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    #[ORM\Column(type: Types::STRING, name: 'module', length: 32, nullable: true)]
    private ?string $module = null;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

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

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(?string $module): static
    {
        $this->module = $module;

        return $this;
    }
}
