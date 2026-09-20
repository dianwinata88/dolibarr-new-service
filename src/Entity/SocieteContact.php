<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_contacts', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(
    name: 'idx_societe_contacts_idx1',
    columns: ['entity', 'fk_soc', 'fk_c_type_contact', 'fk_socpeople'],
)]
class SocieteContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_creation', nullable: false)]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: false)]
    private int $fk_soc;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_c_type_contact', nullable: false)]
    private int $fk_c_type_contact;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_socpeople', nullable: false)]
    private int $fk_socpeople;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'tms', nullable: true)]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::STRING, name: 'import_key', length: 14, nullable: true)]
    private ?string $import_key = null;

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

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getFkSoc(): int
    {
        return $this->fk_soc;
    }

    public function setFkSoc(int $fk_soc): static
    {
        $this->fk_soc = $fk_soc;

        return $this;
    }

    public function getFkCTypeContact(): int
    {
        return $this->fk_c_type_contact;
    }

    public function setFkCTypeContact(int $fk_c_type_contact): static
    {
        $this->fk_c_type_contact = $fk_c_type_contact;

        return $this;
    }

    public function getFkSocpeople(): int
    {
        return $this->fk_socpeople;
    }

    public function setFkSocpeople(int $fk_socpeople): static
    {
        $this->fk_socpeople = $fk_socpeople;

        return $this;
    }

    public function getTms(): ?\DateTimeInterface
    {
        return $this->tms;
    }

    public function setTms(?\DateTimeInterface $tms): static
    {
        $this->tms = $tms;

        return $this;
    }

    public function getImportKey(): ?string
    {
        return $this->import_key;
    }

    public function setImportKey(?string $import_key): static
    {
        $this->import_key = $import_key;

        return $this;
    }
}
