<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_remise_except', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\Index(name: 'idx_societe_remise_except_fk_user', columns: ['fk_user'])]
#[ORM\Index(name: 'idx_societe_remise_except_fk_soc', columns: ['fk_soc'])]
#[ORM\Index(name: 'idx_societe_remise_except_fk_facture_line', columns: ['fk_facture_line'])]
#[ORM\Index(name: 'idx_societe_remise_except_fk_facture', columns: ['fk_facture'])]
#[ORM\Index(name: 'idx_societe_remise_except_fk_facture_source', columns: ['fk_facture_source'])]
#[ORM\Index(name: 'idx_societe_remise_except_discount_type', columns: ['discount_type'])]
class SocieteRemiseExcept
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: false)]
    private int $fk_soc;

    #[ORM\Column(type: Types::INTEGER, name: 'discount_type', nullable: false, options: ['default' => 0])]
    private int $discount_type = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datec', nullable: true)]
    private ?\DateTimeInterface $datec = null;

    #[ORM\Column(type: Types::FLOAT, name: 'amount_ht', precision: 24, scale: 8, nullable: false)]
    private float $amount_ht;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'amount_tva',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $amount_tva = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'amount_localtax1',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $amount_localtax1 = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'amount_localtax2',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $amount_localtax2 = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'amount_ttc',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $amount_ttc = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'tva_tx',
        precision: 7,
        scale: 4,
        nullable: false,
        options: ['default' => '0.0000'],
    )]
    private float $tva_tx = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'localtax1_tx',
        precision: 7,
        scale: 4,
        nullable: false,
        options: ['default' => '0.0000'],
    )]
    private float $localtax1_tx = 0;

    #[ORM\Column(type: Types::STRING, name: 'localtax1_type', length: 10, nullable: true)]
    private ?string $localtax1_type = null;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'localtax2_tx',
        precision: 7,
        scale: 4,
        nullable: false,
        options: ['default' => '0.0000'],
    )]
    private float $localtax2_tx = 0;

    #[ORM\Column(type: Types::STRING, name: 'localtax2_type', length: 10, nullable: true)]
    private ?string $localtax2_type = null;

    #[ORM\Column(type: Types::STRING, name: 'vat_src_code', length: 10, nullable: true, options: ['default' => ''])]
    private ?string $vat_src_code = '';

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user', nullable: false)]
    private int $fk_user;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_facture_line', nullable: true)]
    private ?int $fk_facture_line = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_facture', nullable: true)]
    private ?int $fk_facture = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_facture_source', nullable: true)]
    private ?int $fk_facture_source = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_invoice_supplier_line', nullable: true)]
    private ?int $fk_invoice_supplier_line = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_invoice_supplier', nullable: true)]
    private ?int $fk_invoice_supplier = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_invoice_supplier_source', nullable: true)]
    private ?int $fk_invoice_supplier_source = null;

    #[ORM\Column(type: Types::TEXT, name: 'description', length: 65535, nullable: false)]
    private string $description;

    #[ORM\Column(type: Types::STRING, name: 'multicurrency_code', length: 3, nullable: true)]
    private ?string $multicurrency_code = null;

    #[ORM\Column(type: Types::FLOAT, name: 'multicurrency_tx', precision: 24, scale: 8, nullable: true)]
    private ?float $multicurrency_tx = null;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'multicurrency_amount_ht',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $multicurrency_amount_ht = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'multicurrency_amount_tva',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $multicurrency_amount_tva = 0;

    #[ORM\Column(
        type: Types::FLOAT,
        name: 'multicurrency_amount_ttc',
        precision: 24,
        scale: 8,
        nullable: false,
        options: ['default' => '0.00000000'],
    )]
    private float $multicurrency_amount_ttc = 0;

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

    public function getFkSoc(): int
    {
        return $this->fk_soc;
    }

    public function setFkSoc(int $fk_soc): static
    {
        $this->fk_soc = $fk_soc;

        return $this;
    }

    public function getDiscountType(): int
    {
        return $this->discount_type;
    }

    public function setDiscountType(int $discount_type): static
    {
        $this->discount_type = $discount_type;

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

    public function getAmountHt(): float
    {
        return $this->amount_ht;
    }

    public function setAmountHt(float $amount_ht): static
    {
        $this->amount_ht = $amount_ht;

        return $this;
    }

    public function getAmountTva(): float
    {
        return $this->amount_tva;
    }

    public function setAmountTva(float $amount_tva): static
    {
        $this->amount_tva = $amount_tva;

        return $this;
    }

    public function getAmountLocaltax1(): float
    {
        return $this->amount_localtax1;
    }

    public function setAmountLocaltax1(float $amount_localtax1): static
    {
        $this->amount_localtax1 = $amount_localtax1;

        return $this;
    }

    public function getAmountLocaltax2(): float
    {
        return $this->amount_localtax2;
    }

    public function setAmountLocaltax2(float $amount_localtax2): static
    {
        $this->amount_localtax2 = $amount_localtax2;

        return $this;
    }

    public function getAmountTtc(): float
    {
        return $this->amount_ttc;
    }

    public function setAmountTtc(float $amount_ttc): static
    {
        $this->amount_ttc = $amount_ttc;

        return $this;
    }

    public function getTvaTx(): float
    {
        return $this->tva_tx;
    }

    public function setTvaTx(float $tva_tx): static
    {
        $this->tva_tx = $tva_tx;

        return $this;
    }

    public function getLocaltax1Tx(): float
    {
        return $this->localtax1_tx;
    }

    public function setLocaltax1Tx(float $localtax1_tx): static
    {
        $this->localtax1_tx = $localtax1_tx;

        return $this;
    }

    public function getLocaltax1Type(): ?string
    {
        return $this->localtax1_type;
    }

    public function setLocaltax1Type(?string $localtax1_type): static
    {
        $this->localtax1_type = $localtax1_type;

        return $this;
    }

    public function getLocaltax2Tx(): float
    {
        return $this->localtax2_tx;
    }

    public function setLocaltax2Tx(float $localtax2_tx): static
    {
        $this->localtax2_tx = $localtax2_tx;

        return $this;
    }

    public function getLocaltax2Type(): ?string
    {
        return $this->localtax2_type;
    }

    public function setLocaltax2Type(?string $localtax2_type): static
    {
        $this->localtax2_type = $localtax2_type;

        return $this;
    }

    public function getVatSrcCode(): ?string
    {
        return $this->vat_src_code;
    }

    public function setVatSrcCode(?string $vat_src_code): static
    {
        $this->vat_src_code = $vat_src_code;

        return $this;
    }

    public function getFkUser(): int
    {
        return $this->fk_user;
    }

    public function setFkUser(int $fk_user): static
    {
        $this->fk_user = $fk_user;

        return $this;
    }

    public function getFkFactureLine(): ?int
    {
        return $this->fk_facture_line;
    }

    public function setFkFactureLine(?int $fk_facture_line): static
    {
        $this->fk_facture_line = $fk_facture_line;

        return $this;
    }

    public function getFkFacture(): ?int
    {
        return $this->fk_facture;
    }

    public function setFkFacture(?int $fk_facture): static
    {
        $this->fk_facture = $fk_facture;

        return $this;
    }

    public function getFkFactureSource(): ?int
    {
        return $this->fk_facture_source;
    }

    public function setFkFactureSource(?int $fk_facture_source): static
    {
        $this->fk_facture_source = $fk_facture_source;

        return $this;
    }

    public function getFkInvoiceSupplierLine(): ?int
    {
        return $this->fk_invoice_supplier_line;
    }

    public function setFkInvoiceSupplierLine(?int $fk_invoice_supplier_line): static
    {
        $this->fk_invoice_supplier_line = $fk_invoice_supplier_line;

        return $this;
    }

    public function getFkInvoiceSupplier(): ?int
    {
        return $this->fk_invoice_supplier;
    }

    public function setFkInvoiceSupplier(?int $fk_invoice_supplier): static
    {
        $this->fk_invoice_supplier = $fk_invoice_supplier;

        return $this;
    }

    public function getFkInvoiceSupplierSource(): ?int
    {
        return $this->fk_invoice_supplier_source;
    }

    public function setFkInvoiceSupplierSource(?int $fk_invoice_supplier_source): static
    {
        $this->fk_invoice_supplier_source = $fk_invoice_supplier_source;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMulticurrencyCode(): ?string
    {
        return $this->multicurrency_code;
    }

    public function setMulticurrencyCode(?string $multicurrency_code): static
    {
        $this->multicurrency_code = $multicurrency_code;

        return $this;
    }

    public function getMulticurrencyTx(): ?float
    {
        return $this->multicurrency_tx;
    }

    public function setMulticurrencyTx(?float $multicurrency_tx): static
    {
        $this->multicurrency_tx = $multicurrency_tx;

        return $this;
    }

    public function getMulticurrencyAmountHt(): float
    {
        return $this->multicurrency_amount_ht;
    }

    public function setMulticurrencyAmountHt(float $multicurrency_amount_ht): static
    {
        $this->multicurrency_amount_ht = $multicurrency_amount_ht;

        return $this;
    }

    public function getMulticurrencyAmountTva(): float
    {
        return $this->multicurrency_amount_tva;
    }

    public function setMulticurrencyAmountTva(float $multicurrency_amount_tva): static
    {
        $this->multicurrency_amount_tva = $multicurrency_amount_tva;

        return $this;
    }

    public function getMulticurrencyAmountTtc(): float
    {
        return $this->multicurrency_amount_ttc;
    }

    public function setMulticurrencyAmountTtc(float $multicurrency_amount_ttc): static
    {
        $this->multicurrency_amount_ttc = $multicurrency_amount_ttc;

        return $this;
    }
}
