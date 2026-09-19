<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Faithful ports of the Dolibarr helper functions the third-party API depends
 * on (functions.lib.php / functions2.lib.php / securitycore.lib.php).
 */
final class DolibarrUtils
{
    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    /**
     * Port of price2num() — normalize a numeric value according to the
     * rounding mode:
     *  - ''   : sanitize the value, no rounding
     *  - 'MU' : MAIN_MAX_DECIMALS_UNIT (default 5)
     *  - 'MT' : MAIN_MAX_DECIMALS_TOT (default 2, actually MAIN_MAX_DECIMALS_SHOWN
     *           falls back but upstream conf defaults to 2)
     *  - 'MS' : MAIN_MAX_DECIMALS_SHOWN (default 5)
     *  - 'CU'/'CT' : unit/tot decimals
     *  - 'CR' : 8
     *  - numeric N : round to N decimals
     *
     * @param numeric-string|float|int|null $amount
     */
    public function price2num(mixed $amount, string $rounding = '', string $option = ''): string
    {
        if ($amount === null) {
            $amount = '';
        }
        $option = $option === '' ? 'auto' : $option;

        // Cleaning: API context uses '.' as decimal and ',' as thousand separator
        $dec = '.';
        $thousand = ',';

        if ($option == '' || $option == 'auto') {
            // Convert everything to universal numeric
            $amount = str_replace(' ', '', (string) $amount); // avoid classic spaces
            $amount = preg_replace('/[a-zA-Z\/\*\\\\]/', '', $amount);
            if ($thousand != ',' && $thousand != '.') {
                $amount = str_replace((string) $thousand, '.', $amount);
            }
            if ($thousand == ',') {
                $amount = str_replace(',', '', $amount);
            }
            if ($dec != '.' && $dec != ',') {
                $amount = str_replace((string) $dec, '.', $amount);
            }
            if ($dec == ',') {
                $amount = str_replace(',', '.', $amount);
            }
        }

        if ($rounding === '') {
            return (string) $amount;
        }

        $nbdecimals = match ($rounding) {
            'MU' => $this->config->getInt('MAIN_MAX_DECIMALS_UNIT', 5),
            'MT' => $this->config->getInt('MAIN_MAX_DECIMALS_TOT', 2),
            'MS' => $this->config->getInt('MAIN_MAX_DECIMALS_SHOWN', 5),
            'CU' => $this->config->getInt('MAIN_MAX_DECIMALS_UNIT', 5),
            'CT' => $this->config->getInt('MAIN_MAX_DECIMALS_TOT', 2),
            'CR' => 8,
            default => ctype_digit((string) $rounding) ? (int) $rounding : 0,
        };

        return (string) round((float) $amount, $nbdecimals);
    }

    /**
     * Port of sanitizeVal() for the modes used by _checkValForAPI.
     */
    public function sanitizeVal(mixed $out, string $check): mixed
    {
        if ($out === null) {
            $out = '';
        }
        switch ($check) {
            case 'none':
            case 'password':
                break;
            case 'int':
                if (!is_numeric($out)) {
                    $out = '';
                }
                break;
            case 'email':
                $out = filter_var((string) $out, FILTER_SANITIZE_EMAIL);
                break;
            case 'aZ09':
                if (!is_array($out)) {
                    $out = trim((string) $out);
                    if (preg_match('/[^a-z0-9_\-\.]+/i', $out)) {
                        $out = '';
                    }
                }
                break;
            case 'alpha':
            case 'alphanohtml':
                if (!is_array($out)) {
                    $out = trim((string) $out);
                    do {
                        $oldstringtoclean = $out;
                        $out = $this->stringNohtmltag($out, 0);
                        $out = preg_replace('/\\\([0-9xu])/', '/\1', $out);
                        $out = str_ireplace(
                            ['../', '..\\', '&#38', '&#0000038', '&#x26', '&quot', '"', '&#34', '&#0000034', '&#x22', '&#47', '&#0000047', '&#x2F', '&#92', '&#0000092', '&#x5C'],
                            '',
                            $out,
                        );
                    } while ($oldstringtoclean != $out);
                }
                break;
            case 'nohtml':
                $out = $this->stringNohtmltag((string) $out, 0);
                break;
            case 'restricthtmlnolink':
            case 'restricthtml':
            case 'restricthtmlallowclass':
            case 'restricthtmlallowiframe':
            case 'restricthtmlallowlinkscript':
            case 'restricthtmlallowunvalid':
                $out = $this->htmlWithNoJs((string) $out);
                break;
            default:
                $out = $this->sanitizeVal($out, 'alphanohtml');
                break;
        }

        return $out;
    }

