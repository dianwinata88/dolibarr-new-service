<?php

declare(strict_types=1);

namespace App\BankAccount;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Verbatim port of the value sanitization upstream applies to API request
 * data: DolibarrApi::_checkValForAPI() + sanitizeVal()
 * (htdocs/api/class/api.class.php, htdocs/core/lib/functions.lib.php).
 *
 * Upstream quirk kept verbatim: createCompanyBankAccount() calls
 * _checkValForAPI() with the literal field name 'extrafields' for every
 * submitted field, so every scalar value on POST is sanitized as
 * 'alphanohtml'. updateCompanyBankAccount() uses the real field name, so
 * values are sanitized against the type declared in
 * CompanyBankAccount::$fields.
 */
final class FieldSanitizer
{
    /**
     * Properties that upstream rejects with a 400.
     * See DolibarrApi::_checkValForAPI().
     */
    private const FORBIDDEN_FIELDS = [
        'db', 'table_element', 'table_rowid', 'table_ref_field', 'table_element_line', 'element',
        'fk_element', 'element_for_permission', 'class_element_line', 'fields', 'TRIGGER_PREFIX',
        'picto', 'restrictiononfksoc', 'ismultientitymanaged', 'isextrafieldmanaged', 'module',
        'error', 'errorhidden', 'errors', 'warning', 'warnings', 'validateFieldsErrors', 'oldcopy',
        'oldref', 'newref', 'context', 'actionmsg', 'actionmsg2', 'thirdparty', 'user', 'tpl',
        'extraparams', 'childtables', 'childtablesoncascade',
    ];

    /**
     * The 'type' of every entry of upstream CompanyBankAccount::$fields,
     * verbatim. The sanitizer only inspects this map, nothing else.
     */
    private const FIELD_TYPES = [
        'rowid' => 'integer',
        'type' => 'varchar(32)',
        'fk_soc' => 'integer',
        'datec' => 'datetime',
        'tms' => 'timestamp',
        'label' => 'varchar(200)',
        'bank' => 'varchar(255)',
        'code_banque' => 'varchar(128)',
        'code_guichet' => 'varchar(6)',
        'number' => 'varchar(255)',
        'cle_rib' => 'varchar(5)',
        'bic' => 'varchar(20)',
        'iban_prefix' => 'varchar(34)',
        'domiciliation' => 'varchar(255)',
        'proprio' => 'varchar(60)',
        'owner_address' => 'varchar(255)',
        'default_rib' => 'smallint(6)',
        'state_id' => 'integer',
        'fk_country' => 'integer',
        'currency_code' => 'varchar(3)',
        'rum' => 'varchar(32)',
        'date_rum' => 'date',
        'frstrecur' => 'varchar(16)',
        'import_key' => 'varchar(14)',
        'last_four' => 'varchar(4)',
        'card_type' => 'varchar(255)',
        'cvn' => 'varchar(255)',
        'exp_date_month' => 'integer',
        'exp_date_year' => 'integer',
        'country_code' => 'varchar(10)',
        'approved' => 'integer',
        'email' => 'varchar(255)',
        'ending_date' => 'date',
        'max_total_amount_of_all_payments' => 'double(24,8)',
        'preapproval_key' => 'varchar(255)',
        'starting_date' => 'date',
        'total_amount_of_all_payments' => 'double(24,8)',
        'stripe_card_ref' => 'varchar(128)',
        'status' => 'integer',
        'comment' => 'varchar(255)',
        'ipaddress' => 'varchar(68)',
        'stripe_account' => 'varchar(128)',
        'last_main_doc' => 'varchar(255)',
    ];

    /**
     * POST path: upstream passes every field value through
     * _checkValForAPI('extrafields', ...) — 'extrafields' matches no declared
     * field type, so every scalar is sanitized with 'alphanohtml'.
     */
    public function sanitizeForCreate(mixed $value): mixed
    {
        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $key => $subvalue) {
                $cleaned[$key] = $this->sanitizeForUpdate((string) $key, $subvalue);
            }

