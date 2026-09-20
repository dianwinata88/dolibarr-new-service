<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'llx_societe_account', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uk_societe_account_login_website', columns: ['entity', 'login', 'site', 'fk_website'])]
#[ORM\UniqueConstraint(
    name: 'uk_societe_account_key_account_soc',
    columns: ['entity', 'fk_soc', 'key_account', 'site', 'fk_website'],
)]
#[ORM\Index(name: 'idx_societe_account_rowid', columns: ['rowid'])]
#[ORM\Index(name: 'idx_societe_account_login', columns: ['login'])]
#[ORM\Index(name: 'idx_societe_account_status', columns: ['status'])]
#[ORM\Index(name: 'idx_societe_account_fk_website', columns: ['fk_website'])]
#[ORM\Index(name: 'idx_societe_account_fk_soc', columns: ['fk_soc'])]
class SocieteAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, name: 'rowid')]
    private ?int $rowid = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entity', nullable: true, options: ['default' => 1])]
    private ?int $entity = 1;

    #[ORM\Column(type: Types::STRING, name: 'login', length: 128, nullable: false)]
    private string $login;

    #[ORM\Column(type: Types::STRING, name: 'pass_encoding', length: 24, nullable: true)]
    private ?string $pass_encoding = null;

    #[ORM\Column(type: Types::STRING, name: 'pass_crypted', length: 128, nullable: true)]
    private ?string $pass_crypted = null;

    #[ORM\Column(type: Types::STRING, name: 'pass_temp', length: 128, nullable: true)]
    private ?string $pass_temp = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_soc', nullable: true)]
    private ?int $fk_soc = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_website', nullable: true)]
    private ?int $fk_website = null;

    #[ORM\Column(type: Types::STRING, name: 'site', length: 128, nullable: false)]
    private string $site;

    #[ORM\Column(type: Types::STRING, name: 'site_account', length: 128, nullable: true)]
    private ?string $site_account = null;

    #[ORM\Column(type: Types::STRING, name: 'key_account', length: 128, nullable: true)]
    private ?string $key_account = null;

    #[ORM\Column(type: Types::TEXT, name: 'note_private', length: 65535, nullable: true)]
    private ?string $note_private = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_last_login', nullable: true)]
    private ?\DateTimeInterface $date_last_login = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_previous_login', nullable: true)]
    private ?\DateTimeInterface $date_previous_login = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_last_reset_password', nullable: true)]
    private ?\DateTimeInterface $date_last_reset_password = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'date_creation', nullable: false)]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(
        type: Types::DATETIME_MUTABLE,
        name: 'tms',
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    private ?\DateTimeInterface $tms = null;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_creat', nullable: false)]
    private int $fk_user_creat;

    #[ORM\Column(type: Types::INTEGER, name: 'fk_user_modif', nullable: true)]
    private ?int $fk_user_modif = null;

    #[ORM\Column(type: Types::STRING, name: 'ip', length: 250, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::STRING, name: 'import_key', length: 14, nullable: true)]
    private ?string $import_key = null;

    #[ORM\Column(type: Types::INTEGER, name: 'status', nullable: true)]
    private ?int $status = null;

    public function getRowid(): ?int
    {
        return $this->rowid;
    }

    public function setRowid(?int $rowid): static
    {
        $this->rowid = $rowid;

        return $this;
    }

    public function getEntity(): ?int
    {
        return $this->entity;
    }

    public function setEntity(?int $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getPassEncoding(): ?string
    {
        return $this->pass_encoding;
    }

    public function setPassEncoding(?string $pass_encoding): static
    {
        $this->pass_encoding = $pass_encoding;

        return $this;
    }

    public function getPassCrypted(): ?string
    {
        return $this->pass_crypted;
    }

    public function setPassCrypted(?string $pass_crypted): static
    {
        $this->pass_crypted = $pass_crypted;

        return $this;
    }

    public function getPassTemp(): ?string
    {
        return $this->pass_temp;
    }

    public function setPassTemp(?string $pass_temp): static
    {
        $this->pass_temp = $pass_temp;

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

    public function getFkWebsite(): ?int
    {
        return $this->fk_website;
    }

    public function setFkWebsite(?int $fk_website): static
    {
        $this->fk_website = $fk_website;

        return $this;
    }

    public function getSite(): string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getSiteAccount(): ?string
    {
        return $this->site_account;
    }

    public function setSiteAccount(?string $site_account): static
    {
        $this->site_account = $site_account;

        return $this;
    }

    public function getKeyAccount(): ?string
    {
        return $this->key_account;
    }

    public function setKeyAccount(?string $key_account): static
    {
        $this->key_account = $key_account;

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

    public function getDateLastLogin(): ?\DateTimeInterface
    {
        return $this->date_last_login;
    }

    public function setDateLastLogin(?\DateTimeInterface $date_last_login): static
    {
        $this->date_last_login = $date_last_login;

        return $this;
    }

    public function getDatePreviousLogin(): ?\DateTimeInterface
    {
        return $this->date_previous_login;
    }

    public function setDatePreviousLogin(?\DateTimeInterface $date_previous_login): static
    {
        $this->date_previous_login = $date_previous_login;

        return $this;
    }

    public function getDateLastResetPassword(): ?\DateTimeInterface
    {
        return $this->date_last_reset_password;
    }

    public function setDateLastResetPassword(?\DateTimeInterface $date_last_reset_password): static
    {
        $this->date_last_reset_password = $date_last_reset_password;

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;

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

    public function getFkUserCreat(): int
    {
        return $this->fk_user_creat;
    }

    public function setFkUserCreat(int $fk_user_creat): static
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

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): static
    {
        $this->status = $status;

        return $this;
    }
}
