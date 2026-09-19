<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of CompanyBankAccount (htdocs/societe/class/companybankaccount.class.php)
 * and BonPrelevement::buildRumNumber().
 */
final class BankAccountService
{
    public ?string $error = null;
    /** @var string[] */
    public array $errors = [];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
    ) {
    }

    /**
     * Port of CompanyBankAccount::fetch().
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $id, int $socid = 0, int $ref = 0, ?string $refstr = null, bool $fetchDefaultIfNotFound = false): ?array
    {
        $sql = 'SELECT sr.*, c.code as country_code'
            . ' FROM llx_societe_rib as sr'
            . ' LEFT JOIN llx_c_country as c ON c.rowid = sr.fk_country'
            . ' WHERE sr.rowid = ' . (int) $id;
        if ($socid > 0) {
            $sql .= ' AND sr.fk_soc = ' . (int) $socid;
        }
        $sql .= ' AND sr.entity IN (' . $this->config->getEntity('company_bank_account') . ')';

        $row = $this->db->fetchAssociative($sql);
        if ($row === false) {
            return null;
        }
        $row['iban'] = $this->utils->dolDecrypt((string) ($row['iban_prefix'] ?? ''));

        return $row;
    }

    /**
     * Port of CompanyBankAccount::create(): minimal INSERT
     * (fk_soc, type, datec, model_pdf) then the caller runs update().
     *
     * Returns >0 rowid, <0 KO.
     *
     * @return int
     */
    public function create(int $socid): int
    {
        try {
            $this->db->insert('llx_societe_rib', [
                'fk_soc' => $socid,
                'type' => 'ban',
                'datec' => date('Y-m-d H:i:s'),
                'model_pdf' => 'BANKADDON_PDF',
                'entity' => $this->config->entity(),
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Port of CompanyBankAccount::update() — writes the full column set.
     * The `rum`/`date_rum`/`frstrecur` writes are gated on the prelevement
     * module like upstream.
     *
     * @param array<string, mixed> $account current values of the account
     * @param array<string, mixed> $fields  request fields (already sanitized)
     */
    public function update(int $id, array $fields): int
    {
        $existing = $this->fetch($id);
        if ($existing === null) {
            return -1;
        }

        $sets = [];
        $map = [
            'bank' => 'bank',
            'code_banque' => 'code_banque',
            'code_guichet' => 'code_guichet',
            'number' => 'number',
            'cle_rib' => 'cle_rib',
            'bic' => 'bic',
            'currency_code' => 'currency_code',
            'fk_country' => 'fk_country',
            'state_id' => 'state_id',
            'status' => 'status',
            'domiciliation' => 'address',
            'proprio' => 'owner_address',
            'default_rib' => 'default_rib',
            'frstrecur' => 'frstrecur',
            'rum' => 'rum',
            'label' => 'label',
            'stripe_card_ref' => 'stripe_card_ref',
            'stripe_account' => 'stripe_account',
            'model_pdf' => 'model_pdf',
        ];

        $merged = $existing;
        foreach ($fields as $k => $v) {
            $merged[$k] = $v;
        }

        // default_rib rule: an existing default for the company blocks a
        // second one; if the account is the only one, it becomes default.
        if (isset($fields['default_rib'])) {
            $countDefault = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM llx_societe_rib WHERE fk_soc = ' . (int) $merged['fk_soc']
                . ' AND default_rib = 1 AND rowid <> ' . (int) $id,
            );
            $merged['default_rib'] = $countDefault > 0 ? 0 : (int) $fields['default_rib'];
        }

        $sets[] = 'bank = ' . $this->db->quote((string) ($merged['bank'] ?? ''));
        $sets[] = 'code_banque = ' . $this->db->quote((string) ($merged['code_banque'] ?? ''));
        $sets[] = 'code_guichet = ' . $this->db->quote((string) ($merged['code_guichet'] ?? ''));
        $sets[] = 'number = ' . $this->db->quote((string) ($merged['number'] ?? ''));
        $sets[] = 'cle_rib = ' . $this->db->quote((string) ($merged['cle_rib'] ?? ''));
        $sets[] = 'bic = ' . $this->db->quote((string) ($merged['bic'] ?? ''));
        $sets[] = 'iban_prefix = ' . $this->db->quote($this->utils->dolEncrypt((string) ($merged['iban'] ?? '')));
        $sets[] = 'currency_code = ' . (!empty($merged['currency_code']) ? $this->db->quote((string) $merged['currency_code']) : 'null');
        $sets[] = 'fk_country = ' . (!empty($merged['fk_country']) ? (int) $merged['fk_country'] : 'null');
        $sets[] = 'state_id = ' . (!empty($merged['state_id']) ? (int) $merged['state_id'] : 'null');
        $sets[] = 'status = ' . ((int) ($merged['status'] ?? 1));
        $sets[] = 'domiciliation = ' . $this->db->quote((string) ($merged['address'] ?? $merged['domiciliation'] ?? ''));
        $sets[] = 'proprio = ' . $this->db->quote((string) ($merged['owner_address'] ?? $merged['proprio'] ?? ''));
        $sets[] = 'default_rib = ' . ((int) ($merged['default_rib'] ?? 0));
        $sets[] = 'label = ' . (($merged['label'] ?? '') === '' ? 'null' : $this->db->quote((string) $merged['label']));
        $sets[] = 'stripe_card_ref = ' . $this->db->quote((string) ($merged['stripe_card_ref'] ?? ''));
        $sets[] = 'stripe_account = ' . $this->db->quote((string) ($merged['stripe_account'] ?? ''));

        $sets[] = 'frstrecur = ' . $this->db->quote((string) ($merged['frstrecur'] ?? ''));
        $sets[] = 'rum = ' . $this->db->quote((string) ($merged['rum'] ?? ''));
        $sets[] = 'date_rum = ' . (!empty($merged['date_rum']) ? $this->db->quote((string) $merged['date_rum']) : 'null');
        $sets[] = 'model_pdf = ' . $this->db->quote((string) ($merged['model_pdf'] ?? 'BANKADDON_PDF'));

        try {
            $this->db->executeStatement('UPDATE llx_societe_rib SET ' . implode(',', $sets) . ' WHERE rowid = ' . (int) $id);

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /**
     * Port of CompanyBankAccount::delete().
     */
    public function delete(int $id): int
    {
        $this->db->executeStatement('DELETE FROM llx_societe_rib WHERE rowid = ' . (int) $id);

        return 1;
    }

    /**
     * Port of BonPrelevement::buildRumNumber().
     */
    public function buildRumNumber(string $codeClient, int|string $datec, int $id): string
    {
        $ts = is_int($datec) ? $datec : strtotime((string) $datec);
        $drum = 'RUM';
        $drum = mb_substr($this->utils->stringNospecial($this->utils->stringUnaccent($drum)), 0, 3);

        return $drum
            . '-' . $this->utils->printDate($ts ?: time(), 'dayhourlogsmall')
            . '-' . $this->utils->dolTrunc($drum . '-' . $codeClient, 17, 'right', 'UTF-8', 1)
            . '-' . $id;
    }

    /**
     * Rowid list for getCompanyBankAccount.
     *
     * @return int[]
     */
    public function listForCompany(int $socid): array
    {
        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT rowid FROM llx_societe_rib WHERE fk_soc = ' . (int) $socid,
        ));
    }
}
