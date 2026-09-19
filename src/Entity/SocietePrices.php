<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_prices', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
class SocietePrices
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true, options: ['default' => 0])]
    private ?int $fk_soc = 0;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datec', nullable: true)]
    private ?\DateTimeInterface $datec = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_author', nullable: true)]
    private ?int $fk_user_author = null;

    #[ORM\Column(type: 'tinyint', name: 'price_level', nullable: true, options: ['default' => 1])]
    private ?int $price_level = 1;

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

    public function getTms(): ?\DateTimeInterface
    {
        return $this->tms;
    }

    public function setTms(?\DateTimeInterface $tms): static
    {
        $this->tms = $tms;

        return $this;
    }

    public function getDatec(): ?\DateTimeInterface
    {
        return $this->datec;
    }

    public function setDatec(?\DateTimeInterface $datec): static
    {
        $this->datec = $datec;

        return $this;
    }

    public function getFkUserAuthor(): ?int
    {
        return $this->fk_user_author;
    }

    public function setFkUserAuthor(?int $fk_user_author): static
    {
        $this->fk_user_author = $fk_user_author;

        return $this;
    }

    public function getPriceLevel(): ?int
    {
        return $this->price_level;
    }

    public function setPriceLevel(?int $price_level): static
    {
        $this->price_level = $price_level;

        return $this;
    }
}
