<?php

declare(strict_types=1);

namespace App\BankAccount;

use App\Entity\SocieteRib;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Port of the persistence behaviour of upstream CompanyBankAccount::create(),
 * ::update() and ::delete() (htdocs/societe/class/companybankaccount.class.php).
 *
 * Upstream keeps request values on object properties and update() persists a
 * fixed projection, so only the properties named in WRITABLE_PROPS ever make
 * it into the table; every other request field is accepted then silently
 * dropped. String columns get '' for unset properties (the upstream
 * $this->db->escape(null) produces an empty literal), int columns get 0, and
 * label is written NULL when the trimmed value is empty.
 */
final class CompanyBankAccountManager
{
    private const DEFAULT_ENTITY = 1;

    /**
     * Object properties upstream update() writes to columns, in SQL order.
     * 'iban' maps to column iban_prefix (encrypted), 'address' to
     * domiciliation, 'owner_name' to proprio.
     */
    public const WRITABLE_PROPS = [
        'bank', 'code_banque', 'code_guichet', 'number', 'cle_rib', 'bic', 'iban',
        'currency_code', 'fk_country', 'state_id', 'status', 'address', 'owner_name',
        'owner_address', 'default_rib', 'frstrecur', 'rum', 'date_rum', 'label',
        'stripe_card_ref', 'stripe_account', 'model_pdf',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DolCrypt $dolCrypt,
        private readonly RumGenerator $rumGenerator,
        // port of getDolGlobalString('BANKADDON_PDF')
        #[Autowire('%env(default::BANKADDON_PDF)%')]
        private readonly ?string $bankAddonPdf = null,
    ) {
    }

    /**
     * @return SocieteRib[]
     */
    public function listForSociete(int $socId): array
    {
        return $this->em->getRepository(SocieteRib::class)->findBy(
            ['fk_soc' => $socId, 'entity' => self::DEFAULT_ENTITY],
            ['rowid' => 'ASC'],
        );
    }

    public function find(int $ribId): ?SocieteRib
    {
        return $this->em->find(SocieteRib::class, $ribId);
    }

    /**
     * The writable props of a freshly constructed CompanyBankAccount: all
     * null (upstream defaults), so an absent request value persists as ''/0.
     *
     * @return array<string, mixed>
     */
    public function blankProps(): array
    {
        return array_fill_keys(self::WRITABLE_PROPS, null);
    }

    /**
     * The writable props of a fetched entity, under their upstream property
     * names (iban = decrypted iban_prefix, address = domiciliation,
     * owner_name = proprio).
     *
     * @return array<string, mixed>
     */
    public function readProps(SocieteRib $rib): array
    {
        return [
            'bank' => $rib->getBank(),
            'code_banque' => $rib->getCodeBanque(),
            'code_guichet' => $rib->getCodeGuichet(),
            'number' => $rib->getNumber(),
            'cle_rib' => $rib->getCleRib(),
            'bic' => $rib->getBic(),
            'iban' => $this->dolCrypt->decrypt($rib->getIbanPrefix() ?? ''),
            'currency_code' => $rib->getCurrencyCode(),
            'fk_country' => $rib->getFkCountry(),
            'state_id' => $rib->getStateId(),
            'status' => $rib->getStatus(),
            'address' => $rib->getDomiciliation(),
            'owner_name' => $rib->getProprio(),
            'owner_address' => $rib->getOwnerAddress(),
            'default_rib' => $rib->getDefaultRib(),
            'frstrecur' => $rib->getFrstrecur(),
            'rum' => $rib->getRum(),
            'date_rum' => $rib->getDateRum(),
            'label' => $rib->getLabel(),
            'stripe_card_ref' => $rib->getStripeCardRef(),
            'stripe_account' => $rib->getStripeAccount(),
            'model_pdf' => $rib->getModelPdf(),
        ];
    }

    /**
     * Port of CompanyBankAccount::create(): fixes default_rib so that there is
     * never more than one default 'ban' account per third party (and the very
     * first account always becomes the default), then inserts.
     */
    public function insertNew(SocieteRib $rib): void
    {
        $defaultRib = $rib->getDefaultRib();

        $existing = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.rowid)')
            ->from(SocieteRib::class, 'r')
            ->where('r.fk_soc = :soc')
            ->andWhere('r.default_rib = 1')
            ->andWhere("r.type = 'ban'")
            ->andWhere('r.entity = :entity')
            ->setParameter('soc', $rib->getFkSoc())
            ->setParameter('entity', self::DEFAULT_ENTITY)
            ->getQuery()
            ->getSingleScalarResult();