    /**
     * Port of dol_string_nohtmltag() — strip html tags, keep line feeds.
     */
    public function stringNohtmltag(string $stringtoclean, int $removelinefeed = 1): string
    {
        $temp = str_replace('&', '&amp;', $stringtoclean);
        $temp = strip_tags($temp);
        // Replace CR and LF when requested
        if ($removelinefeed) {
            $temp = str_replace(["\r\n", "\r", "\n"], ' ', $temp);
        }
        $temp = str_replace('&amp;', '&', $temp);

        return trim($temp);
    }

    /**
     * Approximate port of dol_htmlwithnojs() — strip javascript / dangerous
     * attributes from html (used by the restricthtml* sanitize modes).
     */
    public function htmlWithNoJs(string $stringtoclean): string
    {
        // Remove script/style and event handler attributes and javascript: links
        $out = $stringtoclean;
        do {
            $old = $out;
            $out = preg_replace('/<\/(script|iframe|form)/i', '</defhandled', $out);
            $out = preg_replace('/<(script|iframe|form)[^>]*>/i', '', $out);
            $out = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $out);
            $out = preg_replace("/\\son\\w+\\s*=\\s*'[^']*'/i", '', $out);
            $out = preg_replace('/\son\w+\s*=\s*[^\s>]+/i', '', $out);
            $out = preg_replace('/javascript\s*:/i', 'javascriptsanitized:', $out);
        } while ($old != $out);

