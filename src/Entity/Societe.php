<?php

declare(strict_types=1);

namespace App\Entity;

use App\Extrafields\Extrafields;
use App\Extrafields\ExtrafieldsAwareInterface;
use App\Extrafields\ExtrafieldsAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_societe_prefix_comm', columns: ['prefix_comm', 'entity'])]
#[ORM\UniqueConstraint(name: 'uk_societe_code_client', columns: ['code_client', 'entity'])]
#[ORM\UniqueConstraint(name: 'uk_societe_code_fournisseur', columns: ['code_fournisseur', 'entity'])]
#[ORM\UniqueConstraint(name: 'uk_societe_barcode', columns: ['barcode', 'fk_barcode_type', 'entity'])]
#[ORM\Index(name: 'idx_societe_nom', columns: ['nom'])]
#[ORM\Index(name: 'idx_societe_user_creat', columns: ['fk_user_creat'])]
#[ORM\Index(name: 'idx_societe_user_modif', columns: ['fk_user_modif'])]
#[ORM\Index(name: 'idx_societe_stcomm', columns: ['fk_stcomm'])]
#[ORM\Index(name: 'idx_societe_pays', columns: ['fk_pays'])]
#[ORM\Index(name: 'idx_societe_account', columns: ['fk_account'])]
#[ORM\Index(name: 'idx_societe_prospectlevel', columns: ['fk_prospectlevel'])]
#[ORM\Index(name: 'idx_societe_typent', columns: ['fk_typent'])]
#[ORM\Index(name: 'idx_societe_forme_juridique', columns: ['fk_forme_juridique'])]
#[ORM\Index(name: 'idx_societe_shipping_method', columns: ['fk_shipping_method'])]
#[Extrafields(table: 'llx_societe_extrafields')]
class Societe implements ExtrafieldsAwareInterface
{
    use ExtrafieldsAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::STRING, name: 'nom', length: 128, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, name: 'name_alias', length: 128, nullable: true)]
    private ?string $name_alias = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'ref_ext', length: 255, nullable: true)]
    private ?string $ref_ext = null;

    #[ORM\Column(type: 'tinyint', name: 'statut', nullable: true, options: ['default' => 0])]
    private ?int $statut = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'parent', nullable: true)]
    private ?int $parent = null;

    #[ORM\Column(type: 'tinyint', name: 'status', nullable: true, options: ['default' => 1])]
    private ?int $status = 1;

    #[ORM\Column(type: Types::STRING, name: 'code_client', length: 24, nullable: true)]
    private ?string $code_client = null;

    #[ORM\Column(type: Types::STRING, name: 'code_fournisseur', length: 24, nullable: true)]
    private ?string $code_fournisseur = null;

    #[ORM\Column(type: Types::STRING, name: 'tp_payment_reference', length: 25, nullable: true)]
    private ?string $tp_payment_reference = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_customer_general', length: 32, nullable: true)]
    private ?string $accountancy_code_customer_general = null;

    #[ORM\Column(type: Types::STRING, name: 'code_compta', length: 32, nullable: true)]
    private ?string $code_compta = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_supplier_general', length: 32, nullable: true)]
    private ?string $accountancy_code_supplier_general = null;

    #[ORM\Column(type: Types::STRING, name: 'code_compta_fournisseur', length: 32, nullable: true)]
    private ?string $code_compta_fournisseur = null;

    #[ORM\Column(type: Types::STRING, name: 'address', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, name: 'zip', length: 25, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(type: Types::STRING, name: 'town', length: 50, nullable: true)]
    private ?string $town = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_departement', nullable: true, options: ['default' => 0])]
    private ?int $fk_departement = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_pays', nullable: true, options: ['default' => 0])]
    private ?int $fk_pays = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'geolat', precision: 24, scale: 8, nullable: true)]
    private ?float $geolat = null;

    #[ORM\Column(type: Types::FLOAT, name: 'geolong', precision: 24, scale: 8, nullable: true)]
    private ?float $geolong = null;

    #[ORM\Column(type: 'point', name: 'geopoint', nullable: true)]
    private ?string $geopoint = null;

    #[ORM\Column(type: Types::STRING, name: 'georesultcode', length: 16, nullable: true)]
    private ?string $georesultcode = null;

    #[ORM\Column(type: Types::STRING, name: 'phone', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, name: 'phone_mobile', length: 30, nullable: true)]
    private ?string $phone_mobile = null;

    #[ORM\Column(type: Types::STRING, name: 'fax', length: 30, nullable: true)]
    private ?string $fax = null;

    #[ORM\Column(type: Types::STRING, name: 'url', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::STRING, name: 'email', length: 128, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_account', nullable: true, options: ['default' => 0])]
    private ?int $fk_account = 0;

    #[ORM\Column(type: Types::TEXT, name: 'socialnetworks', length: 65535, nullable: true)]
    private ?string $socialnetworks = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_effectif', nullable: true, options: ['default' => 0])]
    private ?int $fk_effectif = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_typent', nullable: true)]
    private ?int $fk_typent = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_forme_juridique', nullable: true, options: ['default' => 0])]
    private ?int $fk_forme_juridique = 0;

    #[ORM\Column(type: Types::DATE_MUTABLE, name: 'birth', nullable: true)]
    private ?\DateTimeInterface $birth = null;

    #[ORM\Column(type: Types::STRING, name: 'fk_currency', length: 3, nullable: true)]
    private ?string $fk_currency = null;

    #[ORM\Column(type: Types::STRING, name: 'siren', length: 128, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(type: Types::STRING, name: 'siret', length: 128, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(type: Types::STRING, name: 'ape', length: 128, nullable: true)]
    private ?string $ape = null;

    #[ORM\Column(type: Types::STRING, name: 'idprof4', length: 128, nullable: true)]
    private ?string $idprof4 = null;

    #[ORM\Column(type: Types::STRING, name: 'idprof5', length: 128, nullable: true)]
    private ?string $idprof5 = null;

    #[ORM\Column(type: Types::STRING, name: 'idprof6', length: 128, nullable: true)]
    private ?string $idprof6 = null;

    #[ORM\Column(type: Types::STRING, name: 'euid', length: 64, nullable: true)]
    private ?string $euid = null;

    #[ORM\Column(type: Types::STRING, name: 'tva_intra', length: 20, nullable: true)]
    private ?string $tva_intra = null;

    #[ORM\Column(type: Types::FLOAT, name: 'capital', precision: 24, scale: 8, nullable: true)]
    private ?float $capital = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_stcomm', nullable: false, options: ['default' => 0])]
    private int $fk_stcomm = 0;

    #[ORM\Column(type: Types::TEXT, name: 'note_private', length: 65535, nullable: true)]
    private ?string $note_private = null;

    #[ORM\Column(type: Types::TEXT, name: 'note_public', length: 65535, nullable: true)]
    private ?string $note_public = null;

    #[ORM\Column(type: Types::STRING, name: 'model_pdf', length: 255, nullable: true)]
    private ?string $model_pdf = null;

    #[ORM\Column(type: Types::STRING, name: 'last_main_doc', length: 255, nullable: true)]
    private ?string $last_main_doc = null;

    #[ORM\Column(type: Types::STRING, name: 'prefix_comm', length: 5, nullable: true)]
    private ?string $prefix_comm = null;

    #[ORM\Column(type: 'tinyint', name: 'client', nullable: true, options: ['default' => 0])]
    private ?int $client = 0;

    #[ORM\Column(type: 'tinyint', name: 'fournisseur', nullable: true, options: ['default' => 0])]
    private ?int $fournisseur = 0;

    #[ORM\Column(type: Types::STRING, name: 'supplier_account', length: 32, nullable: true)]
    private ?string $supplier_account = null;

    #[ORM\Column(type: Types::STRING, name: 'fk_prospectlevel', length: 12, nullable: true)]
    private ?string $fk_prospectlevel = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_incoterms', nullable: true)]
    private ?int $fk_incoterms = null;

    #[ORM\Column(type: Types::STRING, name: 'location_incoterms', length: 255, nullable: true)]
    private ?string $location_incoterms = null;

    #[ORM\Column(type: 'tinyint', name: 'customer_bad', nullable: true, options: ['default' => 0])]
    private ?int $customer_bad = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'customer_rate', nullable: true, options: ['default' => '0'])]
    private ?float $customer_rate = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'supplier_rate', nullable: true, options: ['default' => '0'])]
    private ?float $supplier_rate = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'remise_client', nullable: true, options: ['default' => '0'])]
    private ?float $remise_client = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'remise_supplier', nullable: true, options: ['default' => '0'])]
    private ?float $remise_supplier = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'mode_reglement', nullable: true)]
    private ?int $mode_reglement = null;

    #[ORM\Column(type: 'tinyint', name: 'cond_reglement', nullable: true)]
    private ?int $cond_reglement = null;

    #[ORM\Column(type: Types::STRING, name: 'deposit_percent', length: 63, nullable: true)]
    private ?string $deposit_percent = null;

    #[ORM\Column(type: 'tinyint', name: 'transport_mode', nullable: true)]
    private ?int $transport_mode = null;

    #[ORM\Column(type: 'tinyint', name: 'mode_reglement_supplier', nullable: true)]
    private ?int $mode_reglement_supplier = null;

    #[ORM\Column(type: 'tinyint', name: 'cond_reglement_supplier', nullable: true)]
    private ?int $cond_reglement_supplier = null;

    #[ORM\Column(type: 'tinyint', name: 'transport_mode_supplier', nullable: true)]
    private ?int $transport_mode_supplier = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_shipping_method', nullable: true)]
    private ?int $fk_shipping_method = null;

    #[ORM\Column(type: 'tinyint', name: 'tva_assuj', nullable: true, options: ['default' => 1])]
    private ?int $tva_assuj = 1;

    #[ORM\Column(type: Types::STRING, name: 'vatexemptcode', length: 24, nullable: true)]
    private ?string $vatexemptcode = null;

    #[ORM\Column(type: 'tinyint', name: 'vat_reverse_charge', nullable: true, options: ['default' => 0])]
    private ?int $vat_reverse_charge = 0;

    #[ORM\Column(type: 'tinyint', name: 'localtax1_assuj', nullable: true, options: ['default' => 0])]
    private ?int $localtax1_assuj = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'localtax1_value', precision: 7, scale: 4, nullable: true)]
    private ?float $localtax1_value = null;

    #[ORM\Column(type: 'tinyint', name: 'localtax2_assuj', nullable: true, options: ['default' => 0])]
    private ?int $localtax2_assuj = 0;

    #[ORM\Column(type: Types::FLOAT, name: 'localtax2_value', precision: 7, scale: 4, nullable: true)]
    private ?float $localtax2_value = null;

    #[ORM\Column(type: Types::STRING, name: 'barcode', length: 180, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_barcode_type', nullable: true, options: ['default' => 0])]
    private ?int $fk_barcode_type = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'price_level', nullable: true)]
    private ?int $price_level = null;

    #[ORM\Column(type: Types::FLOAT, name: 'outstanding_limit', precision: 24, scale: 8, nullable: true)]
    private ?float $outstanding_limit = null;

    #[ORM\Column(type: Types::FLOAT, name: 'order_min_amount', precision: 24, scale: 8, nullable: true)]
    private ?float $order_min_amount = null;

    #[ORM\Column(type: Types::FLOAT, name: 'supplier_order_min_amount', precision: 24, scale: 8, nullable: true)]
    private ?float $supplier_order_min_amount = null;

    #[ORM\Column(type: Types::STRING, name: 'default_lang', length: 6, nullable: true)]
    private ?string $default_lang = null;

    #[ORM\Column(type: Types::STRING, name: 'logo', length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::STRING, name: 'logo_squarred', length: 255, nullable: true)]
    private ?string $logo_squarred = null;

    #[ORM\Column(type: Types::STRING, name: 'canvas', length: 32, nullable: true)]
    private ?string $canvas = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_warehouse', nullable: true)]
    private ?int $fk_warehouse = null;

    #[ORM\Column(type: Types::STRING, name: 'webservices_url', length: 255, nullable: true)]
    private ?string $webservices_url = null;

    #[ORM\Column(type: Types::STRING, name: 'webservices_key', length: 128, nullable: true)]
    private ?string $webservices_key = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_sell', length: 32, nullable: true)]
    private ?string $accountancy_code_sell = null;

    #[ORM\Column(type: Types::STRING, name: 'accountancy_code_buy', length: 32, nullable: true)]
    private ?string $accountancy_code_buy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datec', nullable: true)]
    private ?\DateTimeInterface $datec = null;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_creat', nullable: true)]
    private ?int $fk_user_creat = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_modif', nullable: true)]
    private ?int $fk_user_modif = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_multicurrency', nullable: true)]
    private ?int $fk_multicurrency = null;

    #[ORM\Column(type: Types::STRING, name: 'multicurrency_code', length: 3, nullable: true)]
    private ?string $multicurrency_code = null;

    #[ORM\Column(type: Types::STRING, name: 'ip', length: 250, nullable: true)]
    private ?string $ip = null;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNameAlias(): ?string
    {
        return $this->name_alias;
    }

    public function setNameAlias(?string $name_alias): static
    {
        $this->name_alias = $name_alias;

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

    public function getRefExt(): ?string
    {
        return $this->ref_ext;
    }

    public function setRefExt(?string $ref_ext): static
    {
        $this->ref_ext = $ref_ext;

        return $this;
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function setStatut(?int $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCodeClient(): ?string
    {
        return $this->code_client;
    }

    public function setCodeClient(?string $code_client): static
    {
        $this->code_client = $code_client;

        return $this;
    }

    public function getCodeFournisseur(): ?string
    {
        return $this->code_fournisseur;
    }

    public function setCodeFournisseur(?string $code_fournisseur): static
    {
        $this->code_fournisseur = $code_fournisseur;

        return $this;
    }

    public function getTpPaymentReference(): ?string
    {
        return $this->tp_payment_reference;
    }

    public function setTpPaymentReference(?string $tp_payment_reference): static
    {
        $this->tp_payment_reference = $tp_payment_reference;

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

    public function getCodeCompta(): ?string
    {
        return $this->code_compta;
    }

    public function setCodeCompta(?string $code_compta): static
    {
        $this->code_compta = $code_compta;

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

    public function getCodeComptaFournisseur(): ?string
    {
        return $this->code_compta_fournisseur;
    }

    public function setCodeComptaFournisseur(?string $code_compta_fournisseur): static
    {
        $this->code_compta_fournisseur = $code_compta_fournisseur;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getTown(): ?string
    {
        return $this->town;
    }

    public function setTown(?string $town): static
    {
        $this->town = $town;

        return $this;
    }

    public function getFkDepartement(): ?int
    {
        return $this->fk_departement;
    }

    public function setFkDepartement(?int $fk_departement): static
    {
        $this->fk_departement = $fk_departement;

        return $this;
    }

    public function getFkPays(): ?int
    {
        return $this->fk_pays;
    }

    public function setFkPays(?int $fk_pays): static
    {
        $this->fk_pays = $fk_pays;

        return $this;
    }

    public function getGeolat(): ?float
    {
        return $this->geolat;
    }

    public function setGeolat(?float $geolat): static
    {
        $this->geolat = $geolat;

        return $this;
    }

    public function getGeolong(): ?float
    {
        return $this->geolong;
    }

    public function setGeolong(?float $geolong): static
    {
        $this->geolong = $geolong;

        return $this;
    }

    public function getGeopoint(): ?string
    {
        return $this->geopoint;
    }

    public function setGeopoint(?string $geopoint): static
    {
        $this->geopoint = $geopoint;

        return $this;
    }

    public function getGeoresultcode(): ?string
    {
        return $this->georesultcode;
    }

    public function setGeoresultcode(?string $georesultcode): static
    {
        $this->georesultcode = $georesultcode;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhoneMobile(): ?string
    {
        return $this->phone_mobile;
    }

    public function setPhoneMobile(?string $phone_mobile): static
    {
        $this->phone_mobile = $phone_mobile;

        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

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

    public function getFkAccount(): ?int
    {
        return $this->fk_account;
    }

    public function setFkAccount(?int $fk_account): static
    {
        $this->fk_account = $fk_account;

        return $this;
    }

    public function getSocialnetworks(): ?string
    {
        return $this->socialnetworks;
    }

    public function setSocialnetworks(?string $socialnetworks): static
    {
        $this->socialnetworks = $socialnetworks;

        return $this;
    }

    public function getFkEffectif(): ?int
    {
        return $this->fk_effectif;
    }

    public function setFkEffectif(?int $fk_effectif): static
    {
        $this->fk_effectif = $fk_effectif;

        return $this;
    }

    public function getFkTypent(): ?int
    {
        return $this->fk_typent;
    }

    public function setFkTypent(?int $fk_typent): static
    {
        $this->fk_typent = $fk_typent;

        return $this;
    }

    public function getFkFormeJuridique(): ?int
    {
        return $this->fk_forme_juridique;
    }

    public function setFkFormeJuridique(?int $fk_forme_juridique): static
    {
        $this->fk_forme_juridique = $fk_forme_juridique;

        return $this;
    }

    public function getBirth(): ?\DateTimeInterface
    {
        return $this->birth;
    }

    public function setBirth(?\DateTimeInterface $birth): static
    {
        $this->birth = $birth;

        return $this;
    }

    public function getFkCurrency(): ?string
    {
        return $this->fk_currency;
    }

    public function setFkCurrency(?string $fk_currency): static
    {
        $this->fk_currency = $fk_currency;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getApe(): ?string
    {
        return $this->ape;
    }

    public function setApe(?string $ape): static
    {
        $this->ape = $ape;

        return $this;
    }

    public function getIdprof4(): ?string
    {
        return $this->idprof4;
    }

    public function setIdprof4(?string $idprof4): static
    {
        $this->idprof4 = $idprof4;

        return $this;
    }

    public function getIdprof5(): ?string
    {
        return $this->idprof5;
    }

    public function setIdprof5(?string $idprof5): static
    {
        $this->idprof5 = $idprof5;

        return $this;
    }

    public function getIdprof6(): ?string
    {
        return $this->idprof6;
    }

    public function setIdprof6(?string $idprof6): static
    {
        $this->idprof6 = $idprof6;

        return $this;
    }

    public function getEuid(): ?string
    {
        return $this->euid;
    }

    public function setEuid(?string $euid): static
    {
        $this->euid = $euid;

        return $this;
    }

    public function getTvaIntra(): ?string
    {
        return $this->tva_intra;
    }

    public function setTvaIntra(?string $tva_intra): static
    {
        $this->tva_intra = $tva_intra;

        return $this;
    }

    public function getCapital(): ?float
    {
        return $this->capital;
    }

    public function setCapital(?float $capital): static
    {
        $this->capital = $capital;

        return $this;
    }

    public function getFkStcomm(): int
    {
        return $this->fk_stcomm;
    }

    public function setFkStcomm(int $fk_stcomm): static
    {
        $this->fk_stcomm = $fk_stcomm;

        return $this;
    }

    public function getNotePrivate(): ?string
    {
        return $this->note_private;
    }

    public function setNotePrivate(?string $note_private): static
    {
        $this->note_private = $note_private;

        return $this;
    }

    public function getNotePublic(): ?string
    {
        return $this->note_public;
    }

    public function setNotePublic(?string $note_public): static
    {
        $this->note_public = $note_public;

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

    public function getPrefixComm(): ?string
    {
        return $this->prefix_comm;
    }

    public function setPrefixComm(?string $prefix_comm): static
    {
        $this->prefix_comm = $prefix_comm;

        return $this;
    }

    public function getClient(): ?int
    {
        return $this->client;
    }

    public function setClient(?int $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getFournisseur(): ?int
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?int $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getSupplierAccount(): ?string
    {
        return $this->supplier_account;
    }

    public function setSupplierAccount(?string $supplier_account): static
    {
        $this->supplier_account = $supplier_account;

        return $this;
    }

    public function getFkProspectlevel(): ?string
    {
        return $this->fk_prospectlevel;
    }

    public function setFkProspectlevel(?string $fk_prospectlevel): static
    {
        $this->fk_prospectlevel = $fk_prospectlevel;

        return $this;
    }

    public function getFkIncoterms(): ?int
    {
        return $this->fk_incoterms;
    }

    public function setFkIncoterms(?int $fk_incoterms): static
    {
        $this->fk_incoterms = $fk_incoterms;

        return $this;
    }

    public function getLocationIncoterms(): ?string
    {
        return $this->location_incoterms;
    }

    public function setLocationIncoterms(?string $location_incoterms): static
    {
        $this->location_incoterms = $location_incoterms;

        return $this;
    }

    public function getCustomerBad(): ?int
    {
        return $this->customer_bad;
    }

    public function setCustomerBad(?int $customer_bad): static
    {
        $this->customer_bad = $customer_bad;

        return $this;
    }

    public function getCustomerRate(): ?float
    {
        return $this->customer_rate;
    }

    public function setCustomerRate(?float $customer_rate): static
    {
        $this->customer_rate = $customer_rate;

        return $this;
    }

    public function getSupplierRate(): ?float
    {
        return $this->supplier_rate;
    }

    public function setSupplierRate(?float $supplier_rate): static
    {
        $this->supplier_rate = $supplier_rate;

        return $this;
    }

    public function getRemiseClient(): ?float
    {
        return $this->remise_client;
    }

    public function setRemiseClient(?float $remise_client): static
    {
        $this->remise_client = $remise_client;

        return $this;
    }

    public function getRemiseSupplier(): ?float
    {
        return $this->remise_supplier;
    }

    public function setRemiseSupplier(?float $remise_supplier): static
    {
        $this->remise_supplier = $remise_supplier;

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

    public function getDepositPercent(): ?string
    {
        return $this->deposit_percent;
    }

    public function setDepositPercent(?string $deposit_percent): static
    {
        $this->deposit_percent = $deposit_percent;

        return $this;
    }

    public function getTransportMode(): ?int
    {
        return $this->transport_mode;
    }

    public function setTransportMode(?int $transport_mode): static
    {
        $this->transport_mode = $transport_mode;

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

    public function getTransportModeSupplier(): ?int
    {
        return $this->transport_mode_supplier;
    }

    public function setTransportModeSupplier(?int $transport_mode_supplier): static
    {
        $this->transport_mode_supplier = $transport_mode_supplier;

        return $this;
    }

    public function getFkShippingMethod(): ?int
    {
        return $this->fk_shipping_method;
    }

    public function setFkShippingMethod(?int $fk_shipping_method): static
    {
        $this->fk_shipping_method = $fk_shipping_method;

        return $this;
    }

    public function getTvaAssuj(): ?int
    {
        return $this->tva_assuj;
    }

    public function setTvaAssuj(?int $tva_assuj): static
    {
        $this->tva_assuj = $tva_assuj;

        return $this;
    }

    public function getVatexemptcode(): ?string
    {
        return $this->vatexemptcode;
    }

    public function setVatexemptcode(?string $vatexemptcode): static
    {
        $this->vatexemptcode = $vatexemptcode;

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

    public function getLocaltax1Assuj(): ?int
    {
        return $this->localtax1_assuj;
    }

    public function setLocaltax1Assuj(?int $localtax1_assuj): static
    {
        $this->localtax1_assuj = $localtax1_assuj;

        return $this;
    }

    public function getLocaltax1Value(): ?float
    {
        return $this->localtax1_value;
    }

    public function setLocaltax1Value(?float $localtax1_value): static
    {
        $this->localtax1_value = $localtax1_value;

        return $this;
    }

    public function getLocaltax2Assuj(): ?int
    {
        return $this->localtax2_assuj;
    }

    public function setLocaltax2Assuj(?int $localtax2_assuj): static
    {
        $this->localtax2_assuj = $localtax2_assuj;

        return $this;
    }

    public function getLocaltax2Value(): ?float
    {
        return $this->localtax2_value;
    }

    public function setLocaltax2Value(?float $localtax2_value): static
    {
        $this->localtax2_value = $localtax2_value;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getFkBarcodeType(): ?int
    {
        return $this->fk_barcode_type;
    }

    public function setFkBarcodeType(?int $fk_barcode_type): static
    {
        $this->fk_barcode_type = $fk_barcode_type;

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

    public function getOutstandingLimit(): ?float
    {
        return $this->outstanding_limit;
    }

    public function setOutstandingLimit(?float $outstanding_limit): static
    {
        $this->outstanding_limit = $outstanding_limit;

        return $this;
    }

    public function getOrderMinAmount(): ?float
    {
        return $this->order_min_amount;
    }

    public function setOrderMinAmount(?float $order_min_amount): static
    {
        $this->order_min_amount = $order_min_amount;

        return $this;
    }

    public function getSupplierOrderMinAmount(): ?float
    {
        return $this->supplier_order_min_amount;
    }

    public function setSupplierOrderMinAmount(?float $supplier_order_min_amount): static
    {
        $this->supplier_order_min_amount = $supplier_order_min_amount;

        return $this;
    }

    public function getDefaultLang(): ?string
    {
        return $this->default_lang;
    }

    public function setDefaultLang(?string $default_lang): static
    {
        $this->default_lang = $default_lang;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getLogoSquarred(): ?string
    {
        return $this->logo_squarred;
    }

    public function setLogoSquarred(?string $logo_squarred): static
    {
        $this->logo_squarred = $logo_squarred;

        return $this;
    }

    public function getCanvas(): ?string
    {
        return $this->canvas;
    }

    public function setCanvas(?string $canvas): static
    {
        $this->canvas = $canvas;

        return $this;
    }

    public function getFkWarehouse(): ?int
    {
        return $this->fk_warehouse;
    }

    public function setFkWarehouse(?int $fk_warehouse): static
    {
        $this->fk_warehouse = $fk_warehouse;

        return $this;
    }

    public function getWebservicesUrl(): ?string
    {
        return $this->webservices_url;
    }

    public function setWebservicesUrl(?string $webservices_url): static
    {
        $this->webservices_url = $webservices_url;

        return $this;
    }

    public function getWebservicesKey(): ?string
    {
        return $this->webservices_key;
    }

    public function setWebservicesKey(?string $webservices_key): static
    {
        $this->webservices_key = $webservices_key;

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

    public function getFkMulticurrency(): ?int
    {
        return $this->fk_multicurrency;
    }

    public function setFkMulticurrency(?int $fk_multicurrency): static
    {
        $this->fk_multicurrency = $fk_multicurrency;

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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

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
