<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_commerciaux', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(
    name: 'uk_societe_commerciaux_c_type_contact',
    columns: ['fk_soc', 'fk_user', 'fk_c_type_contact_code'],
)]
class SocieteCommerciaux
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user', nullable: true)]
    private ?int $fk_user = null;

    #[ORM\Column(
        type: Types::STRING,
        name: 'fk_c_type_contact_code',
        length: 32,
        nullable: false,
        options: ['default' => 'SALESREPTHIRD'],
    )]
    private string $fk_c_type_contact_code = 'SALESREPTHIRD';

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

    public function getFkSoc(): ?int
    {
        return $this->fk_soc;
    }

    public function setFkSoc(?int $fk_soc): static
    {
        $this->fk_soc = $fk_soc;

        return $this;
    }

    public function getFkUser(): ?int
    {
        return $this->fk_user;
    }

    public function setFkUser(?int $fk_user): static
    {
        $this->fk_user = $fk_user;

        return $this;
    }

    public function getFkCTypeContactCode(): string
    {
        return $this->fk_c_type_contact_code;
    }

    public function setFkCTypeContactCode(string $fk_c_type_contact_code): static
    {
        $this->fk_c_type_contact_code = $fk_c_type_contact_code;

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
