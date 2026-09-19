<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_forme_juridique', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_c_forme_juridique', columns: ['code'])]
class FormeJuridique
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'code', nullable: false)]
    private int $code;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_pays', nullable: false)]
    private int $fk_pays;

    #[ORM\Column(type: Types::STRING, name: 'libelle', length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'tinyint', name: 'isvatexempted', nullable: false, options: ['default' => 0])]
    private int $isvatexempted = 0;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    #[ORM\Column(type: Types::STRING, name: 'module', length: 32, nullable: true)]
    private ?string $module = null;

    #[ORM\Column(type: Types::INTEGER, name: 'position', nullable: false, options: ['default' => 0])]
    private int $position = 0;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getFkPays(): int
    {
        return $this->fk_pays;
    }

    public function setFkPays(int $fk_pays): static
    {
        $this->fk_pays = $fk_pays;

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

    public function getIsvatexempted(): int
    {
        return $this->isvatexempted;
    }

    public function setIsvatexempted(int $isvatexempted): static
    {
        $this->isvatexempted = $isvatexempted;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
