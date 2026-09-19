<?php

declare(strict_types=1);

namespace App\Entity\Dictionary;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_c_prospectlevel', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
class Prospectlevel
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, name: 'code', length: 12)]
    private string $code;

    #[ORM\Column(type: Types::STRING, name: 'label', length: 128, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'sortorder', nullable: true)]
    private ?int $sortorder = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'active', nullable: false, options: ['default' => 1])]
    private int $active = 1;

    #[ORM\Column(type: Types::STRING, name: 'module', length: 32, nullable: true)]
    private ?string $module = null;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    public function getSortorder(): ?int
    {
        return $this->sortorder;
    }

    public function setSortorder(?int $sortorder): static
    {
        $this->sortorder = $sortorder;

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
}
