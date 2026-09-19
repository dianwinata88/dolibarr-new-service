<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of Dolibarr third-party code numbering:
 *  - the mask engine get_next_value()/check_value() (functions2.lib.php)
 *  - the societe code modules mod_codeclient_{leopard,monkey,elephant}
 *    and mod_codecompta_{aquarium,digitaria,panicum}
 *
 * The active module is chosen by the SOCIETE_CODECLIENT_ADDON and
 * SOCIETE_CODECOMPTA_ADDON constants (env vars), exactly like upstream.
 */
final class NumberingService
{
    /** @var string[] */
    public array $errors = [];
    public ?string $error = null;

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
    ) {
    }

    // ------------------------------------------------------------------
    //  Module dispatch (get_codeclient / get_codefournisseur / verif)
    // ------------------------------------------------------------------

    /** Selected code module name or '' when none configured. */
    public function codeModule(): string
    {
        return $this->config->getString('SOCIETE_CODECLIENT_ADDON');
    }

    /**
     * get_codeclient($type=0) / get_codefournisseur($type=1).
     * Returns the next code (or '' when module returns nothing) — null on
     * hard error (-1).
     */
    public function getNextCode(Company $company, int $type): ?string
    {
        $module = $this->codeModule();
        if ($module === '') {
            return null;
        }

        return match ($module) {
            'mod_codeclient_leopard' => '',
            'mod_codeclient_monkey' => $this->monkeyNextValue($type),
            'mod_codeclient_elephant' => $this->elephantNextValue($company, $type),
            default => null, // unknown module: behaves like module not found (no code generated)
        };
    }

    /**
     * verif() of the active code module: 0 ok, -1 syntax, -2 required,
     * -3 already used, -4 prefix required, -5 not configured, -6 other.
     */
    public function verifCode(string &$code, Company $company, int $type): int
    {
        $module = $this->codeModule();
        if ($module === '') {
            return 0;
        }
        $this->error = null;
        $this->errors = [];

        return match ($module) {
            'mod_codeclient_leopard' => $this->leopardVerif($code),
            'mod_codeclient_monkey' => $this->monkeyVerif($code, $company, $type),
            'mod_codeclient_elephant' => $this->elephantVerif($code, $company, $type),
            default => 0,
        };
    }

    /**
     * codeclient_modifiable() — whether the code can be modified under the
     * active module rules.
     */
    public function codeModifiable(Company $company, string $code, int $type): int
    {
        if ($this->codeModule() === '') {
            return 0;
        }
        $flags = $this->moduleFlags();
        if ($flags['code_modifiable_null'] && !$code) {
            return 1;
        }
        if ($flags['code_modifiable_invalide'] && $this->verifCode($code, $company, $type) < 0) {
            return 1;
        }
        if ($flags['code_modifiable']) {
            return 1;
        }

        return 0;
    }

    /** @return array{code_null:int,code_modifiable:int,code_modifiable_invalide:int,code_modifiable_null:int,prefixIsRequired:int} */
    public function moduleFlags(): array
    {
        return match ($this->codeModule()) {
            'mod_codeclient_leopard' => ['code_null' => 1, 'code_modifiable' => 1, 'code_modifiable_invalide' => 1, 'code_modifiable_null' => 1, 'prefixIsRequired' => 0],
            'mod_codeclient_monkey' => ['code_null' => 1, 'code_modifiable' => 1, 'code_modifiable_invalide' => 1, 'code_modifiable_null' => 1, 'prefixIsRequired' => 0],
            'mod_codeclient_elephant' => ['code_null' => 0, 'code_modifiable' => 1, 'code_modifiable_invalide' => 1, 'code_modifiable_null' => 1, 'prefixIsRequired' => 0],
            default => ['code_null' => 1, 'code_modifiable' => 1, 'code_modifiable_invalide' => 1, 'code_modifiable_null' => 1, 'prefixIsRequired' => 0],
        };
    }

    // ------------------------------------------------------------------
    //  mod_codeclient_leopard
    // ------------------------------------------------------------------

    private function leopardVerif(string &$code): int
    {
        $code = trim($code);

        if ($code === '' && !$this->config->getString('MAIN_COMPANY_CODE_ALWAYS_REQUIRED')) {
            return 0;
        }
        if ($code === '') {
            return -2;
        }

        return 0;
    }

    // ------------------------------------------------------------------
    //  mod_codeclient_monkey
    // ------------------------------------------------------------------

    private function monkeyNextValue(int $type): ?string
    {
        $prefixcustomer = $this->config->getString('COMPANY_MONKEY_MASK_CUSTOMER', 'CU');
        $prefixsupplier = $this->config->getString('COMPANY_MONKEY_MASK_SUPPLIER', 'SP');

        if ($type === 0) {
            $field = 'code_client';
            $prefix = $prefixcustomer;
        } elseif ($type === 1) {
            $field = 'code_fournisseur';
            $prefix = $prefixsupplier;
        } else {
            return null;
        }

        $posindice = strlen($prefix) + 6;
        $sql = "SELECT MAX(CAST(SUBSTRING($field FROM $posindice) AS SIGNED)) as max"
            ." FROM llx_societe"
            ." WHERE $field LIKE ".$this->db->quote($prefix."____-%")
            ." AND entity IN (".$this->config->getEntity('societe').")";

        $max = (int) ($this->db->fetchOne($sql) ?? 0);

        $yymm = date('ym');

        if ($max >= (10 ** 5 - 1)) {
            $num = (string) ($max + 1);
        } else {
            $num = sprintf('%05d', $max + 1);
        }

        return $prefix.$yymm.'-'.$num;
    }

    private function monkeyVerif(string &$code, Company $company, int $type): int
    {
        $code = strtoupper(trim($code));

        if ($code === '' && !$this->config->getString('MAIN_COMPANY_CODE_ALWAYS_REQUIRED')) {
            return 0;
        }
        if ($code === '') {
            return -2;
        }
        if (strlen($code) < 11) {
            return $code === '' ? -2 : -1;
        }
        if ($this->codeIsAvailable($code, $company, $type) !== 0) {
            return -3;
        }

        return 0;
    }

    // ------------------------------------------------------------------
    //  mod_codeclient_elephant
    // ------------------------------------------------------------------

    private function elephantMask(int $type): string
    {
        return match ($type) {
            0 => $this->config->getString('COMPANY_ELEPHANT_MASK_CUSTOMER'),
            1 => $this->config->getString('COMPANY_ELEPHANT_MASK_SUPPLIER'),
            default => '',
        };
    }

    private function elephantNextValue(Company $company, int $type): ?string
    {
        $mask = $this->elephantMask($type);
        if (!$mask) {
            $this->error = 'NotConfigured';

            return '';
        }
        if ($type !== 0 && $type !== 1) {
            return null;
        }
        $field = $type === 0 ? 'code_client' : 'code_fournisseur';

        return $this->getNextValue($mask, 'societe', $field, '', '', time(), $company);
    }

    private function elephantVerif(string &$code, Company $company, int $type): int
    {
        $code = strtoupper(trim($code));

        if ($this->config->getString('COMPANY_ELEPHANT_DATE_START_ENABLE')
            && ($company->date_creation ?? 0) < (int) $this->config->getString('COMPANY_ELEPHANT_DATE_START')) {
            return -5;
        }
        if ($code === '' && !$this->config->getString('MAIN_COMPANY_CODE_ALWAYS_REQUIRED')) {
            return 0;
        }
        if ($code === '') {
            return -2;
        }

        $mask = $this->elephantMask($type);
        if (!$mask) {
            $this->error = 'NotConfigured';

            return -5;
        }
        $result = $this->checkValue($mask, $code, $company);
        if (is_string($result)) {
            $this->error = $result;
            $this->errors[] = $result;

            return -6;
        }

        return $this->codeIsAvailable($code, $company, $type) !== 0 ? -3 : $result;
    }

    /**
     * Shared availability check (verif_dispo): 0 available, -1 already used,
     * -2 SQL error.
     */
    private function codeIsAvailable(string $code, Company $company, int $type): int
    {
        $field = $type === 1 ? 'code_fournisseur' : 'code_client';
        $sql = "SELECT rowid FROM llx_societe WHERE $field = ".$this->db->quote($code)
            ." AND entity IN (".$this->config->getEntity('societe').")";
        if (($company->id ?? 0) > 0) {
            $sql .= " AND rowid <> ".(int) $company->id;
        }

        try {
            $rowid = $this->db->fetchOne($sql);
        } catch (\Throwable) {
            return -2;
        }

        return $rowid === false ? 0 : -1;
    }

    // ------------------------------------------------------------------
    //  mod_codecompta_* (get_codecompta)
    // ------------------------------------------------------------------

    /**
     * get_codecompta($type) — fills the customer or supplier accountancy
     * code on $company and returns >=0 on success.
     *
     * @param 'customer'|'supplier' $type
     */
    public function getCodeCompta(Company $company, string $type): int
    {
        $module = $this->config->getString('SOCIETE_CODECOMPTA_ADDON');
        if ($module === '') {
            if ($type === 'customer') {
                $company->code_compta_client = '';
            } else {
                $company->code_compta_fournisseur = '';
            }

            return 0;
        }

        return match ($module) {
            'mod_codecompta_aquarium' => $this->aquariumGetCode($company, $type),
            'mod_codecompta_digitaria' => $this->digitariaGetCode($company, $type),
            'mod_codecompta_panicum' => $this->panicumGetCode($company, $type),
            default => $this->codeComptaFallback($company, $type),
        };
    }

    private function codeComptaFallback(Company $company, string $type): int
    {
        $this->error = 'ErrorAccountancyCodeNotDefined';

        return -1;
    }

    /** @param 'customer'|'supplier' $type */
    private function aquariumGetCode(Company $company, string $type): int
    {
        $maskCustomer = $this->config->getString('COMPANY_AQUARIUM_MASK_CUSTOMER');
        $maskSupplier = $this->config->getString('COMPANY_AQUARIUM_MASK_SUPPLIER');
        if (trim($maskCustomer) === '') {
            $maskCustomer = '411';
        }
        if (trim($maskSupplier) === '') {
            $maskSupplier = '401';
        }
        if ($this->config->getString('COMPANY_AQUARIUM_NO_PREFIX')) {
            $prefixcustomer = '';
            $prefixsupplier = '';
        } else {
            $prefixcustomer = $maskCustomer;
            $prefixsupplier = $maskSupplier;
        }

        if ($type === 'customer') {
            $codetouse = !empty($company->code_client) ? (string) $company->code_client : 'CUSTCODE';
            $prefix = $prefixcustomer;
        } elseif ($type === 'supplier') {
            $codetouse = !empty($company->code_fournisseur) ? (string) $company->code_fournisseur : 'SUPPCODE';
            $prefix = $prefixsupplier;
        } else {
            $this->error = 'Bad value for parameter type';

            return -1;
        }

        if (!$this->config->getString('COMPANY_AQUARIUM_REMOVE_SPECIAL') || $this->config->getString('COMPANY_AQUARIUM_REMOVE_SPECIAL')) {
            $codetouse = (string) preg_replace('/([^a-z0-9])/i', '', $codetouse);
        }
        if ($this->config->getString('COMPANY_AQUARIUM_REMOVE_ALPHA')) {
            $codetouse = (string) preg_replace('/([a-z])/i', '', $codetouse);
        }
        if ($this->config->getString('COMPANY_AQUARIUM_CLEAN_REGEX')) {
            $codetouse = (string) preg_replace('/'.$this->config->getString('COMPANY_AQUARIUM_CLEAN_REGEX').'/', '\1\2\3', $codetouse);
        }

        $codetouse = $prefix.strtoupper($codetouse);

        $field = $type === 'customer' ? 'code_compta' : 'code_compta_fournisseur';
        $sql = "SELECT $field FROM llx_societe WHERE $field = ".$this->db->quote($codetouse);
        if (!empty($company->id)) {
            $sql .= " AND rowid <> ".(int) $company->id;
        }

        try {
            $rowid = $this->db->fetchOne($sql);
            $isDispo = $rowid === false ? 1 : 0;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        if ($type === 'customer') {
            $company->code_compta_client = $codetouse;
        } else {
            $company->code_compta_fournisseur = $codetouse;
        }

        return $isDispo;
    }

    /** @param 'customer'|'supplier' $type */
    private function digitariaGetCode(Company $company, string $type): int
    {
        $maskCustomer = trim($this->config->getString('COMPANY_DIGITARIA_MASK_CUSTOMER')) ?: '411';
        $maskSupplier = trim($this->config->getString('COMPANY_DIGITARIA_MASK_SUPPLIER')) ?: '401';
        $widthCustomer = (int) (trim($this->config->getString('COMPANY_DIGITARIA_MASK_NBCHARACTER_CUSTOMER')) ?: '5');
        $widthSupplier = (int) (trim($this->config->getString('COMPANY_DIGITARIA_MASK_NBCHARACTER_SUPPLIER')) ?: '5');

        $disponibility = 0;
        $code = '';

        if ($type === 'supplier') {
            $codetouse = (string) $company->name;
            $prefix = $maskSupplier;
            $width = $widthSupplier;
        } elseif ($type === 'customer') {
            $codetouse = (string) $company->name;
            $prefix = $maskCustomer;
            $width = $widthCustomer;
        } else {
            $this->error = 'Bad value for parameter type';

            return -1;
        }

        if ($this->config->getString('COMPANY_DIGITARIA_CLEAN_WORDS')) {
            $codetouse = str_replace(explode(';', $this->config->getString('COMPANY_DIGITARIA_CLEAN_WORDS')), '', $codetouse);
        }
        $codetouse = (string) preg_replace('/([^a-z0-9])/i', '', $codetouse);
        if ($this->config->getString('COMPANY_DIGITARIA_CLEAN_REGEX')) {
            $codetouse = (string) preg_replace('/'.$this->config->getString('COMPANY_DIGITARIA_CLEAN_REGEX').'/', '\1\2\3', $codetouse);
        }

        $code = $prefix.strtoupper(substr($codetouse, 0, $width));

        if ($this->config->getString('COMPANY_DIGITARIA_UNIQUE_CODE', '1')) {
            $disponibility = $this->checkIfAccountancyCodeIsAlreadyUsed($code, $type);
            $i = 0;
            while ($disponibility != 0 && $i < 1000) {
                $a = $i <= 9 ? 1 : ($i <= 99 ? 2 : 3);
                $w = $type === 'supplier' ? $widthSupplier : $widthCustomer;
                $code = $prefix.strtoupper(substr($codetouse, 0, $w - $a)).$i;
                $disponibility = $this->checkIfAccountancyCodeIsAlreadyUsed($code, $type);
                $i++;
            }
        }

        if ($type === 'supplier') {
            $company->code_compta_fournisseur = $code;
        } else {
            $company->code_compta_client = $code;
        }

        return $disponibility == 0 ? 0 : -1;
    }

    /** @param 'customer'|'supplier' $type */
    private function panicumGetCode(Company $company, string $type): int
    {
        if ($type === 'supplier') {
            // no auto-generation
        } else {
        }

        return 0;
    }

    private function checkIfAccountancyCodeIsAlreadyUsed(string $code, string $type): int
    {
        $field = $type === 'supplier' ? 'code_compta_fournisseur' : 'code_compta';
        $sql = "SELECT $field FROM llx_societe WHERE $field = ".$this->db->quote($code)
            ." AND entity IN (".$this->config->getEntity('societe').")";

        try {
            $rowid = $this->db->fetchOne($sql);
        } catch (\Throwable) {
            return -1;
        }

        return $rowid === false ? 0 : -1;
    }

    // ------------------------------------------------------------------
    //  get_next_value() / check_value() mask engine (functions2.lib.php)
    // ------------------------------------------------------------------

    /**
     * Full port of get_next_value($db, $mask, $table, $field, $where, $objsoc,
     * $date, 'next', ...).
     *
     * Supported tokens (same as upstream): {yyyy}{yy}{y}{mm}{dd}
     * {t+} {u+} {jj+} {0+[@raz][+offset]} {c+0*} {XXX-n} {user_extra_...}
     * Returns the generated reference or an 'Error...' string.
     *
     * @param string|int $date unix ts
     */
    public function getNextValue(string $mask, string $table, string $field, string $where = '', string $objsoc = '', int|string $date = 0, string $mode = 'next', ?Company $company = null): string
    {
        $this->error = null;

        $this->currentField = $field;
        $bentity = $this->config->getEntity($table);

        // Extract global counter {0+[@raz][+offset]}
        $reg = [];
        if (preg_match('/\{(0+)([@\+][0-9\-\+\=]+)?\}/i', $mask, $reg)) {
            $counter = $reg[1];
            $maskraz = null;
            $maskoffset = 0;
            if (!empty($reg[2])) {
                if (preg_match('/^@/', $reg[2])) {
                    $maskraz = preg_replace('/@/', '', $reg[2]);
                }
                if (preg_match('/\+/', $reg[2])) {
                    $maskoffset = preg_replace('/.*\+/', '', $reg[2]);
                }
            }
            $maskpos = $this->posIndex($mask, $counter); // 1-based position
            $maskwithonlyymcode = (string) preg_replace('/\{(0+)([@\+][0-9\-\+\=]+)?\}/i', $counter, $mask);
            $maskwithnocode = (string) preg_replace('/\{([a-zA-Z]+)\}/', '_', $maskwithonlyymcode);
            $maskwithnocode = (string) preg_replace('/\{([a-zA-Z]+)\+([0-9]+)\}/', '_', $maskwithnocode);
        } else {
            return 'ErrorBadMask';
        }

        $masktri = isset($maskpos) ? 99 * 1000000 + $maskpos : 0;
        if (!isset($maskraz)) {
            $maskraz = -1;
        }
        if ($maskraz >= 0) {
            if ($maskraz > 99) {
                return 'ErrorBadMaskBadRazMonth';
            }

            if ($maskraz == 99 && preg_match('/^.*\{[^mp]*\}.*$/i', $maskwithnocode)) {
                // refuse raz auto when mask has a year but not month
            }
            // Build regexlike extraction of Y/M/D portions
            $yeartoadd = 0;
            $yearoffset = 0;
            $masklike = $maskwithnocode;

            // Analyze mask for {yyyy}/{yy}/{y}/{mm}/{m}/{dd}/{d}
            $hasyear = $hasmonth = $hasday = false;
            $masklike = $this->buildMaskLike($maskwithnocode, (int) $date, $maskraz, $hasyear, $hasmonth, $hasday, $yearoffset, $mask);

            if ($maskraz == 99 && !$hasyear) {
                return 'ErrorCantUseRazIfNoYearInMask';
            }
            if ($maskraz == 99 && !$hasmonth) {
                return 'ErrorCantUseRazInStartedYearIfNoYearMonthInMask';
            }

            // Check that position of counter is after year/month/day
            $posdate = $this->posOfTokens($mask);
            if ($posdate !== false && $maskpos <= $posdate) {
                return 'ErrorCantUseRazInStartedYearIfNoYearMonthInMask';
            }

            if ($maskraz == 99) {
                $yearpos = strpos($maskwithnocode, '_');
                // raz = monthly: reinit counter each month
                $sqlwhere = '';
                $sqlwhereMonth = '';

                if ($hasyear && $hasmonth) {
                    $sqlwhere .= $this->buildSqlWhereForRaz($mask, $maskwithnocode, $maskpos, (int) $date, 'month');
                }
                if ($hasyear && !$hasmonth) {
                    $sqlwhere .= $this->buildSqlWhereForRaz($mask, $maskwithnocode, $maskpos, (int) $date, 'year');
                }
            } elseif ($maskraz >= 1 && $maskraz <= 12) {
                // raz on given month each year
                if ($hasyear) {
                    $fiscalMonth = $maskraz;
                    $yearcur = (int) date('Y', (int) $date);
                    $monthcur = (int) date('m', (int) $date);
                    $yearprev = $monthcur < $fiscalMonth ? $yearcur - 1 : $yearcur;
                    $sqlwhere = $this->buildSqlWhereForRazFiscal($mask, $maskwithnocode, $maskpos, $yearprev, $fiscalMonth);
                } else {
                    return 'ErrorCantUseRazIfNoYearInMask';
                }
            } else {
                $sqlwhere = '';
            }
        } else {
            $sqlwhere = '';
            $masklike = $this->buildMaskLikeNoRaz($maskwithnocode);
        }

        // Forge SQL
        $sql = 'SELECT MAX(SUBSTRING('.$field.', '.$maskpos.', '.strlen($counter).')) as nummax';
        $sql .= ' FROM llx_'.$table;
        $sql .= ' WHERE '.$field." LIKE '".$this->escapeLike($masklike)."'";
        $sql .= " AND ".$field." NOT LIKE '(PROV%'";
        $sql .= ' AND entity IN ('.$bentity.')';
        if ($where) {
            $sql .= $where;
        }
        if (!empty($sqlwhere)) {
            $sql .= ' AND '.$sqlwhere;
        }

        $counterval = 0;
        try {
            $res = $this->db->fetchOne($sql);
            $counterval = $res === false ? 0 : (int) $res;
        } catch (\Throwable) {
            $counterval = 0;
        }

        if ($mode === 'last') {
            return (string) $counterval;
        }

        $counterval++;
        $counterval += (int) ($maskoffset ?? 0);

        if (strlen($counter) < 3 && !$this->config->getString('MAIN_COUNTER_WITH_LESS_3_DIGITS')) {
            return 'ErrorCounterMustHaveMoreThan3Digits';
        }
        if ($counterval >= 10 ** strlen($counter)) {
            return 'ErrorMaxNumberReachForThisMask';
        }

        // Build final value by replacing tokens
        $numFinal = $this->replaceTokens($mask, (int) $date, $counterval, $company);

        return $numFinal;
    }

    /**
     * Port of check_value($mask, $value): verify a code matches the mask.
     * Returns 0 OK, -1 bad length, error string otherwise.
     */
    public function checkValue(string $mask, string $value, ?Company $company = null): int|string
    {
        if (preg_match('/\{(0+)([@\+][0-9\-\+\=]+)?\}/i', $mask, $reg)) {
            $counter = $reg[1];
            $maskraz = null;
            if (!empty($reg[2])) {
                if (preg_match('/^@/', $reg[2])) {
                    $maskraz = preg_replace('/@/', '', $reg[2]);
                }
            }
            $maskwithonlyymcode = (string) preg_replace('/\{(0+)([@\+][0-9\-\+\=]+)?\}/i', $counter, $mask);
            $maskwithnocode = (string) preg_replace('/\{([a-zA-Z]+)\}/', '_', $maskwithonlyymcode);
            $maskwithnocode = (string) preg_replace('/\{([a-zA-Z]+)\+([0-9]+)\}/', '_', $maskwithnocode);
        } else {
            return 'ErrorBadMask';
        }

        if (isset($maskraz) && $maskraz >= 0 && $maskraz <= 12) {
            $hasyear = str_contains($maskwithnocode, '{yyyy}') || str_contains($maskwithnocode, '{yy}') || str_contains($maskwithnocode, '{y}');
            if (!$hasyear) {
                return 'ErrorCantUseRazIfNoYearInMask';
            }
        }

        // Replace year/month/day tokens by their _ placeholder already done;
        // value must have same length as mask-with-code-expanded
        $maskregex = $maskwithnocode;
        $maskregex = str_replace('{yyyy}', '____', $maskregex);
        $maskregex = str_replace('{yy}', '__', $maskregex);
        $maskregex = str_replace('{y}', '_', $maskregex);
        $maskregex = str_replace('{mm}', '__', $maskregex);
        $maskregex = str_replace('{dd}', '__', $maskregex);
        $maskregex = str_replace('{d}', '_', $maskregex);
        $maskregex = str_replace('{m}', '_', $maskregex);
        $maskregex = (string) preg_replace('/\{([a-zA-Z]+)\}/', '_', $maskregex);
        $maskregex = (string) preg_replace('/\{([a-zA-Z]+)\+([0-9]+)\}/', '_', $maskregex);

        if (strlen($value) != strlen($maskregex)) {
            return -1;
        }

        return 0;
    }

    // ----- private helpers for the mask engine -----

    /** 1-based position of the counter inside the mask. */
    private function posIndex(string $mask, string $counter): int
    {
        $pos = strpos($mask, '{'.$counter);
        if ($pos === false) {
            return 1;
        }
        // position of the counter value inside the *expanded* mask
        $prefix = substr($mask, 0, $pos);
        $prefixExpanded = $this->expandMaskStatic($prefix);

        return strlen($prefixExpanded) + 1;
    }

    /**
     * Position (1-based, in the expanded mask) of the last date token, or
     * false if the mask has no year/month/day token.
     */
    private function posOfTokens(string $mask): int|false
    {
        if (!preg_match_all('/\{yyyy\}|\{yy\}|\{y\}|\{mm\}|\{m\}|\{dd\}|\{d\}/i', $mask, $m, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        $last = end($m[0]);
        $prefix = substr($mask, 0, $last[1]);

        return strlen($this->expandMaskStatic($prefix)) + strlen($last[0] === '{yyyy}' ? '____' : ($last[0] === '{yy}' || $last[0] === '{mm}' || $last[0] === '{dd}' ? '__' : '_'));
    }

    /** Replace date tokens in a mask fragment by literal values at $date. */
    private function expandMaskStatic(string $mask, int $date = 0): string
    {
        $date = $date ?: time();
        $out = $mask;
        $out = str_replace('{yyyy}', date('Y', $date), $out);
        $out = str_replace('{yy}', date('y', $date), $out);
        $out = str_replace('{y}', (string) ((int) date('y', $date) % 10), $out);
        $out = str_replace('{mm}', date('m', $date), $out);
        $out = str_replace('{m}', (string) (int) date('m', $date), $out);
        $out = str_replace('{dd}', date('d', $date), $out);
        $out = str_replace('{d}', (string) (int) date('d', $date), $out);

        return $out;
    }

    /**
     * Build the LIKE pattern: date tokens become concrete values when the
     * counter resets each period (raz), '_' wildcards otherwise.
     */
    private function buildMaskLike(string $maskwithnocode, int $date, int $maskraz, bool &$hasyear, bool &$hasmonth, bool &$hasday, int &$yearoffset, string $mask): string
    {
        $hasyear = (bool) preg_match('/\{yy+|y\}/i', $mask);
        $hasmonth = (bool) preg_match('/\{m+\}/i', $mask);
        $hasday = (bool) preg_match('/\{d+\}/i', $mask);

        // yearoffset: {yyyy+3} style
        if (preg_match('/\{yy?y?y?([\+\-][0-9]+)\}/i', $mask, $m)) {
            $yearoffset = (int) $m[1];
            $date = strtotime($yearoffset.' years', $date ?: time());
        } else {
            $date = $date ?: time();
        }

        $out = $maskwithnocode;
        if ($maskraz >= 0) {
            // raz: year/month/day tokens become literal current values
            $out = $this->expandMaskStatic($out, $date);
        } else {
            // no raz: tokens become _ wildcards
            $out = (string) preg_replace('/\{yyyy\}/i', '____', $out);
            $out = (string) preg_replace('/\{yy\}/i', '__', $out);
            $out = (string) preg_replace('/\{y\}/i', '_', $out);
            $out = (string) preg_replace('/\{mm\}/i', '__', $out);
            $out = (string) preg_replace('/\{m\}/i', '_', $out);
            $out = (string) preg_replace('/\{dd\}/i', '__', $out);
            $out = (string) preg_replace('/\{d\}/i', '_', $out);
            $out = (string) preg_replace('/\{([a-zA-Z]+)\}/', '_', $out);
            $out = (string) preg_replace('/\{([a-zA-Z]+)\+([0-9]+)\}/', '_', $out);
        }

        return $out;
    }

    private function buildMaskLikeNoRaz(string $maskwithnocode): string
    {
        $out = $maskwithnocode;
        $out = (string) preg_replace('/\{yyyy\}/i', '____', $out);
        $out = (string) preg_replace('/\{yy\}/i', '__', $out);
        $out = (string) preg_replace('/\{y\}/i', '_', $out);
        $out = (string) preg_replace('/\{mm\}/i', '__', $out);
        $out = (string) preg_replace('/\{m\}/i', '_', $out);
        $out = (string) preg_replace('/\{dd\}/i', '__', $out);
        $out = (string) preg_replace('/\{d\}/i', '_', $out);
        $out = (string) preg_replace('/\{([a-zA-Z]+)\}/', '_', $out);
        $out = (string) preg_replace('/\{([a-zA-Z]+)\+([0-9]+)\}/', '_', $out);

        return $out;
    }

    /**
     * sqlwhere for raz=99 (monthly reset): compare the SUBSTRING of the field
     * covering the year+month literals.
     */
    private function buildSqlWhereForRaz(string $mask, string $maskwithnocode, int $maskpos, int $date, string $mode): string
    {
        // literal value of the period prefix = expanded mask up to (not
        // including) the counter
        $prefixMask = substr($mask, 0, strpos($mask, '{0'));
        $prefix = $this->expandMaskStatic($prefixMask, $date ?: time());

        return 'SUBSTRING('.$this->fieldExpr().', 1, '.strlen($prefix).") = '".$this->escape($prefix)."'";
    }

    private function buildSqlWhereForRazFiscal(string $mask, string $maskwithnocode, int $maskpos, int $yearstart, int $month): string
    {
        // The counter resets in $month of each year: keep rows of the current
        // fiscal window [yearstart-month-01 .. yearstart+1-month-01). With the
        // LIKE already matching the mask, comparing the extracted year part is
        // enough — replicate by filtering on the year substring.
        $pos = strpos($mask, '{0');
        $prefixMask = substr($mask, 0, $pos === false ? 0 : $pos);

        // range covers two years: match where the year portion equals the
        // previous or current year value
        $y1 = $this->expandMaskStatic($prefixMask, mktime(0, 0, 0, $month, 1, $yearstart));
        $y2 = $this->expandMaskStatic($prefixMask, mktime(0, 0, 0, $month, 1, $yearstart + 1));

        return '(SUBSTRING('.$this->fieldExpr().', 1, '.strlen($y1).") = '".$this->escape($y1)."'"
            .' OR SUBSTRING('.$this->fieldExpr().', 1, '.strlen($y2).") = '".$this->escape($y2)."')";
    }

    /** Field name injected into generated sqlwhere fragments. */
    private string $currentField = '';

    private function fieldExpr(): string
    {
        return $this->currentField;
    }

    private function escape(string $v): string
    {
        return str_replace("'", "''", $v);
    }

    private function escapeLike(string $v): string
    {
        return $this->escape($v);
    }

    /**
     * Replace all mask tokens by their final values.
     */
    private function replaceTokens(string $mask, int $date, int $counterval, ?Company $company): string
    {
        $date = $date ?: time();
        $out = $mask;

        // year offset
        if (preg_match('/\{yy?y?y?([\+\-][0-9]+)\}/i', $out, $m)) {
            $date = strtotime($m[1].' years', $date) ?: $date;
        }

        $out = (string) preg_replace('/\{yyyy([\+\-][0-9]+)?\}/i', date('Y', $date), $out);
        $out = (string) preg_replace('/\{yy([\+\-][0-9]+)?\}/i', date('y', $date), $out);
        $out = (string) preg_replace('/\{y([\+\-][0-9]+)?\}/i', (string) ((int) date('y', $date) % 10), $out);
        $out = str_replace('{mm}', date('m', $date), $out);
        $out = str_replace('{m}', (string) (int) date('m', $date), $out);
        $out = str_replace('{dd}', date('d', $date), $out);
        $out = str_replace('{d}', (string) (int) date('d', $date), $out);

        // {t+} third-party type code (typent minus TE_ prefix)
        if (preg_match('/\{t\+?\}/i', $out)) {
            $typecode = '';
            if ($company !== null && $company->typent_code) {
                $typecode = (string) preg_replace('/^TE_/', '', $company->typent_code);
            }
            $out = (string) preg_replace('/\{t\+?\}/i', $typecode, $out);
        }

        // {u+} user lastname — API user, empty by default
        if (preg_match('/\{u\+?\}/i', $out)) {
            $out = (string) preg_replace('/\{u\+?\}/i', $this->config->apiUserLogin(), $out);
        }

        // {jj+} journal — not supported for third parties
        if (preg_match('/\{jj\+?\}/i', $out)) {
            $out = (string) preg_replace('/\{jj\+?\}/i', '', $out);
        }

        // {XXX-n} personalization: n literal chars, rest = counter
        // {c+0*} client-ref based counter is not supported upstream for
        // societe codes either — leave it expanded with the global counter.
        $out = (string) preg_replace_callback(
            '/\{(0+)([@\+][0-9\-\+\=]+)?\}/i',
            fn ($m) => str_pad((string) $counterval, strlen($m[1]), '0', STR_PAD_LEFT),
            $out,
        );

        // {XXX-n}: n chars kept, counter appended — implemented like upstream
        // (block of identical chars followed by -N)
        $out = (string) preg_replace_callback(
            '/\{(.)\1+-([0-9]+)\}/',
            function ($m) {
                $len = (int) $m[2];

                return str_repeat('_', max(0, $len - 1));
            },
            $out,
        );

        return $out;
    }
}
