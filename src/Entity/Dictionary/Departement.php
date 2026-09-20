<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_departements', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_departements', columns: ['code_departement', 'fk_region'])]
#[ORM\Index(name: 'idx_departements_fk_region', columns: ['fk_region'])]
class Departement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::STRING, name: 'code_departement', length: 6, nullable: false)]
    private string $code_departement;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_region', nullable: true)]
    private ?int $fk_region = null;

    #[ORM\Column(type: Types::STRING, name: 'cheflieu', length: 50, nullable: true)]
    private ?string $cheflieu = null;

    #[ORM\Column(type: Types::INTEGER, name: 'tncc', nullable: true)]
    private ?int $tncc = null;

    #[ORM\Column(type: Types::STRING, name: 'ncc', length: 50, nullable: true)]
    private ?string $ncc = null;

    #[ORM\Column(type: Types::STRING, name: 'nom', length: 50, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'tinyint', name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

        return $this;
    }

    public function getCodeDepartement(): string
    {
        return $this->code_departement;
    }

    public function setCodeDepartement(string $code_departement): static
    {
        $this->code_departement = $code_departement;

        return $this;
    }

    public function getFkRegion(): ?int
    {
        return $this->fk_region;
    }

    public function setFkRegion(?int $fk_region): static
    {
        $this->fk_region = $fk_region;

        return $this;
    }

    public function getCheflieu(): ?string
    {
        return $this->cheflieu;
    }

    public function setCheflieu(?string $cheflieu): static
    {
        $this->cheflieu = $cheflieu;

        return $this;
    }

    public function getTncc(): ?int
    {
        return $this->tncc;
    }

    public function setTncc(?int $tncc): static
    {
        $this->tncc = $tncc;

        return $this;
    }

    public function getNcc(): ?string
    {
        return $this->ncc;
    }

    public function setNcc(?string $ncc): static
    {
        $this->ncc = $ncc;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

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
