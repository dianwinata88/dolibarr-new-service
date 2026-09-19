<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_payment_term', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_c_payment_term_code', columns: ['entity', 'code'])]
class PaymentTerm
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'code', length: 16, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'sortorder', nullable: true)]
    private ?int $sortorder = null;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: true, options: ['default' => 1])]
    private ?int $active = 1;

    #[ORM\Column(type: Types::STRING, name: 'libelle', length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, name: 'libelle_facture', length: 65535, nullable: true)]
    private ?string $libelle_facture = null;

    #[ORM\Column(type: 'tinyint', name: 'type_cdr', nullable: true)]
    private ?int $type_cdr = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'nbjour', nullable: true)]
    private ?int $nbjour = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'decalage', nullable: true)]
    private ?int $decalage = null;

    #[ORM\Column(type: Types::STRING, name: 'deposit_percent', length: 63, nullable: true)]
    private ?string $deposit_percent = null;

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

    public function getEntity(): int
    {
        return $this->entity;
    }

    public function setEntity(int $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setActive(?int $active): static
    {
        $this->active = $active;

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

    public function getLibelleFacture(): ?string
    {
        return $this->libelle_facture;
    }

    public function setLibelleFacture(?string $libelle_facture): static
    {
        $this->libelle_facture = $libelle_facture;

        return $this;
    }

    public function getTypeCdr(): ?int
    {
        return $this->type_cdr;
    }

    public function setTypeCdr(?int $type_cdr): static
    {
        $this->type_cdr = $type_cdr;

        return $this;
    }

    public function getNbjour(): ?int
    {
        return $this->nbjour;
    }

    public function setNbjour(?int $nbjour): static
    {
        $this->nbjour = $nbjour;

        return $this;
    }

    public function getDecalage(): ?int
    {
        return $this->decalage;
    }

    public function setDecalage(?int $decalage): static
    {
        $this->decalage = $decalage;

        return $this;
    }

    public function getDepositPercent(): ?string
    {
        return $this->deposit_percent;
    }

    public function setDepositPercent(?string $deposit_percent): static
    {
        $this->deposit_percent = $deposit_percent;

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