            return $cleaned;
        }

        return $this->sanitizeVal($value, 'alphanohtml');
    }

    /**
     * PUT path: upstream _checkValForAPI() with the real field name.
     *
     * @return mixed
     */
    public function sanitizeForUpdate(string $field, mixed $value): mixed
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new BadRequestHttpException('Parameter ' . $field . ' is not allowed in request');
        }

        if (!is_array($value)) {
            if (in_array($field, self::FORBIDDEN_FIELDS, true)) {
                throw new BadRequestHttpException('Parameter ' . $field . ' is not allowed in request');
            }

            // Sanitize the value using the type declared in ->fields of the object.
            // Branches on upstream types not used by llx_societe_rib are kept
            // for parity with _checkValForAPI().
            /** @var string|null $type */
            $type = self::FIELD_TYPES[$field] ?? null;
            if ($type !== null && $type !== '') {
                if (
                    str_starts_with($type, 'int')
                    || str_starts_with($type, 'double')
                    || in_array($type, ['real', 'price', 'stock'], true)
                ) {
                    return $this->sanitizeVal($value, 'int');
                }
                if ($type === 'html') {
                    return $this->sanitizeVal($value, 'restricthtml');
                }
                if ($type === 'select') {
                    return $this->sanitizeVal($value, 'alphanohtml');
                }
                if ($type === 'sellist' || $type === 'checkbox') {
                    return $this->sanitizeVal($value, 'alphanohtml');
                }
                if ($type === 'boolean' || $type === 'radio') {
                    return $this->sanitizeVal($value, 'alphanohtml');
                }
                if ($type === 'email') {
                    return $this->sanitizeVal($value, 'email');
                }
                if ($type === 'password') {
                    return $this->sanitizeVal($value, 'password');
                }
                // Others will use 'alphanohtml'
            }

            // Guess a type from the field name for undeclared fields
            if (preg_match('/^fk_/i', $field)) {
                return $this->sanitizeVal($value, 'int');
            }
            if (in_array($field, ['note', 'note_private', 'note_public', 'desc', 'description'], true)) {
                return $this->sanitizeVal($value, 'restricthtml');
            }

            return $this->sanitizeVal($value, 'alphanohtml');
        }

        $cleaned = [];
        foreach ($value as $key => $subvalue) {
            $cleaned[$key] = $this->sanitizeForUpdate((string) $key, $subvalue);
        }

        return $cleaned;
    }

    /**
     * Port of sanitizeVal() — restricted to the checks reachable through the
     * bank-account API surface ('int', 'alphanohtml', 'email', 'password',
     * 'restricthtml').
     *
     * @return string|int|float
     */
    private function sanitizeVal(mixed $value, string $check): string|int|float
    {
        if ($value === null) {
            $value = '';
        }

        switch ($check) {
            case 'none':
            case 'password':
                break;
            case 'int': // Check param is a numeric value (integer but also float or hexadecimal)
                if (!is_numeric($value)) {
                    $value = '';
                }
                break;
            case 'email':
                $value = (string) filter_var($value, FILTER_SANITIZE_EMAIL);
                break;
            case 'alphanohtml':
            case 'alpha':
                $value = trim((string) $value);
                do {
                    $oldstringtoclean = $value;
                    // Remove html tags
                    $value = $this->stringNoHtmlTag($value);
                    // Refuse octal syntax \999, hexa syntax \x999 and unicode syntax \u{999}
                    $value = (string) preg_replace('/\\\([0-9xu])/', '/\1', $value);
                    // Remove also other dangerous string sequences
                    $value = str_ireplace(
                        ['../', '..\\', '&#38', '&#0000038', '&#x26', '&quot', '"', '&#34',
                            '&#0000034', '&#x22', '&#47', '&#0000047', '&#x2F', '&#92', '&#0000092', '&#x5C'],
                        '',
                        $value,
                    );
                } while ($oldstringtoclean !== $value);
                break;
            case 'restricthtml':
                // The full upstream implementation is dol_htmlwithnojs(); the
                // bank-account surface has no html fields, so the safe port
                // falls back to the same tag stripping as 'alphanohtml'.
                $value = $this->stringNoHtmlTag((string) $value);
                break;
            default:
                break;
        }

        return $value;
    }

    /**
     * Port of dol_string_nohtmltag($s, removelinefeed: 0, strip_tags: 0).
     */
    private function stringNoHtmlTag(string $stringtoclean): string
    {
        $temp = (string) preg_replace('/<br[^>]*>/i', "\n", $stringtoclean);

        // Remove entities before stripping
        $temp = html_entity_decode($temp, ENT_COMPAT | ENT_HTML5, 'UTF-8');

        $temp = str_replace('< ', '__ltspace__', $temp);
        $temp = str_replace('<:', '__lttwopoints__', $temp);

        // Remove '<' into remaining, removing non closing html tags like
        // '<abc' or '<<abc' ('<123abc' is not a html tag, '<abc123' is)
        $tempbis = $temp;
        do {
            $temp = $tempbis;
            $tempbis = str_replace('<>', '', $temp);
            $tempbis = (string) preg_replace('/<[^<>]+>/', '', $tempbis);
        } while ($tempbis !== $temp);
        $temp = $tempbis;

        $temp = (string) preg_replace('/<+([a-z]+)/i', '\1', $temp);

        $temp = html_entity_decode($temp, ENT_COMPAT, 'UTF-8');

        // removelinefeed = 0: line feeds are kept

        // Remove double spaces
        while (str_contains($temp, '  ')) {
            $temp = str_replace('  ', ' ', $temp);
        }

        $temp = str_replace('__ltspace__', '< ', $temp);
        $temp = str_replace('__lttwopoints__', '<:', $temp);

        return trim($temp);
    }
}
