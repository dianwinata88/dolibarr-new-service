<?php

declare(strict_types=1);

namespace App\Entity;

use App\Extrafields\Extrafields;
use App\Extrafields\ExtrafieldsAwareInterface;
use App\Extrafields\ExtrafieldsAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_categorie', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_categorie_ref', columns: ['entity', 'fk_parent', 'label', 'type'])]
#[Extrafields(table: 'llx_categories_extrafields')]
class Categorie implements ExtrafieldsAwareInterface
{
    use ExtrafieldsAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_parent', nullable: false, options: ['default' => 0])]
    private int $fk_parent = 0;

    #[ORM\Column(type: Types::STRING, name: 'label', length: 180, nullable: false)]
    private string $label;

    #[ORM\Column(type: Types::STRING, name: 'ref_ext', length: 255, nullable: true)]
    private ?string $ref_ext = null;

    #[ORM\Column(type: Types::INTEGER, name: 'type', nullable: false, options: ['default' => 1])]
    private int $type = 1;

    #[ORM\Column(type: Types::TEXT, name: 'description', length: 65535, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, name: 'color', length: 8, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::STRING, name: 'extraparams', length: 255, nullable: true)]
    private ?string $extraparams = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_creation', nullable: true)]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_creat', nullable: true)]
    private ?int $fk_user_creat = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_modif', nullable: true)]
    private ?int $fk_user_modif = null;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: 'tinyint', name: 'visible', nullable: false, options: ['default' => 1])]
    private int $visible = 1;

    #[ORM\Column(type: Types::INTEGER, name: 'position', nullable: true, options: ['default' => 0])]
    private ?int $position = 0;

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

    public function getFkParent(): int
    {
        return $this->fk_parent;
    }

    public function setFkParent(int $fk_parent): static
    {
        $this->fk_parent = $fk_parent;

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

    public function getRefExt(): ?string
    {
        return $this->ref_ext;
    }

    public function setRefExt(?string $ref_ext): static
    {
        $this->ref_ext = $ref_ext;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

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

    public function getExtraparams(): ?string
    {
        return $this->extraparams;
    }

    public function setExtraparams(?string $extraparams): static
    {
        $this->extraparams = $extraparams;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

    public function getFkUserCreat(): ?int
    {
        return $this->fk_user_creat;
    }

    public function setFkUserCreat(?int $fk_user_creat): static
    {
        $this->fk_user_creat = $fk_user_creat;

        return $this;
    }

    public function getFkUserModif(): ?int
    {
        return $this->fk_user_modif;
    }

    public function setFkUserModif(?int $fk_user_modif): static
    {
        $this->fk_user_modif = $fk_user_modif;

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

    public function getVisible(): int
    {
        return $this->visible;
    }

    public function setVisible(int $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

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
