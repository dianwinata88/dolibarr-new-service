<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_country', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'idx_c_country_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'idx_c_country_code_iso', columns: ['code_iso'])]
#[ORM\UniqueConstraint(name: 'idx_c_country_label', columns: ['label'])]
class Country
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private int $rowid;

    #[ORM\Column(type: Types::STRING, name: 'code', length: 2, nullable: false)]
    private string $code;

    #[ORM\Column(type: Types::STRING, name: 'code_iso', length: 3, nullable: true)]
    private ?string $code_iso = null;

    #[ORM\Column(type: Types::STRING, name: 'numeric_code', length: 3, nullable: true)]
    private ?string $numeric_code = null;

    #[ORM\Column(type: Types::STRING, name: 'label', length: 128, nullable: false)]
    private string $label;

    #[ORM\Column(type: 'tinyint', name: 'eec', nullable: false, options: ['default' => 0])]
    private int $eec = 0;

    #[ORM\Column(type: 'tinyint', name: 'sepa', nullable: false, options: ['default' => 0])]
    private int $sepa = 0;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    #[ORM\Column(type: 'tinyint', name: 'favorite', nullable: false, options: ['default' => 0])]
    private int $favorite = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'phone_code', nullable: true)]
    private ?int $phone_code = null;

    #[ORM\Column(type: Types::STRING, name: 'trunk_prefix', length: 5, nullable: true)]
    private ?string $trunk_prefix = null;

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

    public function getCodeIso(): ?string
    {
        return $this->code_iso;
    }

    public function setCodeIso(?string $code_iso): static
    {
        $this->code_iso = $code_iso;

        return $this;
    }

    public function getNumericCode(): ?string
    {
        return $this->numeric_code;
    }

    public function setNumericCode(?string $numeric_code): static
    {
        $this->numeric_code = $numeric_code;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getEec(): int
    {
        return $this->eec;
    }

    public function setEec(int $eec): static
    {
        $this->eec = $eec;

        return $this;
    }

    public function getSepa(): int
    {
        return $this->sepa;
    }

    public function setSepa(int $sepa): static
    {
        $this->sepa = $sepa;

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

    public function getFavorite(): int
    {
        return $this->favorite;
    }

    public function setFavorite(int $favorite): static
    {
        $this->favorite = $favorite;

        return $this;
    }

    public function getPhoneCode(): ?int
    {
        return $this->phone_code;
    }

    public function setPhoneCode(?int $phone_code): static
    {
        $this->phone_code = $phone_code;

        return $this;
    }

    public function getTrunkPrefix(): ?string
    {
        return $this->trunk_prefix;
    }

    public function setTrunkPrefix(?string $trunk_prefix): static
    {
        $this->trunk_prefix = $trunk_prefix;

        return $this;
    }
}