        return $out;
    }

    /** Port of isValidEmail() (functions.lib.php). */
    public function isValidEmail(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Port of clean_url($url, 0): strips the scheme and lowercases the domain
     * of http/https URLs; returns other values unchanged.
     */
    public function cleanUrl(string $url): string
    {
        $regs = [];
        if (preg_match('/^(https?:[\\/]+)?([0-9A-Z.-]+\.[A-Z]{2,4})(:[0-9]+)?/i', $url, $regs)) {
            return strtolower($regs[2] ?? '') . (isset($regs[3]) ? $regs[3] : '');
        }

        return $url;
    }

    /**
     * Port of dol_concatdesc(): HTML-aware concatenation of two notes.
     */
    public function dolConcatdesc(string $text1, string $text2, bool $forxml = false, bool $invert = false): string
    {
        if ($invert) {
            [$text1, $text2] = [$text2, $text1];
        }

        $ishtml1 = $this->textIsHtml($text1);
        $ishtml2 = $this->textIsHtml($text2);

        $ret = (!$ishtml1 && $ishtml2) ? nl2br(htmlentities($text1, ENT_COMPAT, 'UTF-8')) : $text1;
        $ret .= ($text1 !== '' && $text2 !== '') ? (($ishtml1 || $ishtml2) ? ($forxml ? "<br \>\n" : "<br>\n") : "\n") : '';
        $ret .= ($ishtml1 && !$ishtml2) ? nl2br(htmlentities($text2, ENT_COMPAT, 'UTF-8')) : $text2;

        return $ret;
    }

    /** Port of dol_textishtml() — returns true when the string contains html tags. */
    public function textIsHtml(string $msg): bool
    {
        return preg_match('/<[^>]+>/', $msg) === 1;
    }

    /** Port of dol_string_unaccent(): transliterate accentuated chars. */
    public function stringUnaccent(string $str): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

        return $converted === false ? $str : $converted;
    }

    /**
     * Port of dol_string_nospecial(): keep only [a-z0-9] plus the char in
     * $newstr (default '-') replacing everything else.
     */
    public function stringNospecial(string $str, string $newstr = '_'): string
    {
        $str = preg_replace("/([^\w" . $newstr . "\(\)\[\]\{\}]+)/i", $newstr, $str);

        return str_replace(['  ', ' '], [$newstr, $newstr], $str);
    }

    /**
     * Port of dol_trunc(): truncate a string to $size chars.
     */
    public function dolTrunc(string $string, int $size = 40, string $trunc = 'right', string $stringformat = 'UTF-8', int $disallowhtml = 0): string
    {
        if (mb_strlen($string, $stringformat) <= $size) {
            return $string;
        }
        if ($trunc === 'middle') {
            $sizeleft = (int) round($size / 2);
            $sizeright = $size - $sizeleft;

            return mb_substr($string, 0, $sizeleft, $stringformat) . '..' . mb_substr($string, -$sizeright, null, $stringformat);
        }

        return mb_substr($string, 0, $size, $stringformat);
    }

    /**
     * Port of dol_print_date() for the formats used by this API slice.
     * Supports: 'dayhourlogsmall' (YmdHis), 'dayhourlog' (Ymd_His),
     * '%Y-%m-%d %H:%M:%S' style fallback via strftime-like handling.
     */
    public function printDate(int $time, string $format = ''): string
    {
        return match ($format) {
            'dayhourlogsmall' => date('YmdHis', $time),
            'dayhourlog', 'dayhour' => date('YmdHis', $time),
            'day', '%Y-%m-%d' => date('Y-m-d', $time),
            '%Y-%m-%d %H:%M:%S' => date('Y-m-d H:i:s', $time),
            default => date('Y-m-d H:i:s', $time),
        };
    }

    /**
     * Port of dol_getIdFromCode().
     */
    public function getIdFromCode(string $key, string $table, string $fieldkey = 'code', string $fieldid = 'id', int $entityfilter = 0, string $filters = ''): string|int
    {
        if ($key === '') {
            return 0;
        }

        $sql = sprintf(
            'SELECT %s as valuetoget FROM %s WHERE %s = %s',
            $this->sanitizeIdentifier($fieldid),
            'llx_' . $this->sanitizeIdentifier($table),
            $this->sanitizeIdentifier($fieldkey),
            ($fieldkey === 'id' || $fieldkey === 'rowid') ? (string) (int) $key : $this->db->quote($key),
        );
        if ($entityfilter) {
            $sql .= ' AND entity IN (' . $this->config->getEntity($table) . ')';
        }
        if ($filters) {
            $sql .= $filters;
        }

        $value = $this->db->fetchOne($sql);

        return $value === false ? '' : $value;
    }

    /**
     * Port of dolEncrypt() — 'dolcrypt:<cipher>:<iv>:<cipher>' format.
     * When no key is configured the plaintext is returned, as upstream does.
     */
    public function dolEncrypt(?string $chain): string
    {
        if ($chain === '' || $chain === null) {
            return '';
        }
        if (preg_match('/^(dolobfuscation|dolcrypt)[^:]*:([^:]+):(.+)$/', $chain)) {
            return $chain; // already encrypted
        }

        $key = $this->config->getString('DOLIBARR_DOLCRYPT_KEY', $this->config->getString('DOLIBARR_INSTANCE_UNIQUE_ID'));
        if ($key === '') {
            return $chain;
        }
        $key = (string) preg_replace('/,.*$/', '', $key);

        $ciphering = $this->config->getString('MAIN_SECURITY_REVERSIBLE_ALGO', 'aes-256-ctr');
        $ivlen = openssl_cipher_iv_length($ciphering);
        if ($ivlen === false || $ivlen < 1 || $ivlen > 32) {
            $ivlen = 16;
        }
        $ivseed = openssl_random_pseudo_bytes($ivlen);
        $newchain = openssl_encrypt($chain, $ciphering, $key, 0, $ivseed);

        return 'dolcrypt:' . $ciphering . ':' . $ivseed . ':' . $newchain;
    }

    /**
     * Port of dolDecrypt().
     */
    public function dolDecrypt(?string $chain): string
    {
        if ($chain === '' || $chain === null) {
            return '';
        }

        $key = $this->config->getString('DOLIBARR_DOLCRYPT_KEY', $this->config->getString('DOLIBARR_INSTANCE_UNIQUE_ID'));

        if (preg_match('/^crypted:(.+)$/', $chain, $reg)) {
            return (string) base64_decode($reg[1], true) ?: $chain;
        }

        if (preg_match('/^(dolobfuscation|dolcrypt)[^:]*:([^:]+):(.+)$/', $chain, $reg)) {
            $ciphering = $reg[2];
            if ($key === '') {
                return $chain;
            }
            $tmpexplode = explode(':', $reg[3]);
            if (!empty($tmpexplode[1])) {
                $data = $tmpexplode[1];
                $iv = $tmpexplode[0];
            } else {
                $data = (string) $tmpexplode[0];
                $iv = '';
            }

            foreach (explode(',', $key) as $tmpkey) {
                $newchain = openssl_decrypt($data, $ciphering, $tmpkey, 0, $iv);
                if ($newchain !== false && (mb_check_encoding($newchain, 'ASCII') || mb_check_encoding($newchain, 'UTF-8'))) {
                    return $newchain;
                }
            }

            return $chain;
        }

        return $chain;
    }

    /**
     * Port of getTaxesFromId($vatrate, $buyer, $seller, 0): find the c_tva row
     * matching a "rate (code)" string for the seller's country.
     *
     * @return array<string,mixed>
     */
    public function getTaxesFromId(string $vatrate, ?string $sellerCountryCode = null, ?string $buyerCountryCode = null): array
    {
        $vatratecleaned = $vatrate;
        $vatratecode = '';
        if (preg_match('/^(.*)\s*\((.*)\)$/', $vatrate, $reg)) {
            $vatratecleaned = $reg[1];
            $vatratecode = $reg[2];
        }

        $countrycode = $sellerCountryCode;
        if ($this->config->getString('SERVICE_ARE_ECOMMERCE_200238EC')) {
            $countrycode = $buyerCountryCode;
        }
        [, $mysocCountryCode] = $this->config->mysocCountry();
        if (empty($countrycode)) {
            $countrycode = $mysocCountryCode;
        }

        $sql = 'SELECT t.rowid, t.code, t.taux as rate, t.recuperableonly as npr, t.accountancy_code_sell, t.accountancy_code_buy, t.localtax1, t.localtax1_type, t.localtax2, t.localtax2_type'
            . ' FROM llx_c_tva as t, llx_c_country as c'
            . ' WHERE t.fk_pays = c.rowid'
            . " AND c.code = " . $this->db->quote((string) $countrycode)
            . ' AND t.taux = ' . (float) $vatratecleaned
            . ' AND t.active = 1'
            . ' AND t.entity IN (' . $this->config->getEntity('c_tva') . ')';
        if ($vatratecode !== '') {
            $sql .= ' AND t.code = ' . $this->db->quote($vatratecode);
        }

        $row = $this->db->fetchAssociative($sql);

        return $row === false ? [] : $row;
    }

    /** Sanitize an SQL identifier (table or column name). */
    public function sanitizeIdentifier(string $identifier): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_\.]/', '', $identifier);
    }
}
