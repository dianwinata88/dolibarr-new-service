<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_type_contact', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_c_type_contact_id', columns: ['element', 'source', 'code'])]
#[ORM\Index(name: 'idx_c_type_contact_code', columns: ['code'])]
class TypeContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::STRING, name: 'element', length: 64, nullable: false)]
    private string $element;

    #[ORM\Column(type: Types::STRING, name: 'source', length: 8, nullable: false, options: ['default' => 'external'])]
    private string $source = 'external';

    #[ORM\Column(type: Types::STRING, name: 'code', length: 32, nullable: false)]
    private string $code;

    #[ORM\Column(type: Types::STRING, name: 'libelle', length: 128, nullable: false)]
    private string $libelle;

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

    public function getElement(): string
    {
        return $this->element;
    }

    public function setElement(string $element): static
    {
        $this->element = $element;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

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

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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
