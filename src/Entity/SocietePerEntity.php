<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_perentity', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_societe_perentity', columns: ['fk_soc', 'entity'])]
#[ORM\Index(name: 'idx_societe_perentity_fk_soc', columns: ['fk_soc'])]
class SocietePerEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_customer_general', length: 32, nullable: true)]
    private ?string $accountancy_code_customer_general = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_customer', length: 32, nullable: true)]
    private ?string $accountancy_code_customer = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_supplier_general', length: 32, nullable: true)]
    private ?string $accountancy_code_supplier_general = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_supplier', length: 32, nullable: true)]
    private ?string $accountancy_code_supplier = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_sell', length: 32, nullable: true)]
    private ?string $accountancy_code_sell = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_buy', length: 32, nullable: true)]
    private ?string $accountancy_code_buy = null;

    #[ORM\Column(type: 'tinyint', name: 'vat_reverse_charge', nullable: true, options: ['default' => 0])]
    private ?int $vat_reverse_charge = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_account', nullable: true)]
    private ?int $fk_account = null;

    #[ORM\Column(type: Types::INTEGER, name: 'mode_reglement', nullable: true)]
    private ?int $mode_reglement = null;

    #[ORM\Column(type: 'tinyint', name: 'cond_reglement', nullable: true)]
    private ?int $cond_reglement = null;

    #[ORM\Column(type: 'tinyint', name: 'mode_reglement_supplier', nullable: true)]
    private ?int $mode_reglement_supplier = null;

    #[ORM\Column(type: 'tinyint', name: 'cond_reglement_supplier', nullable: true)]
    private ?int $cond_reglement_supplier = null;

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

    public function getEntity(): int
    {
        return $this->entity;
    }

    public function setEntity(int $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getAccountancyCodeCustomerGeneral(): ?string
    {
        return $this->accountancy_code_customer_general;
    }

    public function setAccountancyCodeCustomerGeneral(?string $accountancy_code_customer_general): static
    {
        $this->accountancy_code_customer_general = $accountancy_code_customer_general;

        return $this;
    }

    public function getAccountancyCodeCustomer(): ?string
    {
        return $this->accountancy_code_customer;
    }

    public function setAccountancyCodeCustomer(?string $accountancy_code_customer): static
    {
        $this->accountancy_code_customer = $accountancy_code_customer;

        return $this;
    }

    public function getAccountancyCodeSupplierGeneral(): ?string
    {
        return $this->accountancy_code_supplier_general;
    }

    public function setAccountancyCodeSupplierGeneral(?string $accountancy_code_supplier_general): static
    {
        $this->accountancy_code_supplier_general = $accountancy_code_supplier_general;

        return $this;
    }

    public function getAccountancyCodeSupplier(): ?string
    {
        return $this->accountancy_code_supplier;
    }

    public function setAccountancyCodeSupplier(?string $accountancy_code_supplier): static
    {
        $this->accountancy_code_supplier = $accountancy_code_supplier;

        return $this;
    }

    public function getAccountancyCodeSell(): ?string
    {
        return $this->accountancy_code_sell;
    }

    public function setAccountancyCodeSell(?string $accountancy_code_sell): static
    {
        $this->accountancy_code_sell = $accountancy_code_sell;

        return $this;
    }

    public function getAccountancyCodeBuy(): ?string
    {
        return $this->accountancy_code_buy;
    }

    public function setAccountancyCodeBuy(?string $accountancy_code_buy): static
    {
        $this->accountancy_code_buy = $accountancy_code_buy;

        return $this;
    }

    public function getVatReverseCharge(): ?int
    {
        return $this->vat_reverse_charge;
    }

    public function setVatReverseCharge(?int $vat_reverse_charge): static
    {
        $this->vat_reverse_charge = $vat_reverse_charge;

        return $this;
    }

    public function getFkAccount(): ?int
    {
        return $this->fk_account;
    }

    public function setFkAccount(?int $fk_account): static
    {
        $this->fk_account = $fk_account;

        return $this;
    }

    public function getModeReglement(): ?int
    {
        return $this->mode_reglement;
    }

    public function setModeReglement(?int $mode_reglement): static
    {
        $this->mode_reglement = $mode_reglement;

        return $this;
    }

    public function getCondReglement(): ?int
    {
        return $this->cond_reglement;
    }

    public function setCondReglement(?int $cond_reglement): static
    {
        $this->cond_reglement = $cond_reglement;

        return $this;
    }

    public function getModeReglementSupplier(): ?int
    {
        return $this->mode_reglement_supplier;
    }

    public function setModeReglementSupplier(?int $mode_reglement_supplier): static
    {
        $this->mode_reglement_supplier = $mode_reglement_supplier;

        return $this;
    }

    public function getCondReglementSupplier(): ?int
    {
        return $this->cond_reglement_supplier;
    }

    public function setCondReglementSupplier(?int $cond_reglement_supplier): static
    {
        $this->cond_reglement_supplier = $cond_reglement_supplier;

        return $this;
    }
}
