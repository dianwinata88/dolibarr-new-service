<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy third-party log table.
 *
 * Not present in htdocs/install/mysql/tables on current upstream; ported from
 * the reference dump (dev/initdemo/mysqldump_dolibarr_24.0.0.sql) where it is
 * still created. Kept for parity — the table tracks status changes on third
 * parties written by older Dolibarr code paths.
 */
#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_log', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
class SocieteLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'id')]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datel', nullable: true)]
    private ?\DateTimeInterface $datel = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_statut', nullable: true)]
    private ?int $fk_statut = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user', nullable: true)]
    private ?int $fk_user = null;

    #[ORM\Column(type: Types::STRING, name: 'author', length: 30, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(type: Types::STRING, name: 'label', length: 128, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getDatel(): ?\DateTimeInterface
    {
        return $this->datel;
    }

    public function setDatel(?\DateTimeInterface $datel): static
    {
        $this->datel = $datel;

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

    public function getFkStatut(): ?int
    {
        return $this->fk_statut;
    }

    public function setFkStatut(?int $fk_statut): static
    {
        $this->fk_statut = $fk_statut;

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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

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
}
