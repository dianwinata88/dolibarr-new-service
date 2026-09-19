<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_categorie_contact', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\Index(name: 'idx_categorie_contact_fk_categorie', columns: ['fk_categorie'])]
#[ORM\Index(name: 'idx_categorie_contact_fk_socpeople', columns: ['fk_socpeople'])]
class CategorieContact
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, name: 'fk_categorie')]
    private int $fk_categorie;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, name: 'fk_socpeople')]
    private int $fk_socpeople;

    #[ORM\Column(type: Types::STRING, name: 'import_key', length: 14, nullable: true)]
    private ?string $import_key = null;

    public function getFkCategorie(): int
    {
        return $this->fk_categorie;
    }

    public function setFkCategorie(int $fk_categorie): static
    {
        $this->fk_categorie = $fk_categorie;

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