        // We want to be sure to have always 1 default for type = 'ban'
        if ($defaultRib !== 0 && $existing > 0) {
            $rib->setDefaultRib(0);
        }
        if ($defaultRib === 0 && $existing === 0) {
            $rib->setDefaultRib(1);
        }

        $rib->setEntity(self::DEFAULT_ENTITY);
        if ($rib->getDatec() === null) {
            $rib->setDatec(new \DateTime());
        }
        $rib->setTms(new \DateTime());
        // upstream insert uses $this->model_pdf = getDolGlobalString('BANKADDON_PDF')
        $rib->setModelPdf($this->bankAddonPdf ?? '');

        $this->em->persist($rib);
        $this->em->flush();
    }

    /**
     * Port of the rum auto-generation the endpoints run when ->rum is empty:
     * rum = buildRumNumber(code_client, datec, rowid), date_rum = dol_now().
     *
     * @param mixed $rumProp the effective 'rum' property value (request
     *                       overlay for create, fetched+overlay for update)
     */
    public function fillRumIfEmpty(SocieteRib $rib, ?string $codeClient, mixed $rumProp): void
    {
        if (empty($rumProp)) {
            $rib->setRum($this->rumGenerator->buildRumNumber(
                $codeClient,
                $rib->getDatec() ?? time(),
                (string) $rib->getRowid(),
            ));
            $rib->setDateRum(new \DateTime());
        }
    }

    /**
     * Port of CompanyBankAccount::update(): writes the fixed writable
     * projection from a full property bag (baseline merged with the sanitized
     * request overlay).
     *
     * @param array<string, mixed> $props complete property-name => value map
     */
    public function applyUpdateProjection(SocieteRib $rib, array $props): void
    {
        $str = static fn (mixed $v): string => $v === null ? '' : (string) $v;

        $rib->setBank($str($props['bank'] ?? null));
        $rib->setCodeBanque($str($props['code_banque'] ?? null));
        $rib->setCodeGuichet($str($props['code_guichet'] ?? null));
        $rib->setNumber($str($props['number'] ?? null));
        $rib->setCleRib($str($props['cle_rib'] ?? null));
        $rib->setBic($str($props['bic'] ?? null));

        // iban prop -> encrypted iban_prefix column
        $rib->setIbanPrefix($this->dolCrypt->encrypt((string) ($props['iban'] ?? '')));

        $rib->setCurrencyCode($str($props['currency_code'] ?? null));
        $rib->setFkCountry((int) ($props['fk_country'] ?? 0));
        $rib->setStateId((int) ($props['state_id'] ?? 0));
        $rib->setStatus((int) ($props['status'] ?? 0));

        // upstream truncates domiciliation/owner_address to 254 chars
        $address = $props['address'] ?? null;
        $rib->setDomiciliation($address !== null ? mb_substr((string) $address, 0, 254, 'UTF-8') : null);
        $ownerAddress = $props['owner_address'] ?? null;
        $rib->setOwnerAddress($ownerAddress !== null ? mb_substr((string) $ownerAddress, 0, 254, 'UTF-8') : null);

        $rib->setProprio($str($props['owner_name'] ?? null));
        $rib->setDefaultRib((int) ($props['default_rib'] ?? 0));

        $rib->setFrstrecur($str($props['frstrecur'] ?? null));
        $rib->setRum($str($props['rum'] ?? null));
        $dateRum = $props['date_rum'] ?? null;
        $rib->setDateRum($this->toDateTime($dateRum));

        // upstream: label = NULL when the trimmed label is empty
        $label = $props['label'] ?? null;
        $rib->setLabel($label !== null && trim((string) $label) !== '' ? (string) $label : null);

        $rib->setStripeCardRef($str($props['stripe_card_ref'] ?? null));
        $rib->setStripeAccount($str($props['stripe_account'] ?? null));
        $modelPdf = $props['model_pdf'] ?? null;
        $rib->setModelPdf($modelPdf !== null ? trim((string) $modelPdf) : null);

        // upstream tms auto-updates via ON UPDATE CURRENT_TIMESTAMP
        $rib->setTms(new \DateTime());

        $this->em->flush();
    }

    /**
     * Port of CompanyBankAccount::delete(): returns 1 on success.
     */
    public function delete(SocieteRib $rib): int
    {
        $this->em->remove($rib);
        $this->em->flush();

        return 1;
    }

    private function toDateTime(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (new \DateTime())->setTimestamp((int) $value);
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);

            return $ts === false ? null : (new \DateTime())->setTimestamp($ts);
        }

        return null;
    }
}
