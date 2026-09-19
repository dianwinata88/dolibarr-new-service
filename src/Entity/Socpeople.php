<?php

declare(strict_types=1);

namespace App\Entity;

use App\Extrafields\Extrafields;
use App\Extrafields\ExtrafieldsAwareInterface;
use App\Extrafields\ExtrafieldsAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_socpeople', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\Index(name: 'idx_socpeople_fk_soc', columns: ['fk_soc'])]
#[ORM\Index(name: 'idx_socpeople_fk_user_creat', columns: ['fk_user_creat'])]
#[ORM\Index(name: 'idx_socpeople_lastname', columns: ['lastname'])]
#[Extrafields(table: 'llx_socpeople_extrafields')]
class Socpeople implements ExtrafieldsAwareInterface
{
    use ExtrafieldsAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'datec', nullable: true)]
    private ?\DateTimeInterface $datec = null;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'use_thirdparty_address', nullable: true)]
    private ?int $use_thirdparty_address = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: false, options: ['default' => 1])]
    private int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'ref_ext', length: 255, nullable: true)]
    private ?string $ref_ext = null;

    #[ORM\Column(type: Types::STRING, name: 'name_alias', length: 255, nullable: true)]
    private ?string $name_alias = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_parent', nullable: true)]
    private ?int $fk_parent = null;

    #[ORM\Column(type: Types::STRING, name: 'civility', length: 6, nullable: true)]
    private ?string $civility = null;

    #[ORM\Column(type: Types::STRING, name: 'lastname', length: 50, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::STRING, name: 'firstname', length: 50, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(type: Types::STRING, name: 'address', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, name: 'zip', length: 25, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(type: Types::STRING, name: 'town', length: 255, nullable: true)]
    private ?string $town = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_departement', nullable: true)]
    private ?int $fk_departement = null;

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

    #[ORM\Column(type: Types::DATE_MUTABLE, name: 'birthday', nullable: true)]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\Column(type: Types::STRING, name: 'poste', length: 255, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(type: Types::STRING, name: 'phone', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, name: 'phone_perso', length: 30, nullable: true)]
    private ?string $phone_perso = null;

    #[ORM\Column(type: Types::STRING, name: 'phone_mobile', length: 30, nullable: true)]
    private ?string $phone_mobile = null;

    #[ORM\Column(type: Types::STRING, name: 'fax', length: 30, nullable: true)]
    private ?string $fax = null;

    #[ORM\Column(type: Types::STRING, name: 'url', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::STRING, name: 'email', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, name: 'socialnetworks', length: 65535, nullable: true)]
    private ?string $socialnetworks = null;

    #[ORM\Column(type: Types::STRING, name: 'photo', length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::SMALLINT, name: 'no_email', nullable: false, options: ['default' => 0])]
    private int $no_email = 0;

    #[ORM\Column(type: Types::SMALLINT, name: 'priv', nullable: false, options: ['default' => 0])]
    private int $priv = 0;

    #[ORM\Column(type: Types::STRING, name: 'fk_prospectlevel', length: 12, nullable: true)]
    private ?string $fk_prospectlevel = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_stcommcontact', nullable: false, options: ['default' => 0])]
    private int $fk_stcommcontact = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_creat', nullable: true, options: ['default' => 0])]
    private ?int $fk_user_creat = 0;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_modif', nullable: true)]
    private ?int $fk_user_modif = null;

    #[ORM\Column(type: Types::TEXT, name: 'note_private', length: 65535, nullable: true)]
    private ?string $note_private = null;

    #[ORM\Column(type: Types::TEXT, name: 'note_public', length: 65535, nullable: true)]
    private ?string $note_public = null;

    #[ORM\Column(type: Types::STRING, name: 'default_lang', length: 6, nullable: true)]
    private ?string $default_lang = null;

    #[ORM\Column(type: Types::STRING, name: 'canvas', length: 32, nullable: true)]
    private ?string $canvas = null;

    #[ORM\Column(type: Types::STRING, name: 'import_key', length: 14, nullable: true)]
    private ?string $import_key = null;

    #[ORM\Column(type: 'tinyint', name: 'statut', nullable: false, options: ['default' => 1])]
    private int $statut = 1;

    #[ORM\Column(type: Types::STRING, name: 'ip', length: 250, nullable: true)]
    private ?string $ip = null;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

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

    public function getFkSoc(): ?int
    {
        return $this->fk_soc;
    }

    public function setFkSoc(?int $fk_soc): static
    {
        $this->fk_soc = $fk_soc;

        return $this;
    }

    public function getUseThirdpartyAddress(): ?int
    {
        return $this->use_thirdparty_address;
    }

    public function setUseThirdpartyAddress(?int $use_thirdparty_address): static
    {
        $this->use_thirdparty_address = $use_thirdparty_address;

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

    public function getNameAlias(): ?string
    {
        return $this->name_alias;
    }

    public function setNameAlias(?string $name_alias): static
    {
        $this->name_alias = $name_alias;

        return $this;
    }

    public function getFkParent(): ?int
    {
        return $this->fk_parent;
    }

    public function setFkParent(?int $fk_parent): static
    {
        $this->fk_parent = $fk_parent;

        return $this;
    }

    public function getCivility(): ?string
    {
        return $this->civility;
    }

    public function setCivility(?string $civility): static
    {
        $this->civility = $civility;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

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

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): static
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(?string $poste): static
    {
        $this->poste = $poste;

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

    public function getPhonePerso(): ?string
    {
        return $this->phone_perso;
    }

    public function setPhonePerso(?string $phone_perso): static
    {
        $this->phone_perso = $phone_perso;

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

    public function getSocialnetworks(): ?string
    {
        return $this->socialnetworks;
    }

    public function setSocialnetworks(?string $socialnetworks): static
    {
        $this->socialnetworks = $socialnetworks;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getNoEmail(): int
    {
        return $this->no_email;
    }

    public function setNoEmail(int $no_email): static
    {
        $this->no_email = $no_email;

        return $this;
    }

    public function getPriv(): int
    {
        return $this->priv;
    }

    public function setPriv(int $priv): static
    {
        $this->priv = $priv;

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

    public function getFkStcommcontact(): int
    {
        return $this->fk_stcommcontact;
    }

    public function setFkStcommcontact(int $fk_stcommcontact): static
    {
        $this->fk_stcommcontact = $fk_stcommcontact;

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

    public function getDefaultLang(): ?string
    {
        return $this->default_lang;
    }

    public function setDefaultLang(?string $default_lang): static
    {
        $this->default_lang = $default_lang;

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

    public function getImportKey(): ?string
    {
        return $this->import_key;
    }

    public function setImportKey(?string $import_key): static
    {
        $this->import_key = $import_key;

        return $this;
    }

    public function getStatut(): int
    {
        return $this->statut;
    }

    public function setStatut(int $statut): static
    {
        $this->statut = $statut;

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
}
