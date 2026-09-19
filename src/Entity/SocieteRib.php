<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_rib', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_societe_rib', columns: ['entity', 'label', 'fk_soc'])]
class SocieteRib
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'type', length: 32, nullable: false, options: ['default' => 'ban'])]
    private string $type = 'ban';

    #[ORM\Column(type: Types::STRING, name: 'label', length: 180, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: false)]
    private int $fk_soc;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datec', nullable: true)]
    private ?\DateTimeInterface $datec = null;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::STRING, name: 'bank', length: 255, nullable: true)]
    private ?string $bank = null;

    #[ORM\Column(type: Types::STRING, name: 'code_banque', length: 128, nullable: true)]
    private ?string $code_banque = null;

    #[ORM\Column(type: Types::STRING, name: 'code_guichet', length: 6, nullable: true)]
    private ?string $code_guichet = null;

    #[ORM\Column(type: Types::STRING, name: 'number', length: 255, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: Types::STRING, name: 'cle_rib', length: 5, nullable: true)]
    private ?string $cle_rib = null;

    #[ORM\Column(type: Types::STRING, name: 'bic', length: 20, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(type: Types::STRING, name: 'bic_intermediate', length: 11, nullable: true)]
    private ?string $bic_intermediate = null;

    #[ORM\Column(type: Types::STRING, name: 'iban_prefix', length: 100, nullable: true)]
    private ?string $iban_prefix = null;

    #[ORM\Column(type: Types::STRING, name: 'cci', length: 100, nullable: true)]
    private ?string $cci = null;

    #[ORM\Column(type: Types::STRING, name: 'domiciliation', length: 255, nullable: true)]
    private ?string $domiciliation = null;

    #[ORM\Column(type: Types::STRING, name: 'proprio', length: 60, nullable: true)]
    private ?string $proprio = null;

    #[ORM\Column(type: Types::STRING, name: 'owner_address', length: 255, nullable: true)]
    private ?string $owner_address = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'default_rib', nullable: false, options: ['default' => 0])]
    private int $default_rib = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'state_id', nullable: true)]
    private ?int $state_id = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_country', nullable: true)]
    private ?int $fk_country = null;

    #[ORM\Column(type: Types::STRING, name: 'currency_code', length: 3, nullable: true)]
    private ?string $currency_code = null;

    #[ORM\Column(type: Types::STRING, name: 'model_pdf', length: 255, nullable: true)]
    private ?string $model_pdf = null;

    #[ORM\Column(type: Types::STRING, name: 'last_main_doc', length: 255, nullable: true)]
    private ?string $last_main_doc = null;

    #[ORM\Column(type: Types::STRING, name: 'rum', length: 32, nullable: true)]
    private ?string $rum = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, name: 'date_rum', nullable: true)]
    private ?\DateTimeInterface $date_rum = null;

    #[ORM\Column(type: Types::STRING, name: 'frstrecur', length: 16, nullable: true, options: ['default' => 'FRST'])]
    private ?string $frstrecur = 'FRST';

    #[ORM\Column(type: Types::STRING, name: 'last_four', length: 4, nullable: true)]
    private ?string $last_four = null;

    #[ORM\Column(type: Types::STRING, name: 'card_type', length: 255, nullable: true)]
    private ?string $card_type = null;

    #[ORM\Column(type: Types::STRING, name: 'cvn', length: 255, nullable: true)]
    private ?string $cvn = null;

    #[ORM\Column(type: Types::INTEGER, name: 'exp_date_month', nullable: true)]
    private ?int $exp_date_month = null;

    #[ORM\Column(type: Types::INTEGER, name: 'exp_date_year', nullable: true)]
    private ?int $exp_date_year = null;

    #[ORM\Column(type: Types::STRING, name: 'country_code', length: 10, nullable: true)]
    private ?string $country_code = null;

    #[ORM\Column(type: Types::INTEGER, name: 'approved', nullable: true, options: ['default' => 0])]
    private ?int $approved = 0;

    #[ORM\Column(type: Types::STRING, name: 'email', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, name: 'ending_date', nullable: true)]
    private ?\DateTimeInterface $ending_date = null;

    #[ORM\Column(type: Types::FLOAT, name: 'max_total_amount_of_all_payments', precision: 24, scale: 8, nullable: true)]
    private ?float $max_total_amount_of_all_payments = null;

    #[ORM\Column(type: Types::STRING, name: 'preapproval_key', length: 255, nullable: true)]
    private ?string $preapproval_key = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, name: 'starting_date', nullable: true)]
    private ?\DateTimeInterface $starting_date = null;

    #[ORM\Column(type: Types::FLOAT, name: 'total_amount_of_all_payments', precision: 24, scale: 8, nullable: true)]
    private ?float $total_amount_of_all_payments = null;

    #[ORM\Column(type: Types::STRING, name: 'stripe_card_ref', length: 128, nullable: true)]
    private ?string $stripe_card_ref = null;

    #[ORM\Column(type: Types::STRING, name: 'stripe_account', length: 128, nullable: true)]
    private ?string $stripe_account = null;

    #[ORM\Column(type: Types::STRING, name: 'ext_payment_site', length: 128, nullable: true)]
    private ?string $ext_payment_site = null;

    #[ORM\Column(type: Types::STRING, name: 'extraparams', length: 255, nullable: true)]
    private ?string $extraparams = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_signature', nullable: true)]
    private ?\DateTimeInterface $date_signature = null;

    #[ORM\Column(type: Types::STRING, name: 'online_sign_ip', length: 48, nullable: true)]
    private ?string $online_sign_ip = null;

    #[ORM\Column(type: Types::STRING, name: 'online_sign_name', length: 64, nullable: true)]
    private ?string $online_sign_name = null;

    #[ORM\Column(type: Types::STRING, name: 'comment', length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::STRING, name: 'ipaddress', length: 68, nullable: true)]
    private ?string $ipaddress = null;

    #[ORM\Column(type: Types::INTEGER, name: 'status', nullable: false, options: ['default' => 1])]
    private int $status = 1;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getFkSoc(): int
    {
        return $this->fk_soc;
    }

    public function setFkSoc(int $fk_soc): static
    {
        $this->fk_soc = $fk_soc;

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

    public function getTms(): ?\DateTimeInterface
    {
        return $this->tms;
    }

    public function setTms(?\DateTimeInterface $tms): static
    {
        $this->tms = $tms;

        return $this;
    }

    public function getBank(): ?string
    {
        return $this->bank;
    }

    public function setBank(?string $bank): static
    {
        $this->bank = $bank;

        return $this;
    }

    public function getCodeBanque(): ?string
    {
        return $this->code_banque;
    }

    public function setCodeBanque(?string $code_banque): static
    {
        $this->code_banque = $code_banque;

        return $this;
    }

    public function getCodeGuichet(): ?string
    {
        return $this->code_guichet;
    }

    public function setCodeGuichet(?string $code_guichet): static
    {
        $this->code_guichet = $code_guichet;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getCleRib(): ?string
    {
        return $this->cle_rib;
    }

    public function setCleRib(?string $cle_rib): static
    {
        $this->cle_rib = $cle_rib;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;

        return $this;
    }

    public function getBicIntermediate(): ?string
    {
        return $this->bic_intermediate;
    }

    public function setBicIntermediate(?string $bic_intermediate): static
    {
        $this->bic_intermediate = $bic_intermediate;

        return $this;
    }

    public function getIbanPrefix(): ?string
    {
        return $this->iban_prefix;
    }

    public function setIbanPrefix(?string $iban_prefix): static
    {
        $this->iban_prefix = $iban_prefix;

        return $this;
    }

    public function getCci(): ?string
    {
        return $this->cci;
    }

    public function setCci(?string $cci): static
    {
        $this->cci = $cci;

        return $this;
    }

    public function getDomiciliation(): ?string
    {
        return $this->domiciliation;
    }

    public function setDomiciliation(?string $domiciliation): static
    {
        $this->domiciliation = $domiciliation;

        return $this;
    }

    public function getProprio(): ?string
    {
        return $this->proprio;
    }

    public function setProprio(?string $proprio): static
    {
        $this->proprio = $proprio;

        return $this;
    }

    public function getOwnerAddress(): ?string
    {
        return $this->owner_address;
    }

    public function setOwnerAddress(?string $owner_address): static
    {
        $this->owner_address = $owner_address;

        return $this;
    }

    public function getDefaultRib(): int
    {
        return $this->default_rib;
    }

    public function setDefaultRib(int $default_rib): static
    {
        $this->default_rib = $default_rib;

        return $this;
    }

    public function getStateId(): ?int
    {
        return $this->state_id;
    }

    public function setStateId(?int $state_id): static
    {
        $this->state_id = $state_id;

        return $this;
    }

    public function getFkCountry(): ?int
    {
        return $this->fk_country;
    }

    public function setFkCountry(?int $fk_country): static
    {
        $this->fk_country = $fk_country;

        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currency_code;
    }

    public function setCurrencyCode(?string $currency_code): static
    {
        $this->currency_code = $currency_code;

        return $this;
    }

    public function getModelPdf(): ?string
    {
        return $this->model_pdf;
    }

    public function setModelPdf(?string $model_pdf): static
    {
        $this->model_pdf = $model_pdf;

        return $this;
    }

    public function getLastMainDoc(): ?string
    {
        return $this->last_main_doc;
    }

    public function setLastMainDoc(?string $last_main_doc): static
    {
        $this->last_main_doc = $last_main_doc;

        return $this;
    }

    public function getRum(): ?string
    {
        return $this->rum;
    }

    public function setRum(?string $rum): static
    {
        $this->rum = $rum;

        return $this;
    }

    public function getDateRum(): ?\DateTimeInterface
    {
        return $this->date_rum;
    }

    public function setDateRum(?\DateTimeInterface $date_rum): static
    {
        $this->date_rum = $date_rum;

        return $this;
    }

    public function getFrstrecur(): ?string
    {
        return $this->frstrecur;
    }

    public function setFrstrecur(?string $frstrecur): static
    {
        $this->frstrecur = $frstrecur;

        return $this;
    }

    public function getLastFour(): ?string
    {
        return $this->last_four;
    }

    public function setLastFour(?string $last_four): static
    {
        $this->last_four = $last_four;

        return $this;
    }

    public function getCardType(): ?string
    {
        return $this->card_type;
    }

    public function setCardType(?string $card_type): static
    {
        $this->card_type = $card_type;

        return $this;
    }

    public function getCvn(): ?string
    {
        return $this->cvn;
    }

    public function setCvn(?string $cvn): static
    {
        $this->cvn = $cvn;

        return $this;
    }

    public function getExpDateMonth(): ?int
    {
        return $this->exp_date_month;
    }

    public function setExpDateMonth(?int $exp_date_month): static
    {
        $this->exp_date_month = $exp_date_month;

        return $this;
    }

    public function getExpDateYear(): ?int
    {
        return $this->exp_date_year;
    }

    public function setExpDateYear(?int $exp_date_year): static
    {
        $this->exp_date_year = $exp_date_year;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->country_code;
    }

    public function setCountryCode(?string $country_code): static
    {
        $this->country_code = $country_code;

        return $this;
    }

    public function getApproved(): ?int
    {
        return $this->approved;
    }

    public function setApproved(?int $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getEndingDate(): ?\DateTimeInterface
    {
        return $this->ending_date;
    }

    public function setEndingDate(?\DateTimeInterface $ending_date): static
    {
        $this->ending_date = $ending_date;

        return $this;
    }

    public function getMaxTotalAmountOfAllPayments(): ?float
    {
        return $this->max_total_amount_of_all_payments;
    }

    public function setMaxTotalAmountOfAllPayments(?float $max_total_amount_of_all_payments): static
    {
        $this->max_total_amount_of_all_payments = $max_total_amount_of_all_payments;

        return $this;
    }

    public function getPreapprovalKey(): ?string
    {
        return $this->preapproval_key;
    }

    public function setPreapprovalKey(?string $preapproval_key): static
    {
        $this->preapproval_key = $preapproval_key;

        return $this;
    }

    public function getStartingDate(): ?\DateTimeInterface
    {
        return $this->starting_date;
    }

    public function setStartingDate(?\DateTimeInterface $starting_date): static
    {
        $this->starting_date = $starting_date;

        return $this;
    }

    public function getTotalAmountOfAllPayments(): ?float
    {
        return $this->total_amount_of_all_payments;
    }

    public function setTotalAmountOfAllPayments(?float $total_amount_of_all_payments): static
    {
        $this->total_amount_of_all_payments = $total_amount_of_all_payments;

        return $this;
    }

    public function getStripeCardRef(): ?string
    {
        return $this->stripe_card_ref;
    }

    public function setStripeCardRef(?string $stripe_card_ref): static
    {
        $this->stripe_card_ref = $stripe_card_ref;

        return $this;
    }

    public function getStripeAccount(): ?string
    {
        return $this->stripe_account;
    }

    public function setStripeAccount(?string $stripe_account): static
    {
        $this->stripe_account = $stripe_account;

        return $this;
    }

    public function getExtPaymentSite(): ?string
    {
        return $this->ext_payment_site;
    }

    public function setExtPaymentSite(?string $ext_payment_site): static
    {
        $this->ext_payment_site = $ext_payment_site;

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

    public function getDateSignature(): ?\DateTimeInterface
    {
        return $this->date_signature;
    }

    public function setDateSignature(?\DateTimeInterface $date_signature): static
    {
        $this->date_signature = $date_signature;

        return $this;
    }

    public function getOnlineSignIp(): ?string
    {
        return $this->online_sign_ip;
    }

    public function setOnlineSignIp(?string $online_sign_ip): static
    {
        $this->online_sign_ip = $online_sign_ip;

        return $this;
    }

    public function getOnlineSignName(): ?string
    {
        return $this->online_sign_name;
    }

    public function setOnlineSignName(?string $online_sign_name): static
    {
        $this->online_sign_name = $online_sign_name;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getIpaddress(): ?string
    {
        return $this->ipaddress;
    }

    public function setIpaddress(?string $ipaddress): static
    {
        $this->ipaddress = $ipaddress;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

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
